<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TrainerAnalyticsService
{
    /**
     * Get trainer performance analytics strictly scoped to their assigned courses.
     *
     * @return array<string, mixed>
     */
    public function getTrainerAnalytics(User $trainer, Academy $academy, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // 1. Get assigned courses
        $assignedCourses = Course::query()
            ->forAcademy($academy)
            ->whereHas('trainers', fn (Builder $q) => $q->where('users.id', $trainer->id))
            ->withCount([
                'enrollments',
                'enrollments as active_enrollments_count' => function ($q) {
                    $q->whereIn('enrollments.status', [Enrollment::STATUS_ACTIVE, 'active', 'Active']);
                },
                'enrollments as completed_enrollments_count' => function ($q) {
                    $q->whereIn('enrollments.status', [Enrollment::STATUS_COMPLETED, 'completed', 'Completed']);
                },
                'lessons' => function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('lessons.status')->orWhere('lessons.status', 'Active')->orWhere('lessons.status', 'active');
                    });
                },
            ])
            ->get();

        $assignedCourseIds = $assignedCourses->pluck('id')->all();

        if (empty($assignedCourseIds)) {
            return [
                'trainer_id' => $trainer->id,
                'trainer_name' => $trainer->name,
                'total_assigned_courses' => 0,
                'total_distinct_students' => 0,
                'active_enrollments' => 0,
                'completed_enrollments' => 0,
                'overall_completion_rate' => 0.0,
                'courses' => [],
                'evaluation_workload' => [
                    'pending_evaluations' => 0,
                    'graded_evaluations' => 0,
                    'average_turnaround_hours' => 0.0,
                ],
                'assessment_summary' => [
                    'total_attempts' => 0,
                    'passed_attempts' => 0,
                    'pass_rate' => 0.0,
                    'average_score' => 0.0,
                ],
                'recent_student_activity' => [],
            ];
        }

        // Distinct students across assigned courses
        $distinctStudentsCount = Enrollment::query()
            ->whereIn('course_id', $assignedCourseIds)
            ->distinct('student_profile_id')
            ->count('student_profile_id');

        $activeEnrollments = $assignedCourses->sum('active_enrollments_count');
        $completedEnrollments = $assignedCourses->sum('completed_enrollments_count');
        $totalEnrollments = $assignedCourses->sum('enrollments_count');

        $overallCompletionRate = $totalEnrollments > 0
            ? round(($completedEnrollments / $totalEnrollments) * 100, 1)
            : 0.0;

        // Course breakdowns
        $coursesData = [];
        foreach ($assignedCourses as $c) {
            $cTotal = $c->enrollments_count;
            $cComp = $c->completed_enrollments_count;
            $cRate = $cTotal > 0 ? round(($cComp / $cTotal) * 100, 1) : 0.0;

            $coursesData[] = [
                'course_id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'status' => $c->status,
                'total_enrolled' => $cTotal,
                'active_enrollments' => $c->active_enrollments_count,
                'completed_enrollments' => $cComp,
                'completion_rate' => $cRate,
                'total_lessons' => $c->lessons_count,
            ];
        }

        // Assignment evaluation workload
        $submissionsQuery = AssignmentSubmission::query()
            ->whereHas('assignment', fn (Builder $q) => $q->whereIn('course_id', $assignedCourseIds));

        if ($startDate) {
            $submissionsQuery->where('submitted_at', '>=', $startDate->startOfDay());
        }
        if ($endDate) {
            $submissionsQuery->where('submitted_at', '<=', $endDate->endOfDay());
        }

        $pendingEvaluations = (clone $submissionsQuery)
            ->whereIn('status', [
                AssignmentSubmission::STATUS_SUBMITTED,
                AssignmentSubmission::STATUS_RESUBMITTED,
                AssignmentSubmission::STATUS_UNDER_REVIEW,
            ])
            ->count();

        $gradedSubmissions = (clone $submissionsQuery)
            ->where('status', AssignmentSubmission::STATUS_GRADED)
            ->with('latestEvaluation')
            ->get();

        $gradedCount = $gradedSubmissions->count();

        // Calculate average turnaround time in hours
        $totalTurnaroundHours = 0.0;
        $turnaroundSamples = 0;
        foreach ($gradedSubmissions as $sub) {
            $eval = $sub->latestEvaluation;
            if ($eval && $sub->submitted_at && $eval->graded_at) {
                $hours = Carbon::parse($sub->submitted_at)->diffInHours(Carbon::parse($eval->graded_at));
                $totalTurnaroundHours += $hours;
                $turnaroundSamples++;
            }
        }

        $avgTurnaroundHours = $turnaroundSamples > 0
            ? round($totalTurnaroundHours / $turnaroundSamples, 1)
            : 0.0;

        // Assessment performance across assigned courses
        $attempts = AssessmentAttempt::query()
            ->whereHas('enrollment', fn ($q) => $q->whereIn('course_id', $assignedCourseIds))
            ->where('status', AssessmentAttempt::STATUS_EVALUATED)
            ->get();

        $totalAttempts = $attempts->count();
        $passedAttempts = $attempts->where('passed', true)->count();
        $avgScore = $totalAttempts > 0 ? round((float) $attempts->avg('score_percentage'), 1) : 0.0;
        $passRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : 0.0;

        // Recent student activity across assigned courses
        $recentActivity = LessonProgress::query()
            ->whereHas('enrollment', fn ($q) => $q->whereIn('course_id', $assignedCourseIds))
            ->whereNotNull('last_accessed_at')
            ->with(['user', 'lesson', 'enrollment.course'])
            ->latest('last_accessed_at')
            ->take(10)
            ->get()
            ->map(fn (LessonProgress $lp) => [
                'student_name' => $lp->user?->name ?? 'Student',
                'course_title' => $lp->enrollment?->course?->title ?? 'N/A',
                'lesson_title' => $lp->lesson?->title ?? 'Lesson',
                'status' => $lp->status,
                'last_accessed_at' => $lp->last_accessed_at?->toIso8601String(),
            ]);

        return [
            'trainer_id' => $trainer->id,
            'trainer_name' => $trainer->name,
            'total_assigned_courses' => count($assignedCourses),
            'total_distinct_students' => $distinctStudentsCount,
            'active_enrollments' => $activeEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'overall_completion_rate' => $overallCompletionRate,
            'courses' => $coursesData,
            'evaluation_workload' => [
                'pending_evaluations' => $pendingEvaluations,
                'graded_evaluations' => $gradedCount,
                'average_turnaround_hours' => $avgTurnaroundHours,
            ],
            'assessment_summary' => [
                'total_attempts' => $totalAttempts,
                'passed_attempts' => $passedAttempts,
                'pass_rate' => $passRate,
                'average_score' => $avgScore,
            ],
            'recent_student_activity' => $recentActivity->all(),
        ];
    }
}
