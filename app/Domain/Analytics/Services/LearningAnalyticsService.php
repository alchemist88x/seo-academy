<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Assessments\Models\AssessmentAttempt;
use App\Domain\Assignments\Models\Assignment;
use App\Domain\Assignments\Models\AssignmentSubmission;
use App\Domain\Certificates\Models\Certificate;
use App\Domain\Courses\Models\Course;
use App\Domain\Grading\Models\EnrollmentFinalGrade;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LearningAnalyticsService
{
    /**
     * Get high-level academy metrics.
     *
     * @return array<string, int|float>
     */
    public function getAcademyOverviewMetrics(Academy $academy, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $totalStudents = StudentProfile::query()
            ->where('academy_id', $academy->id)
            ->count();

        $activeStudents = StudentProfile::query()
            ->where('academy_id', $academy->id)
            ->whereHas('user', function (Builder $q) {
                $q->where('is_active', true)->whereNull('suspended_at');
            })
            ->where('active', true)
            ->count();

        $suspendedStudents = StudentProfile::query()
            ->where('academy_id', $academy->id)
            ->where(function (Builder $q) {
                $q->whereHas('user', function (Builder $u) {
                    $u->where('is_active', false)->orWhereNotNull('suspended_at');
                })->orWhere('active', false);
            })
            ->count();

        $totalTrainers = TrainerProfile::query()
            ->where('academy_id', $academy->id)
            ->count();

        $publishedCourses = Course::forAcademy($academy)
            ->whereIn('status', ['Published', 'published', 'Active', 'active'])
            ->count();

        $totalCourses = Course::forAcademy($academy)->count();

        $enrollmentQuery = Enrollment::query()
            ->where('academy_id', $academy->id);

        if ($startDate) {
            $enrollmentQuery->where('enrolled_at', '>=', $startDate->startOfDay());
        }
        if ($endDate) {
            $enrollmentQuery->where('enrolled_at', '<=', $endDate->endOfDay());
        }

        $totalEnrollments = (clone $enrollmentQuery)->count();

        $activeEnrollments = (clone $enrollmentQuery)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, 'active', 'Active'])
            ->count();

        $completedEnrollments = (clone $enrollmentQuery)
            ->whereIn('status', [Enrollment::STATUS_COMPLETED, 'completed', 'Completed'])
            ->count();

        $courseCompletionRate = $totalEnrollments > 0
            ? round(($completedEnrollments / $totalEnrollments) * 100, 1)
            : 0.0;

        $certificateQuery = Certificate::query()
            ->whereHas('course', function (Builder $q) use ($academy) {
                $q->where('academy_id', $academy->id);
            })
            ->where('status', Certificate::STATUS_ISSUED);

        if ($startDate) {
            $certificateQuery->where('issued_at', '>=', $startDate->startOfDay());
        }
        if ($endDate) {
            $certificateQuery->where('issued_at', '<=', $endDate->endOfDay());
        }

        $certificatesIssued = $certificateQuery->count();

        return [
            'total_students' => $totalStudents,
            'active_students' => $activeStudents,
            'suspended_students' => $suspendedStudents,
            'total_trainers' => $totalTrainers,
            'published_courses' => $publishedCourses,
            'total_courses' => $totalCourses,
            'total_enrollments' => $totalEnrollments,
            'active_enrollments' => $activeEnrollments,
            'completed_enrollments' => $completedEnrollments,
            'course_completion_rate' => $courseCompletionRate,
            'certificates_issued' => $certificatesIssued,
        ];
    }

    /**
     * Get daily enrollment trend data.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    public function getEnrollmentTrends(Academy $academy, int $days = 30, ?string $courseId = null): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Enrollment::query()
            ->where('academy_id', $academy->id)
            ->where('enrolled_at', '>=', $startDate)
            ->where('enrolled_at', '<=', $endDate);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        // Works identically on SQLite and MySQL
        $records = $query
            ->selectRaw('DATE(enrolled_at) as date_val, COUNT(*) as aggregate_count')
            ->groupBy('date_val')
            ->orderBy('date_val')
            ->pluck('aggregate_count', 'date_val')
            ->all();

        $result = [];
        $current = clone $startDate;

        while ($current->lte($endDate)) {
            $dateKey = $current->toDateString();
            $result[] = [
                'date' => $dateKey,
                'label' => $current->format('M d'),
                'count' => (int) ($records[$dateKey] ?? 0),
            ];
            $current->addDay();
        }

        return $result;
    }

    /**
     * Get enrollment status distribution.
     *
     * @return array<string, int>
     */
    public function getEnrollmentStatusDistribution(Academy $academy, ?string $courseId = null): array
    {
        $query = Enrollment::query()->where('academy_id', $academy->id);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        $rawCounts = $query
            ->selectRaw('LOWER(status) as status_val, COUNT(*) as count_val')
            ->groupBy('status_val')
            ->pluck('count_val', 'status_val')
            ->all();

        return [
            'active' => (int) ($rawCounts['active'] ?? 0),
            'completed' => (int) ($rawCounts['completed'] ?? 0),
            'suspended' => (int) ($rawCounts['suspended'] ?? 0),
            'cancelled' => (int) ($rawCounts['cancelled'] ?? 0),
            'expired' => (int) ($rawCounts['expired'] ?? 0),
            'pending' => (int) ($rawCounts['pending'] ?? 0),
        ];
    }

    /**
     * Get student registration trend over time.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    public function getRegistrationTrends(Academy $academy, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $records = StudentProfile::query()
            ->where('academy_id', $academy->id)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->selectRaw('DATE(created_at) as date_val, COUNT(*) as aggregate_count')
            ->groupBy('date_val')
            ->orderBy('date_val')
            ->pluck('aggregate_count', 'date_val')
            ->all();

        $result = [];
        $current = clone $startDate;

        while ($current->lte($endDate)) {
            $dateKey = $current->toDateString();
            $result[] = [
                'date' => $dateKey,
                'label' => $current->format('M d'),
                'count' => (int) ($records[$dateKey] ?? 0),
            ];
            $current->addDay();
        }

        return $result;
    }

    /**
     * Get daily active students count over a window.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    public function getDailyActiveStudents(Academy $academy, int $days = 30, ?string $courseId = null): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = LessonProgress::query()
            ->where('lesson_progress.academy_id', $academy->id)
            ->where('lesson_progress.last_accessed_at', '>=', $startDate)
            ->where('lesson_progress.last_accessed_at', '<=', $endDate);

        if ($courseId) {
            $query->whereHas('enrollment', function (Builder $q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        $records = $query
            ->selectRaw('DATE(lesson_progress.last_accessed_at) as date_val, COUNT(DISTINCT lesson_progress.user_id) as aggregate_count')
            ->groupBy('date_val')
            ->orderBy('date_val')
            ->pluck('aggregate_count', 'date_val')
            ->all();

        $result = [];
        $current = clone $startDate;

        while ($current->lte($endDate)) {
            $dateKey = $current->toDateString();
            $result[] = [
                'date' => $dateKey,
                'label' => $current->format('M d'),
                'count' => (int) ($records[$dateKey] ?? 0),
            ];
            $current->addDay();
        }

        return $result;
    }

    /**
     * Get monthly active students.
     *
     * @return array<int, array{month: string, label: string, count: int}>
     */
    public function getMonthlyActiveStudents(Academy $academy, int $months = 6): array
    {
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $activeUserIds = LessonProgress::query()
            ->where('academy_id', $academy->id)
            ->where('last_accessed_at', '>=', $startDate)
            ->where('last_accessed_at', '<=', $endDate)
            ->select(['user_id', 'last_accessed_at'])
            ->get();

        $result = [];
        $current = clone $startDate;

        while ($current->lte($endDate)) {
            $monthKey = $current->format('Y-m');
            $monthLabel = $current->format('M Y');
            $startOfMonth = $current->copy()->startOfMonth();
            $endOfMonth = $current->copy()->endOfMonth();

            $count = $activeUserIds->filter(function ($item) use ($startOfMonth, $endOfMonth) {
                $time = Carbon::parse($item->last_accessed_at);

                return $time->gte($startOfMonth) && $time->lte($endOfMonth);
            })->pluck('user_id')->unique()->count();

            $result[] = [
                'month' => $monthKey,
                'label' => $monthLabel,
                'count' => $count,
            ];
            $current->addMonth();
        }

        return $result;
    }

    /**
     * Get lesson completions trend over time.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    public function getLessonsCompletedTrends(Academy $academy, int $days = 30, ?string $courseId = null): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = LessonProgress::query()
            ->where('lesson_progress.academy_id', $academy->id)
            ->where('lesson_progress.status', 'completed')
            ->whereNotNull('lesson_progress.completed_at')
            ->where('lesson_progress.completed_at', '>=', $startDate)
            ->where('lesson_progress.completed_at', '<=', $endDate);

        if ($courseId) {
            $query->whereHas('enrollment', function (Builder $q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        $records = $query
            ->selectRaw('DATE(lesson_progress.completed_at) as date_val, COUNT(*) as aggregate_count')
            ->groupBy('date_val')
            ->orderBy('date_val')
            ->pluck('aggregate_count', 'date_val')
            ->all();

        $result = [];
        $current = clone $startDate;

        while ($current->lte($endDate)) {
            $dateKey = $current->toDateString();
            $result[] = [
                'date' => $dateKey,
                'label' => $current->format('M d'),
                'count' => (int) ($records[$dateKey] ?? 0),
            ];
            $current->addDay();
        }

        return $result;
    }

    /**
     * Calculate average course progress across enrollments.
     */
    public function getAverageCourseProgress(Academy $academy, ?string $courseId = null): float
    {
        $coursesQuery = Course::forAcademy($academy)->withCount([
            'lessons' => function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('lessons.status')->orWhere('lessons.status', 'Active')->orWhere('lessons.status', 'active');
                });
            },
        ]);

        if ($courseId) {
            $coursesQuery->where('id', $courseId);
        }

        $courses = $coursesQuery->get()->keyBy('id');

        if ($courses->isEmpty()) {
            return 0.0;
        }

        $enrollmentsQuery = Enrollment::query()
            ->where('academy_id', $academy->id)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED]);

        if ($courseId) {
            $enrollmentsQuery->where('course_id', $courseId);
        }

        $enrollments = $enrollmentsQuery->withCount([
            'lessonProgress' => function ($q) {
                $q->where('status', 'completed');
            },
        ])->get();

        if ($enrollments->isEmpty()) {
            return 0.0;
        }

        $totalPercentages = 0.0;
        $count = 0;

        foreach ($enrollments as $enrollment) {
            $course = $courses->get($enrollment->course_id);
            if (! $course || $course->lessons_count === 0) {
                continue;
            }

            if ($enrollment->status === Enrollment::STATUS_COMPLETED) {
                $percent = 100.0;
            } else {
                $percent = min(100.0, ($enrollment->lesson_progress_count / $course->lessons_count) * 100.0);
            }

            $totalPercentages += $percent;
            $count++;
        }

        return $count > 0 ? round($totalPercentages / $count, 1) : 0.0;
    }

    /**
     * Get course engagement ranking (most and least engaged courses).
     *
     * @return array<int, array{
     *     course_id: string,
     *     title: string,
     *     enrolled_count: int,
     *     active_enrollments: int,
     *     completed_enrollments: int,
     *     completion_rate: float,
     *     average_progress: float,
     *     active_students_count: int
     * }>
     */
    public function getCourseEngagementRanking(Academy $academy): array
    {
        $courses = Course::forAcademy($academy)
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

        $ranking = [];
        foreach ($courses as $course) {
            $totalEnrolled = $course->enrollments_count;
            $completed = $course->completed_enrollments_count;
            $completionRate = $totalEnrolled > 0 ? round(($completed / $totalEnrolled) * 100, 1) : 0.0;
            $avgProgress = $this->getAverageCourseProgress($academy, $course->id);

            // Active students who accessed lessons in last 30 days
            $activeStudents = LessonProgress::query()
                ->where('academy_id', $academy->id)
                ->where('last_accessed_at', '>=', Carbon::now()->subDays(30))
                ->whereHas('enrollment', fn ($q) => $q->where('course_id', $course->id))
                ->distinct('user_id')
                ->count('user_id');

            $ranking[] = [
                'course_id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'enrolled_count' => $totalEnrolled,
                'active_enrollments' => $course->active_enrollments_count,
                'completed_enrollments' => $completed,
                'completion_rate' => $completionRate,
                'average_progress' => $avgProgress,
                'active_students_count' => $activeStudents,
            ];
        }

        usort($ranking, fn ($a, $b) => $b['active_students_count'] <=> $a['active_students_count']);

        return $ranking;
    }

    /**
     * Identify students with no recent activity.
     */
    public function getInactiveStudents(
        Academy $academy,
        int $inactiveDays = 14,
        ?string $courseId = null,
        ?User $trainer = null
    ): Collection {
        $threshold = Carbon::now()->subDays($inactiveDays);

        $query = Enrollment::query()
            ->where('enrollments.academy_id', $academy->id)
            ->whereIn('enrollments.status', [Enrollment::STATUS_ACTIVE, 'active', 'Active'])
            ->where(function ($q) use ($threshold) {
                $q->where('enrollments.last_active_at', '<', $threshold)
                    ->orWhereNull('enrollments.last_active_at');
            })
            ->with(['studentProfile.user', 'course']);

        if ($courseId) {
            $query->where('enrollments.course_id', $courseId);
        }

        if ($trainer) {
            $query->whereHas('course.trainers', function (Builder $q) use ($trainer) {
                $q->where('users.id', $trainer->id);
            });
        }

        return $query->get()->map(function (Enrollment $enrollment) {
            $user = $enrollment->studentProfile?->user;
            $lastActive = $enrollment->last_active_at;
            $daysInactive = $lastActive ? (int) $lastActive->diffInDays(Carbon::now()) : 999;

            return [
                'enrollment_id' => $enrollment->id,
                'student_profile_id' => $enrollment->student_profile_id,
                'user_id' => $user?->id,
                'student_name' => $user?->name ?? 'Unknown Student',
                'student_email' => $user?->email ?? 'N/A',
                'student_code' => $enrollment->studentProfile?->student_code ?? 'N/A',
                'course_id' => $enrollment->course_id,
                'course_title' => $enrollment->course?->title ?? 'N/A',
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
                'last_active_at' => $lastActive?->toIso8601String(),
                'days_inactive' => $daysInactive,
            ];
        });
    }

    /**
     * Get overall academic performance metrics.
     *
     * @return array<string, int|float>
     */
    public function getAcademicPerformanceMetrics(Academy $academy, ?string $courseId = null): array
    {
        $attemptsQuery = AssessmentAttempt::query()
            ->where('academy_id', $academy->id)
            ->where('status', AssessmentAttempt::STATUS_EVALUATED);

        if ($courseId) {
            $attemptsQuery->whereHas('enrollment', fn ($q) => $q->where('course_id', $courseId));
        }

        $totalAttempts = (clone $attemptsQuery)->count();
        $passedAttempts = (clone $attemptsQuery)->where('passed', true)->count();
        $avgScore = $totalAttempts > 0 ? (float) (clone $attemptsQuery)->avg('score_percentage') : 0.0;
        $passRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : 0.0;

        // Final published grades
        $gradesQuery = EnrollmentFinalGrade::query()
            ->whereHas('enrollment', function (Builder $q) use ($academy, $courseId) {
                $q->where('academy_id', $academy->id);
                if ($courseId) {
                    $q->where('course_id', $courseId);
                }
            });

        $totalGrades = (clone $gradesQuery)->count();
        $avgGrade = $totalGrades > 0 ? (float) (clone $gradesQuery)->avg('final_score') : 0.0;

        // Assignments
        $submissionsQuery = AssignmentSubmission::query()
            ->whereHas('enrollment', function (Builder $q) use ($academy, $courseId) {
                $q->where('academy_id', $academy->id);
                if ($courseId) {
                    $q->where('course_id', $courseId);
                }
            });

        $totalSubmissions = (clone $submissionsQuery)->count();
        $gradedSubmissions = (clone $submissionsQuery)->where('status', AssignmentSubmission::STATUS_GRADED)->count();
        $pendingSubmissions = (clone $submissionsQuery)->whereIn('status', [
            AssignmentSubmission::STATUS_SUBMITTED,
            AssignmentSubmission::STATUS_RESUBMITTED,
            AssignmentSubmission::STATUS_UNDER_REVIEW,
        ])->count();

        return [
            'total_assessment_attempts' => $totalAttempts,
            'passed_assessment_attempts' => $passedAttempts,
            'average_assessment_score' => round($avgScore, 1),
            'assessment_pass_rate' => $passRate,
            'total_published_grades' => $totalGrades,
            'average_grade_score' => round($avgGrade, 1),
            'total_assignment_submissions' => $totalSubmissions,
            'graded_assignment_submissions' => $gradedSubmissions,
            'pending_assignment_evaluations' => $pendingSubmissions,
        ];
    }

    /**
     * Get operational insights summary.
     *
     * @return array<string, mixed>
     */
    public function getOperationalInsights(Academy $academy, ?User $trainer = null): array
    {
        $inactiveStudents = $this->getInactiveStudents($academy, 14, null, $trainer);

        // Overdue assignments
        $overdueAssignmentsQuery = Assignment::query()
            ->where('academy_id', $academy->id)
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::now())
            ->where('status', Assignment::STATUS_PUBLISHED);

        if ($trainer) {
            $overdueAssignmentsQuery->whereHas('course.trainers', fn ($q) => $q->where('users.id', $trainer->id));
        }

        $overdueAssignments = $overdueAssignmentsQuery->with('course')->get();

        // Pending evaluations waiting > 48h
        $pendingQuery = AssignmentSubmission::query()
            ->whereHas('enrollment', fn ($q) => $q->where('academy_id', $academy->id))
            ->whereIn('status', [
                AssignmentSubmission::STATUS_SUBMITTED,
                AssignmentSubmission::STATUS_RESUBMITTED,
            ])
            ->where('submitted_at', '<', Carbon::now()->subHours(48))
            ->with(['assignment.course', 'user']);

        if ($trainer) {
            $pendingQuery->whereHas('assignment.course.trainers', fn ($q) => $q->where('users.id', $trainer->id));
        }

        $pendingEvaluations = $pendingQuery->get();

        // Courses with low completion (< 20% with >= 5 enrollments)
        $courseRanking = $this->getCourseEngagementRanking($academy);
        $lowCompletionCourses = array_filter($courseRanking, function ($c) {
            return $c['enrolled_count'] >= 5 && $c['completion_rate'] < 20.0;
        });

        return [
            'inactive_students_count' => $inactiveStudents->count(),
            'inactive_students' => $inactiveStudents->take(10)->values()->all(),
            'overdue_assignments_count' => $overdueAssignments->count(),
            'overdue_assignments' => $overdueAssignments->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'course_title' => $a->course?->title ?? 'N/A',
                'due_date' => $a->due_date?->toIso8601String(),
            ])->all(),
            'pending_evaluations_count' => $pendingEvaluations->count(),
            'pending_evaluations' => $pendingEvaluations->map(fn ($s) => [
                'id' => $s->id,
                'assignment_title' => $s->assignment?->title ?? 'N/A',
                'course_title' => $s->assignment?->course?->title ?? 'N/A',
                'student_name' => $s->user?->name ?? 'N/A',
                'submitted_at' => $s->submitted_at?->toIso8601String(),
            ])->all(),
            'low_completion_courses' => array_values($lowCompletionCourses),
        ];
    }
}
