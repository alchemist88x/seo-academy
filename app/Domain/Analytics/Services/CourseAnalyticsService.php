<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assessments\Models\AssessmentAttemptAnswer;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use Carbon\Carbon;

class CourseAnalyticsService
{
    /**
     * Get comprehensive analytics for a single course.
     *
     * @return array<string, mixed>
     */
    public function getCourseMetrics(Course $course, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $enrollmentQuery = Enrollment::query()->where('course_id', $course->id);

        if ($startDate) {
            $enrollmentQuery->where('enrolled_at', '>=', $startDate->startOfDay());
        }
        if ($endDate) {
            $enrollmentQuery->where('enrolled_at', '<=', $endDate->endOfDay());
        }

        $totalEnrolled = (clone $enrollmentQuery)->count();

        $activeEnrollments = (clone $enrollmentQuery)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, 'active', 'Active'])
            ->count();

        $completedEnrollments = (clone $enrollmentQuery)
            ->whereIn('status', [Enrollment::STATUS_COMPLETED, 'completed', 'Completed'])
            ->count();

        $completionRate = $totalEnrolled > 0
            ? round(($completedEnrollments / $totalEnrolled) * 100, 1)
            : 0.0;

        $certificatesIssued = Certificate::query()
            ->where('course_id', $course->id)
            ->where('status', Certificate::STATUS_ISSUED)
            ->count();

        // Total active lessons in course
        $modules = $course->modules()
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'Active')->orWhere('status', 'active');
            })
            ->orderBy('display_order')
            ->with(['lessons' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', 'Active')->orWhere('status', 'active');
                })->orderBy('display_order');
            }])
            ->get();

        $allLessons = $modules->flatMap->lessons;
        $totalLessonsCount = $allLessons->count();

        // Calculate average course progress across enrollments
        $averageProgress = 0.0;
        if ($totalEnrolled > 0 && $totalLessonsCount > 0) {
            $progressCounts = LessonProgress::query()
                ->whereHas('enrollment', fn ($q) => $q->where('course_id', $course->id))
                ->where('status', 'completed')
                ->selectRaw('enrollment_id, COUNT(*) as completed_count')
                ->groupBy('enrollment_id')
                ->pluck('completed_count', 'enrollment_id')
                ->all();

            $totalP = 0.0;
            foreach ($progressCounts as $c) {
                $totalP += min(100.0, ($c / $totalLessonsCount) * 100.0);
            }
            $averageProgress = round($totalP / $totalEnrolled, 1);
        }

        // Module-level breakdown
        $modulesBreakdown = [];
        foreach ($modules as $module) {
            $mLessons = $module->lessons;
            $mLessonCount = $mLessons->count();
            $mLessonIds = $mLessons->pluck('id');

            $mCompleted = 0;
            if ($mLessonCount > 0 && $totalEnrolled > 0) {
                $mCompleted = LessonProgress::query()
                    ->whereHas('enrollment', fn ($q) => $q->where('course_id', $course->id))
                    ->whereIn('lesson_id', $mLessonIds)
                    ->where('status', 'completed')
                    ->count();
            }

            $possibleCompletions = $mLessonCount * $totalEnrolled;
            $mRate = $possibleCompletions > 0 ? round(($mCompleted / $possibleCompletions) * 100, 1) : 0.0;

            $modulesBreakdown[] = [
                'module_id' => $module->id,
                'title' => $module->title,
                'display_order' => $module->display_order,
                'total_lessons' => $mLessonCount,
                'total_completions' => $mCompleted,
                'average_completion_rate' => $mRate,
            ];
        }

        // Lesson-level statistics
        $lessonsStats = [];
        $lessonCompletions = LessonProgress::query()
            ->whereHas('enrollment', fn ($q) => $q->where('course_id', $course->id))
            ->selectRaw('lesson_id, status, COUNT(*) as count_val')
            ->groupBy('lesson_id', 'status')
            ->get();

        $completionsByLesson = [];
        $inProgressByLesson = [];
        foreach ($lessonCompletions as $lc) {
            if ($lc->status === 'completed') {
                $completionsByLesson[$lc->lesson_id] = (int) $lc->count_val;
            } elseif ($lc->status === 'in_progress') {
                $inProgressByLesson[$lc->lesson_id] = (int) $lc->count_val;
            }
        }

        foreach ($modules as $mod) {
            foreach ($mod->lessons as $les) {
                $cCount = $completionsByLesson[$les->id] ?? 0;
                $ipCount = $inProgressByLesson[$les->id] ?? 0;
                $cRate = $totalEnrolled > 0 ? round(($cCount / $totalEnrolled) * 100, 1) : 0.0;

                // Abandonment indicator: in-progress without completion
                $abandonmentRate = ($cCount + $ipCount) > 0 ? round(($ipCount / ($cCount + $ipCount)) * 100, 1) : 0.0;

                $lessonsStats[] = [
                    'lesson_id' => $les->id,
                    'module_title' => $mod->title,
                    'title' => $les->title,
                    'type' => $les->type ?? 'video',
                    'display_order' => $les->display_order,
                    'completed_count' => $cCount,
                    'in_progress_count' => $ipCount,
                    'completion_rate' => $cRate,
                    'abandonment_rate' => $abandonmentRate,
                ];
            }
        }

        // Highest abandonment lessons (sorted by in_progress count and abandonment rate)
        $abandonmentLessons = $lessonsStats;
        usort($abandonmentLessons, fn ($a, $b) => $b['in_progress_count'] <=> $a['in_progress_count']);
        $highestAbandonment = array_slice($abandonmentLessons, 0, 5);

        // Low completion lessons (< 30% completion rate with enrolled students)
        $lowCompletionLessons = array_filter($lessonsStats, fn ($l) => $l['completion_rate'] < 30.0);
        usort($lowCompletionLessons, fn ($a, $b) => $a['completion_rate'] <=> $b['completion_rate']);

        // Stalled students (no activity in 14 days and not completed)
        $stalledThreshold = Carbon::now()->subDays(14);
        $stalledStudents = Enrollment::query()
            ->where('course_id', $course->id)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, 'active', 'Active'])
            ->where(function ($q) use ($stalledThreshold) {
                $q->where('last_active_at', '<', $stalledThreshold)
                    ->orWhereNull('last_active_at');
            })
            ->with('studentProfile.user')
            ->get()
            ->map(fn ($e) => [
                'enrollment_id' => $e->id,
                'student_name' => $e->studentProfile?->user?->name ?? 'Unknown Student',
                'student_email' => $e->studentProfile?->user?->email ?? 'N/A',
                'student_code' => $e->studentProfile?->student_code ?? 'N/A',
                'last_active_at' => $e->last_active_at?->toIso8601String(),
                'days_inactive' => $e->last_active_at ? (int) $e->last_active_at->diffInDays(Carbon::now()) : 999,
            ]);

        // Assessment performance for quizzes belonging to this course
        $assessmentAttempts = AssessmentAttempt::query()
            ->whereHas('enrollment', fn ($q) => $q->where('course_id', $course->id))
            ->where('status', AssessmentAttempt::STATUS_EVALUATED)
            ->get();

        $totalAttempts = $assessmentAttempts->count();
        $passedAttempts = $assessmentAttempts->where('passed', true)->count();
        $avgAssessmentScore = $totalAttempts > 0 ? round((float) $assessmentAttempts->avg('score_percentage'), 1) : 0.0;
        $assessmentPassRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : 0.0;

        // Question performance analysis
        $poorQuestions = $this->getPoorPerformingQuestions($course);

        // Assignment statistics
        $assignments = Assignment::query()->where('course_id', $course->id)->get();
        $assignmentIds = $assignments->pluck('id');
        $assignmentSubmissions = AssignmentSubmission::query()
            ->whereIn('assignment_id', $assignmentIds)
            ->get();

        $totalSubmissions = $assignmentSubmissions->count();
        $gradedSubmissions = $assignmentSubmissions->where('status', AssignmentSubmission::STATUS_GRADED)->count();
        $avgAssignmentGrade = $gradedSubmissions > 0
            ? round((float) $assignmentSubmissions->where('status', AssignmentSubmission::STATUS_GRADED)->avg('grade'), 1)
            : 0.0;

        return [
            'course_id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'total_enrolled' => $totalEnrolled,
            'active_enrollments' => $activeEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'completion_rate' => $completionRate,
            'average_progress' => $averageProgress,
            'certificates_issued' => $certificatesIssued,
            'total_lessons' => $totalLessonsCount,
            'modules_breakdown' => $modulesBreakdown,
            'lessons_breakdown' => $lessonsStats,
            'highest_abandonment_lessons' => $highestAbandonment,
            'low_completion_lessons' => array_values(array_slice($lowCompletionLessons, 0, 5)),
            'stalled_students_count' => $stalledStudents->count(),
            'stalled_students' => $stalledStudents->take(10)->values()->all(),
            'assessment_summary' => [
                'total_attempts' => $totalAttempts,
                'passed_attempts' => $passedAttempts,
                'average_score' => $avgAssessmentScore,
                'pass_rate' => $assessmentPassRate,
            ],
            'poor_performing_questions' => $poorQuestions,
            'assignment_summary' => [
                'total_assignments' => $assignments->count(),
                'total_submissions' => $totalSubmissions,
                'graded_submissions' => $gradedSubmissions,
                'average_grade' => $avgAssignmentGrade,
            ],
        ];
    }

    /**
     * Identify assessment questions with poor performance.
     *
     * @return array<int, array{question_id: string, question_text: string, total_answers: int, incorrect_count: int, failure_rate: float}>
     */
    public function getPoorPerformingQuestions(Course $course): array
    {
        $attemptIds = AssessmentAttempt::query()
            ->whereHas('enrollment', fn ($q) => $q->where('course_id', $course->id))
            ->pluck('id');

        if ($attemptIds->isEmpty()) {
            return [];
        }

        $answers = AssessmentAttemptAnswer::query()
            ->whereIn('attempt_id', $attemptIds)
            ->whereNotNull('canonical_question_id')
            ->get();

        if ($answers->isEmpty()) {
            return [];
        }

        $grouped = $answers->groupBy('canonical_question_id');
        $poorQuestions = [];

        foreach ($grouped as $qId => $items) {
            $total = $items->count();
            if ($total < 2) {
                continue;
            }

            $incorrect = $items->where('is_correct', false)->count();
            $failureRate = round(($incorrect / $total) * 100, 1);

            if ($failureRate >= 40.0) {
                $snapshot = $items->first()->question_snapshot;
                $questionText = $snapshot['question_text'] ?? $snapshot['title'] ?? 'Question #'.substr((string) $qId, 0, 8);

                $poorQuestions[] = [
                    'question_id' => (string) $qId,
                    'question_text' => $questionText,
                    'total_answers' => $total,
                    'incorrect_count' => $incorrect,
                    'failure_rate' => $failureRate,
                ];
            }
        }

        usort($poorQuestions, fn ($a, $b) => $b['failure_rate'] <=> $a['failure_rate']);

        return array_slice($poorQuestions, 0, 5);
    }
}
