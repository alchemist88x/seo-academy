<?php

namespace App\Domain\Learning\Services;

use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Quizzes\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourseNavigationService
{
    /**
     * Retrieve all active, visible lessons for a course ordered by module and lesson display order.
     * Empty modules, inactive modules, and hidden or inactive lessons are safely excluded.
     *
     * @return Collection<int, Lesson>
     */
    public function getOrderedLessons(Course $course): Collection
    {
        $modules = $course->modules()
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', 'Active')
                    ->orWhere('status', 'active');
            })
            ->orderBy('display_order')
            ->with(['lessons' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhere('status', 'Active')
                        ->orWhere('status', 'active');
                })
                    ->where('visibility', '!=', 'hidden')
                    ->orderBy('display_order');
            }])
            ->get();

        $orderedLessons = collect();

        foreach ($modules as $module) {
            foreach ($module->lessons as $lesson) {
                $orderedLessons->push($lesson);
            }
        }

        return $orderedLessons;
    }

    /**
     * Determine whether a specific lesson is unlocked for the user.
     * Admins and assigned trainers have unrestricted access.
     * Completed enrollments retain full read-only review access across all lessons.
     * Active students must satisfy sequential progression and explicit prerequisite rules.
     */
    public function isLessonUnlocked(Lesson $lesson, User $user, Course $course): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $course->trainers()->where('users.id', $user->id)->exists();
        }

        // Student authorization checks
        $enrollment = $user->studentProfile?->enrollments()
            ->where('course_id', $course->id)
            ->first();

        if (! $enrollment || ! $enrollment->allowsCourseAccess()) {
            return false;
        }

        // Completed enrollments retain unrestricted review access
        if ($enrollment->isCompleted()) {
            return true;
        }

        // Free preview lessons are always accessible
        if ($lesson->isFreePreview()) {
            return true;
        }

        $orderedLessons = $this->getOrderedLessons($course);
        $lessonIndex = $orderedLessons->search(fn (Lesson $l) => (string) $l->id === (string) $lesson->id);

        if ($lessonIndex === false) {
            return false;
        }

        // Fetch completed lesson IDs for this student in this course
        $completedLessonIds = LessonProgress::query()
            ->where(function ($q) use ($enrollment, $user) {
                $q->where('enrollment_id', $enrollment->id)
                    ->orWhere(function ($sub) use ($user) {
                        $sub->whereNull('enrollment_id')->where('user_id', $user->id);
                    });
            })
            ->whereIn('lesson_id', $orderedLessons->pluck('id'))
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        // 1. Check Course-level Sequential progression (if course->is_sequential is true)
        if ($course->is_sequential && $lessonIndex > 0) {
            for ($i = 0; $i < $lessonIndex; $i++) {
                $previousLessonId = (string) $orderedLessons[$i]->id;
                if (! in_array($previousLessonId, $completedLessonIds, true)) {
                    return false;
                }
            }
        }

        // 2. Check explicit lesson prerequisites from lesson_prerequisites table
        $prerequisiteLessonIds = DB::table('lesson_prerequisites')
            ->where('lesson_id', $lesson->id)
            ->pluck('prerequisite_lesson_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (! empty($prerequisiteLessonIds)) {
            foreach ($prerequisiteLessonIds as $prereqId) {
                if (! in_array($prereqId, $completedLessonIds, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Resolve the previous and next lesson relative to the current lesson.
     *
     * @return array{previous: ?Lesson, next: ?Lesson, next_unlocked: bool}
     */
    public function getPreviousAndNext(Course $course, Lesson $currentLesson, User $user): array
    {
        $orderedLessons = $this->getOrderedLessons($course);
        $currentIndex = $orderedLessons->search(fn (Lesson $l) => (string) $l->id === (string) $currentLesson->id);

        if ($currentIndex === false) {
            return [
                'previous' => null,
                'next' => null,
                'next_unlocked' => false,
            ];
        }

        $previous = $currentIndex > 0 ? $orderedLessons[$currentIndex - 1] : null;
        $next = $currentIndex < ($orderedLessons->count() - 1) ? $orderedLessons[$currentIndex + 1] : null;
        $nextUnlocked = $next !== null && $this->isLessonUnlocked($next, $user, $course);

        return [
            'previous' => $previous,
            'next' => $next,
            'next_unlocked' => $nextUnlocked,
        ];
    }

    /**
     * Resolve the resume lesson for a student in a course.
     * - Returns the student's latest in-progress lesson if unlocked.
     * - Otherwise returns the first unlocked, uncompleted lesson.
     * - If all are completed or no progress exists, returns the first accessible lesson.
     */
    public function resolveResumeLesson(Course $course, User $user): ?Lesson
    {
        $orderedLessons = $this->getOrderedLessons($course);

        if ($orderedLessons->isEmpty()) {
            return null;
        }

        $orderedLessonIds = $orderedLessons->pluck('id')->map(fn ($id) => (string) $id)->all();

        $enrollment = $user->studentProfile?->enrollments()
            ->where('course_id', $course->id)
            ->first();

        // 1. Check stored resume location on enrollment
        if ($enrollment && $enrollment->last_active_lesson_id) {
            $lastActive = $orderedLessons->first(fn (Lesson $l) => (string) $l->id === (string) $enrollment->last_active_lesson_id);
            if ($lastActive && $this->isLessonUnlocked($lastActive, $user, $course)) {
                return $lastActive;
            }
        }

        // 2. Check for most recently accessed in-progress lesson
        $latestInProgress = LessonProgress::query()
            ->where(function ($q) use ($enrollment, $user) {
                if ($enrollment) {
                    $q->where('enrollment_id', $enrollment->id)
                        ->orWhere(function ($sub) use ($user) {
                            $sub->whereNull('enrollment_id')->where('user_id', $user->id);
                        });
                } else {
                    $q->where('user_id', $user->id);
                }
            })
            ->whereIn('lesson_id', $orderedLessonIds)
            ->where('status', 'in_progress')
            ->orderByDesc('last_accessed_at')
            ->orderByDesc('updated_at')
            ->first();

        if ($latestInProgress) {
            $lesson = $orderedLessons->first(fn (Lesson $l) => (string) $l->id === (string) $latestInProgress->lesson_id);
            if ($lesson && $this->isLessonUnlocked($lesson, $user, $course)) {
                return $lesson;
            }
        }

        // 2. Query completed lessons
        $completedLessonIds = LessonProgress::query()
            ->where(function ($q) use ($enrollment, $user) {
                if ($enrollment) {
                    $q->where('enrollment_id', $enrollment->id)
                        ->orWhere(function ($sub) use ($user) {
                            $sub->whereNull('enrollment_id')->where('user_id', $user->id);
                        });
                } else {
                    $q->where('user_id', $user->id);
                }
            })
            ->whereIn('lesson_id', $orderedLessonIds)
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        // 3. Find first unlocked lesson that is NOT completed
        foreach ($orderedLessons as $lesson) {
            if (! in_array((string) $lesson->id, $completedLessonIds, true)) {
                if ($this->isLessonUnlocked($lesson, $user, $course)) {
                    return $lesson;
                }
            }
        }

        // 4. If all are completed or none found, return the first unlocked lesson
        foreach ($orderedLessons as $lesson) {
            if ($this->isLessonUnlocked($lesson, $user, $course)) {
                return $lesson;
            }
        }

        // Fallback to first lesson
        return $orderedLessons->first();
    }

    /**
     * Build the structured curriculum tree with unlock status, completion status, and progress metrics.
     *
     * @return array{
     *     modules: array<int, array<string, mixed>>,
     *     total_lessons: int,
     *     completed_lessons: int,
     *     progress_percent: int
     * }
     */
    public function getCurriculumStructure(Course $course, User $user, ?Lesson $currentLesson = null): array
    {
        $modules = $course->modules()
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', 'Active')
                    ->orWhere('status', 'active');
            })
            ->orderBy('display_order')
            ->with(['lessons' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhere('status', 'Active')
                        ->orWhere('status', 'active');
                })
                    ->where('visibility', '!=', 'hidden')
                    ->orderBy('display_order')
                    ->with(['materials']);
            }])
            ->get();

        $allLessons = $this->getOrderedLessons($course);
        $allLessonIds = $allLessons->pluck('id')->map(fn ($id) => (string) $id)->all();

        $enrollment = $user->studentProfile?->enrollments()
            ->where('course_id', $course->id)
            ->first();

        $progressMap = LessonProgress::query()
            ->where(function ($q) use ($enrollment, $user) {
                if ($enrollment) {
                    $q->where('enrollment_id', $enrollment->id)
                        ->orWhere(function ($sub) use ($user) {
                            $sub->whereNull('enrollment_id')->where('user_id', $user->id);
                        });
                } else {
                    $q->where('user_id', $user->id);
                }
            })
            ->whereIn('lesson_id', $allLessonIds)
            ->get()
            ->keyBy(fn ($item) => (string) $item->lesson_id);

        $prerequisitesMap = DB::table('lesson_prerequisites')
            ->whereIn('lesson_id', $allLessonIds)
            ->get()
            ->groupBy('lesson_id');

        $isCompletedEnrollment = $enrollment?->isCompleted() ?? false;
        $isAdminOrTrainer = $user->hasRole('admin') || ($user->hasRole('trainer') && $course->trainers()->where('users.id', $user->id)->exists());

        $structuredModules = [];
        $totalLessons = $allLessons->count();
        $completedLessonsCount = 0;

        $completedLessonIds = $progressMap->filter(fn ($p) => $p->status === 'completed')
            ->keys()
            ->map(fn ($id) => (string) $id)
            ->all();

        $completedLessonsCount = count($completedLessonIds);

        // Fetch assessments for the course and modules
        $courseAssessments = $course->quizzes()
            ->where('status', 'Active')
            ->whereNull('module_id')
            ->with('attempts', function ($q) use ($enrollment) {
                if ($enrollment) {
                    $q->where('enrollment_id', $enrollment->id);
                }
            })
            ->get();

        $moduleAssessments = Quiz::query()
            ->whereIn('module_id', $modules->pluck('id'))
            ->where('status', 'Active')
            ->with('attempts', function ($q) use ($enrollment) {
                if ($enrollment) {
                    $q->where('enrollment_id', $enrollment->id);
                }
            })
            ->get()
            ->groupBy('module_id');

        foreach ($modules as $module) {
            $moduleLessons = [];

            foreach ($module->lessons as $lesson) {
                $lessonIdStr = (string) $lesson->id;
                $progress = $progressMap->get($lessonIdStr);
                $isCompleted = $progress !== null && $progress->status === 'completed';
                $isInProgress = $progress !== null && $progress->status === 'in_progress';

                // Evaluate unlock status
                $isUnlocked = true;

                if (! $isAdminOrTrainer && ! $isCompletedEnrollment) {
                    if (! $lesson->isFreePreview()) {
                        // Sequential check
                        if ($course->is_sequential) {
                            $lessonIndex = $allLessons->search(fn (Lesson $l) => (string) $l->id === $lessonIdStr);
                            if ($lessonIndex > 0) {
                                for ($i = 0; $i < $lessonIndex; $i++) {
                                    $prevId = (string) $allLessons[$i]->id;
                                    if (! in_array($prevId, $completedLessonIds, true)) {
                                        $isUnlocked = false;
                                        break;
                                    }
                                }
                            }
                        }

                        // Explicit prerequisites check
                        if ($isUnlocked && isset($prerequisitesMap[$lesson->id])) {
                            foreach ($prerequisitesMap[$lesson->id] as $prereq) {
                                if (! in_array((string) $prereq->prerequisite_lesson_id, $completedLessonIds, true)) {
                                    $isUnlocked = false;
                                    break;
                                }
                            }
                        }
                    }
                }

                $moduleLessons[] = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'type' => $lesson->type,
                    'duration_seconds' => $lesson->duration_seconds,
                    'display_order' => $lesson->display_order,
                    'is_completed' => $isCompleted,
                    'is_in_progress' => $isInProgress,
                    'is_unlocked' => $isUnlocked,
                    'is_current' => $currentLesson !== null && (string) $lesson->id === (string) $currentLesson->id,
                    'materials_count' => $lesson->materials->count(),
                ];
            }

            $moduleQuizzes = [];
            if (isset($moduleAssessments[$module->id])) {
                foreach ($moduleAssessments[$module->id] as $quiz) {
                    $hasPassed = $enrollment ? $quiz->attempts->where('is_passed', true)->isNotEmpty() : false;
                    $moduleQuizzes[] = [
                        'id' => $quiz->id,
                        'title' => $quiz->title,
                        'type' => $quiz->type,
                        'is_required' => $quiz->is_required,
                        'passing_percentage' => $quiz->passing_percentage,
                        'is_passed' => $hasPassed,
                    ];
                }
            }

            $structuredModules[] = [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'display_order' => $module->display_order,
                'lessons' => $moduleLessons,
                'assessments' => $moduleQuizzes,
            ];
        }

        $courseQuizzes = [];
        foreach ($courseAssessments as $quiz) {
            $hasPassed = $enrollment ? $quiz->attempts->where('is_passed', true)->isNotEmpty() : false;
            $courseQuizzes[] = [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'type' => $quiz->type,
                'is_required' => $quiz->is_required,
                'passing_percentage' => $quiz->passing_percentage,
                'is_passed' => $hasPassed,
            ];
        }

        $progressPercent = $totalLessons > 0
            ? (int) round(($completedLessonsCount / $totalLessons) * 100)
            : 0;

        return [
            'modules' => $structuredModules,
            'course_assessments' => $courseQuizzes,
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessonsCount,
            'progress_percent' => $progressPercent,
        ];
    }
}
