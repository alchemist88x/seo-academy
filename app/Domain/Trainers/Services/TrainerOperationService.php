<?php

namespace App\Domain\Trainers\Services;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Domain\Students\Models\Enrollment;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrainerOperationService
{
    /**
     * Get trainer dashboard metrics for an academy.
     * Supports both ($trainer, $academy) and ($academy, $trainer).
     */
    public function getDashboardMetrics(User|Academy $arg1, User|Academy $arg2): array
    {
        $trainer = $arg1 instanceof User ? $arg1 : $arg2;
        $academy = $arg1 instanceof Academy ? $arg1 : $arg2;

        $assignedCoursesQuery = Course::query()
            ->forAcademy($academy)
            ->whereHas('trainers', function (Builder $q) use ($trainer): void {
                $q->where('users.id', $trainer->getKey());
            });

        $assignedCourseIds = (clone $assignedCoursesQuery)->pluck('courses.id');

        $totalAssignedCourses = $assignedCourseIds->count();

        $publishedAssignedCourses = (clone $assignedCoursesQuery)
            ->whereIn('status', ['Published', 'Active', 'published', 'active'])
            ->count();

        $draftAssignedCourses = (clone $assignedCoursesQuery)
            ->whereIn('status', ['Draft', 'draft'])
            ->count();

        $totalEnrolledStudents = 0;
        if ($assignedCourseIds->isNotEmpty()) {
            $totalEnrolledStudents = Enrollment::query()
                ->whereIn('course_id', $assignedCourseIds)
                ->whereIn('status', [Enrollment::STATUS_ACTIVE, 'active', 'Active'])
                ->distinct('student_profile_id')
                ->count('student_profile_id');
        }

        $recentCourses = collect();
        if ($assignedCourseIds->isNotEmpty()) {
            $recentCourses = (clone $assignedCoursesQuery)
                ->with('category')
                ->withCount([
                    'modules',
                    'lessons',
                    'enrollments' => function (Builder $q): void {
                        $q->whereIn('status', [Enrollment::STATUS_ACTIVE, 'active', 'Active']);
                    },
                ])
                ->latest()
                ->take(5)
                ->get()
                ->map(function (Course $course): Course {
                    $course->enrolled_count = $course->enrollments_count ?? 0;

                    return $course;
                });
        }

        $recentEnrollments = collect();
        if ($assignedCourseIds->isNotEmpty()) {
            $recentEnrollments = Enrollment::query()
                ->whereIn('course_id', $assignedCourseIds)
                ->with(['studentProfile.user', 'course'])
                ->latest('enrolled_at')
                ->latest('created_at')
                ->take(5)
                ->get();
        }

        return [
            'total_assigned_courses' => $totalAssignedCourses,
            'published_assigned_courses' => $publishedAssignedCourses,
            'draft_assigned_courses' => $draftAssignedCourses,
            'total_enrolled_students' => $totalEnrolledStudents,
            'recent_courses' => $recentCourses,
            'recent_enrollments' => $recentEnrollments,
        ];
    }

    /**
     * Alias for UI components expecting getMetrics.
     */
    public function getMetrics(Academy|User $arg1, Academy|User $arg2): array
    {
        $dashboard = $this->getDashboardMetrics($arg1, $arg2);

        $trainer = $arg1 instanceof User ? $arg1 : $arg2;
        $academy = $arg1 instanceof Academy ? $arg1 : $arg2;

        $assignedCourseIds = Course::query()
            ->forAcademy($academy)
            ->whereHas('trainers', function (Builder $q) use ($trainer): void {
                $q->where('users.id', $trainer->getKey());
            })
            ->pluck('courses.id');

        $activeCohorts = 0;
        if ($assignedCourseIds->isNotEmpty()) {
            $activeCohorts = StudentProfile::query()
                ->forAcademy($academy)
                ->whereNotNull('batch')
                ->where('batch', '!=', '')
                ->whereHas('enrollments', function (Builder $q) use ($assignedCourseIds): void {
                    $q->whereIn('course_id', $assignedCourseIds)
                        ->whereIn('status', [Enrollment::STATUS_ACTIVE, 'active', 'Active']);
                })
                ->distinct()
                ->count('batch');
        }

        return array_merge($dashboard, [
            'total_assigned_courses' => $dashboard['total_assigned_courses'],
            'published_courses' => $dashboard['published_assigned_courses'],
            'total_active_students' => $dashboard['total_enrolled_students'],
            'active_cohorts' => $activeCohorts,
        ]);
    }

    /**
     * Get recent course assignments for trainer.
     */
    public function getRecentCourseAssignments(Academy|User $arg1, Academy|User $arg2, int $limit = 5): Collection
    {
        $trainer = $arg1 instanceof User ? $arg1 : $arg2;
        $academy = $arg1 instanceof Academy ? $arg1 : $arg2;

        return Course::query()
            ->forAcademy($academy)
            ->whereHas('trainers', function (Builder $q) use ($trainer): void {
                $q->where('users.id', $trainer->getKey());
            })
            ->with(['category', 'enrollments'])
            ->withCount(['enrollments as active_enrollments_count' => function (Builder $q): void {
                $q->whereIn('status', [Enrollment::STATUS_ACTIVE, 'active', 'Active']);
            }])
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Get recent enrollment activity across trainer's assigned courses.
     */
    public function getRecentEnrollmentActivity(Academy|User $arg1, Academy|User $arg2, int $limit = 5): Collection
    {
        $trainer = $arg1 instanceof User ? $arg1 : $arg2;
        $academy = $arg1 instanceof Academy ? $arg1 : $arg2;

        $assignedCourseIds = Course::query()
            ->forAcademy($academy)
            ->whereHas('trainers', function (Builder $q) use ($trainer): void {
                $q->where('users.id', $trainer->getKey());
            })
            ->pluck('id');

        if ($assignedCourseIds->isEmpty()) {
            return collect();
        }

        return Enrollment::query()
            ->whereIn('course_id', $assignedCourseIds)
            ->with(['studentProfile.user', 'course'])
            ->latest('enrolled_at')
            ->latest('created_at')
            ->take($limit)
            ->get();
    }

    /**
     * Query enrollments belonging to courses assigned to the trainer.
     * Supports both ($trainer, $academy, ...) and ($academy, $trainer, ...).
     */
    public function listAssignedStudents(
        Academy|User $arg1,
        Academy|User $arg2,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        $trainer = $arg1 instanceof User ? $arg1 : $arg2;
        $academy = $arg1 instanceof Academy ? $arg1 : $arg2;

        $assignedCourseIds = Course::query()
            ->forAcademy($academy)
            ->whereHas('trainers', function (Builder $q) use ($trainer): void {
                $q->where('users.id', $trainer->getKey());
            })
            ->pluck('courses.id');

        // Never return students who are not enrolled in any course taught by this trainer
        if ($assignedCourseIds->isEmpty()) {
            return Enrollment::query()
                ->whereRaw('1 = 0')
                ->paginate($perPage);
        }

        $query = Enrollment::query()
            ->whereIn('course_id', $assignedCourseIds)
            ->with(['studentProfile.user', 'course']);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->whereHas('studentProfile', function (Builder $profileQuery) use ($search): void {
                $profileQuery->where('student_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $code = trim((string) ($filters['code'] ?? ''));
        if ($code !== '') {
            $query->whereHas('studentProfile', function (Builder $profileQuery) use ($code): void {
                $profileQuery->where('student_code', 'like', "%{$code}%");
            });
        }

        $courseId = trim((string) ($filters['course_id'] ?? ''));
        if ($courseId !== '' && $courseId !== 'all') {
            if ($assignedCourseIds->contains($courseId)) {
                $query->where('course_id', $courseId);
            } else {
                return Enrollment::query()
                    ->whereRaw('1 = 0')
                    ->paginate($perPage);
            }
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', strtolower($status));
        }

        return $query->latest('created_at')->paginate($perPage);
    }
}
