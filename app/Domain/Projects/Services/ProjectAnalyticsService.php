<?php

namespace App\Domain\Projects\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\ProjectAchievement;
use App\Domain\Projects\Models\ProjectEvaluation;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;

class ProjectAnalyticsService
{
    /**
     * Get aggregate academy-wide practical project KPIs.
     *
     * @return array{
     *     total_projects: int,
     *     published_projects: int,
     *     total_submissions: int,
     *     approved_submissions: int,
     *     completion_rate_percent: float,
     *     late_submissions_count: int,
     *     late_submissions_percent: float,
     *     average_score_percent: float,
     *     revision_rate_percent: float,
     *     average_turnaround_hours: float,
     *     total_achievements_issued: int
     * }
     */
    public function getAcademyProjectKpis(?Academy $academy = null): array
    {
        $academyId = $academy?->getKey() ?? Academy::current()?->getKey();

        $projectQuery = PracticalProject::query();
        if ($academyId) {
            $projectQuery->where('academy_id', $academyId);
        }

        $totalProjects = (clone $projectQuery)->count();
        $publishedProjects = (clone $projectQuery)->where('status', PracticalProject::STATUS_PUBLISHED)->count();

        $submissionQuery = ProjectSubmission::query()
            ->whereHas('project', function ($q) use ($academyId) {
                if ($academyId) {
                    $q->where('academy_id', $academyId);
                }
            });

        $totalSubmissions = (clone $submissionQuery)->count();
        $approvedSubmissions = (clone $submissionQuery)->where('status', ProjectSubmission::STATUS_APPROVED)->count();
        $lateSubmissions = (clone $submissionQuery)->where('is_late', true)->count();

        $completionRate = $totalSubmissions > 0 ? round(($approvedSubmissions / $totalSubmissions) * 100.0, 1) : 0.0;
        $latePercent = $totalSubmissions > 0 ? round(($lateSubmissions / $totalSubmissions) * 100.0, 1) : 0.0;

        $avgScore = (float) ((clone $submissionQuery)->whereNotNull('score_percentage')->avg('score_percentage') ?? 0.0);

        $revisionsCount = (clone $submissionQuery)->where('version_number', '>', 1)->count();
        $revisionRate = $totalSubmissions > 0 ? round(($revisionsCount / $totalSubmissions) * 100.0, 1) : 0.0;

        // Turnaround hours calculation
        $turnaroundHours = $this->calculateAverageTurnaroundHours($academyId);

        $achievementsQuery = ProjectAchievement::query()
            ->whereHas('project', function ($q) use ($academyId) {
                if ($academyId) {
                    $q->where('academy_id', $academyId);
                }
            });
        $totalAchievements = $achievementsQuery->count();

        return [
            'total_projects' => $totalProjects,
            'published_projects' => $publishedProjects,
            'total_submissions' => $totalSubmissions,
            'approved_submissions' => $approvedSubmissions,
            'completion_rate_percent' => $completionRate,
            'late_submissions_count' => $lateSubmissions,
            'late_submissions_percent' => $latePercent,
            'average_score_percent' => round($avgScore, 1),
            'revision_rate_percent' => $revisionRate,
            'average_turnaround_hours' => round($turnaroundHours, 1),
            'total_achievements_issued' => $totalAchievements,
        ];
    }

    /**
     * Get trainer-scoped practical project analytics.
     *
     * @return array{
     *     assigned_projects_count: int,
     *     pending_evaluations_count: int,
     *     completed_evaluations_count: int,
     *     average_turnaround_hours: float,
     *     approval_rate_percent: float
     * }
     */
    public function getTrainerProjectAnalytics(User $trainer): array
    {
        $assignedCourseIds = Course::query()
            ->whereHas('trainers', function ($q) use ($trainer) {
                $q->where('users.id', $trainer->getKey());
            })
            ->pluck('id')
            ->all();

        $projectIds = PracticalProject::query()
            ->whereIn('course_id', $assignedCourseIds)
            ->pluck('id')
            ->all();

        $pendingCount = ProjectSubmission::query()
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', [ProjectSubmission::STATUS_SUBMITTED, ProjectSubmission::STATUS_RESUBMITTED])
            ->count();

        $evaluations = ProjectEvaluation::query()
            ->where('evaluator_id', $trainer->getKey())
            ->get();

        $completedCount = $evaluations->count();
        $approvedCount = $evaluations->where('status', ProjectEvaluation::STATUS_APPROVED)->count();
        $approvalRate = $completedCount > 0 ? round(($approvedCount / $completedCount) * 100.0, 1) : 0.0;

        // Turnaround
        $turnaroundHours = 0.0;
        if ($completedCount > 0) {
            $totalHours = 0.0;
            $counted = 0;
            foreach ($evaluations as $eval) {
                if ($eval->submission && $eval->submission->submitted_at && $eval->evaluated_at) {
                    $diff = $eval->evaluated_at->diffInMinutes($eval->submission->submitted_at) / 60.0;
                    $totalHours += max(0.0, $diff);
                    $counted++;
                }
            }
            $turnaroundHours = $counted > 0 ? $totalHours / $counted : 0.0;
        }

        return [
            'assigned_projects_count' => count($projectIds),
            'pending_evaluations_count' => $pendingCount,
            'completed_evaluations_count' => $completedCount,
            'average_turnaround_hours' => round($turnaroundHours, 1),
            'approval_rate_percent' => $approvalRate,
        ];
    }

    /**
     * Get student project performance breakdown.
     *
     * @return array{
     *     assigned_count: int,
     *     submitted_count: int,
     *     approved_count: int,
     *     revisions_requested_count: int,
     *     average_score_percent: float,
     *     skills_demonstrated: array<int, string>
     * }
     */
    public function getStudentProjectPerformance(StudentProfile $studentProfile): array
    {
        $submissions = ProjectSubmission::query()
            ->where('student_profile_id', $studentProfile->id)
            ->with(['project', 'achievement'])
            ->get();

        $totalSubmissions = $submissions->count();
        $approved = $submissions->where('status', ProjectSubmission::STATUS_APPROVED);
        $revisions = $submissions->where('status', ProjectSubmission::STATUS_REVISION_REQUESTED);

        $avgScore = $submissions->whereNotNull('score_percentage')->avg('score_percentage') ?? 0.0;

        // Gather unique skills demonstrated across approved projects
        $skills = collect();
        foreach ($approved as $sub) {
            if ($sub->project) {
                $skills->push($sub->project->categoryLabel());
            }
            if ($sub->achievement && ! empty($sub->achievement->skills_awarded)) {
                $skills = $skills->merge($sub->achievement->skills_awarded);
            }
        }

        return [
            'assigned_count' => PracticalProject::query()->where('status', PracticalProject::STATUS_PUBLISHED)->count(),
            'submitted_count' => $totalSubmissions,
            'approved_count' => $approved->count(),
            'revisions_requested_count' => $revisions->count(),
            'average_score_percent' => round((float) $avgScore, 1),
            'skills_demonstrated' => $skills->unique()->values()->all(),
        ];
    }

    /**
     * Compute average turnaround hours between submission and first evaluation.
     */
    protected function calculateAverageTurnaroundHours(?string $academyId = null): float
    {
        $evaluations = ProjectEvaluation::query()
            ->with('submission')
            ->whereHas('submission.project', function ($q) use ($academyId) {
                if ($academyId) {
                    $q->where('academy_id', $academyId);
                }
            })
            ->limit(200)
            ->get();

        if ($evaluations->isEmpty()) {
            return 0.0;
        }

        $totalHours = 0.0;
        $count = 0;

        foreach ($evaluations as $eval) {
            if ($eval->submission && $eval->submission->submitted_at && $eval->evaluated_at) {
                $diff = $eval->evaluated_at->diffInMinutes($eval->submission->submitted_at) / 60.0;
                $totalHours += max(0.0, $diff);
                $count++;
            }
        }

        return $count > 0 ? $totalHours / $count : 0.0;
    }
}
