<?php

namespace App\Domain\Learning\Services;

use App\Domain\Courses\Models\Course;
use App\Domain\Learning\Events\LessonCompleted;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Lessons\Models\Lesson;
use App\Domain\Students\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LearningProgressService
{
    public function __construct(
        protected CourseNavigationService $navigationService,
        protected ?CourseCompletionService $completionService = null
    ) {
        $this->completionService ??= app(CourseCompletionService::class);
    }

    /**
     * Record active participation in a lesson (heartbeat / activity ping).
     *
     * Principles:
     * - Opening or heartbeating a lesson NEVER marks it completed.
     * - Heartbeats safely update or create in_progress records.
     * - If already completed, status remains completed.
     * - Updates the enrollment resume location.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function recordHeartbeat(
        Enrollment $enrollment,
        Lesson $lesson,
        User $user,
        ?float $positionSeconds = null
    ): LessonProgress {
        return $this->recordActivity($enrollment, $lesson, $user, $positionSeconds);
    }

    /**
     * Record lesson activity with enrollment isolation.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function recordActivity(
        Enrollment $enrollment,
        Lesson $lesson,
        User $user,
        ?float $positionSeconds = null
    ): LessonProgress {
        $this->authorizeStudentEnrollment($enrollment, $user);
        $this->validateLessonBelongsToCourse($enrollment, $lesson);

        return DB::transaction(function () use ($enrollment, $lesson, $user, $positionSeconds) {
            $now = Carbon::now();

            // Locate existing progress record for this enrollment and lesson
            /** @var LessonProgress|null $progress */
            $progress = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->lockForUpdate()
                ->first();

            // Legacy fallback: check if unassociated record exists for this user and lesson
            if ($progress === null) {
                $legacyProgress = LessonProgress::query()
                    ->whereNull('enrollment_id')
                    ->where('user_id', $user->id)
                    ->where('lesson_id', $lesson->id)
                    ->lockForUpdate()
                    ->first();

                if ($legacyProgress !== null) {
                    $legacyProgress->enrollment_id = $enrollment->id;
                    $legacyProgress->save();
                    $progress = $legacyProgress;
                }
            }

            $validPosition = ($positionSeconds !== null && is_finite($positionSeconds) && $positionSeconds >= 0)
                ? (float) $positionSeconds
                : null;

            if ($progress === null) {
                $progress = LessonProgress::create([
                    'academy_id' => $enrollment->academy_id,
                    'enrollment_id' => $enrollment->id,
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id,
                    'status' => 'in_progress',
                    'last_position_seconds' => $validPosition ?? 0,
                    'progress_percent' => 0,
                    'last_accessed_at' => $now,
                ]);
            } else {
                $updates = [
                    'last_accessed_at' => $now,
                ];

                if ($progress->status === 'not_started') {
                    $updates['status'] = 'in_progress';
                }

                if ($validPosition !== null) {
                    $updates['last_position_seconds'] = $validPosition;
                }

                $progress->update($updates);
            }

            // Update enrollment's resume location and activity timestamp
            $enrollment->update([
                'last_active_lesson_id' => $lesson->id,
                'last_active_at' => $now,
            ]);

            return $progress->fresh();
        }, 5);
    }

    /**
     * Explicitly record lesson completion.
     *
     * Principles:
     * - Completion is a separate, intentional event.
     * - Repeated completion requests are strictly idempotent.
     * - A completed lesson remains completed upon revisit.
     * - Progress from other enrollments never influences this enrollment.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function completeLesson(Enrollment $enrollment, Lesson $lesson, User $user): LessonProgress
    {
        $this->authorizeStudentEnrollment($enrollment, $user);
        $this->validateLessonBelongsToCourse($enrollment, $lesson);

        return DB::transaction(function () use ($enrollment, $lesson, $user) {
            $now = Carbon::now();

            /** @var LessonProgress|null $progress */
            $progress = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->lockForUpdate()
                ->first();

            // Legacy check
            if ($progress === null) {
                $legacyProgress = LessonProgress::query()
                    ->whereNull('enrollment_id')
                    ->where('user_id', $user->id)
                    ->where('lesson_id', $lesson->id)
                    ->lockForUpdate()
                    ->first();

                if ($legacyProgress !== null) {
                    $legacyProgress->enrollment_id = $enrollment->id;
                    $legacyProgress->save();
                    $progress = $legacyProgress;
                }
            }

            if ($progress === null) {
                $progress = LessonProgress::create([
                    'academy_id' => $enrollment->academy_id,
                    'enrollment_id' => $enrollment->id,
                    'user_id' => $user->id,
                    'lesson_id' => $lesson->id,
                    'status' => 'completed',
                    'completed_at' => $now,
                    'progress_percent' => 100,
                    'last_accessed_at' => $now,
                ]);
            } else {
                $updates = [
                    'status' => 'completed',
                    'progress_percent' => 100,
                    'last_accessed_at' => $now,
                ];

                // Retain historical completed_at on idempotent re-completion
                if ($progress->completed_at === null) {
                    $updates['completed_at'] = $now;
                }

                $progress->update($updates);
            }

            // Update enrollment resume location
            $enrollment->update([
                'last_active_lesson_id' => $lesson->id,
                'last_active_at' => $now,
            ]);

            $savedProgress = $progress->fresh();

            // Dispatch lesson completed event after transaction commit
            DB::afterCommit(function () use ($enrollment, $lesson, $savedProgress) {
                event(new LessonCompleted($enrollment, $lesson, $savedProgress));
            });

            // Evaluate course completion requirements and update lifecycle if satisfied
            $this->completionService->completeCourse($enrollment, $user);

            return $savedProgress;
        }, 5);
    }

    /**
     * Retrieve the course completion service instance.
     */
    public function getCompletionService(): CourseCompletionService
    {
        return $this->completionService;
    }

    /**
     * Retrieve the structured completion diagnostic status for an enrollment.
     */
    public function getCompletionStatus(Enrollment $enrollment): array
    {
        return $this->completionService->getCompletionStatus($enrollment);
    }

    /**
     * Update the enrollment's resume location when entering or actively navigating to a lesson.
     *
     * Principles:
     * - Opening a lesson updates resume location and activity timestamp.
     * - Does NOT mark the lesson as completed.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function updateResumeLocation(Enrollment $enrollment, Lesson $lesson, User $user): void
    {
        $this->authorizeStudentEnrollment($enrollment, $user);
        $this->validateLessonBelongsToCourse($enrollment, $lesson);

        DB::transaction(function () use ($enrollment, $lesson, $user) {
            $now = Carbon::now();

            $enrollment->update([
                'last_active_lesson_id' => $lesson->id,
                'last_active_at' => $now,
            ]);

            // Update or initialize progress record without completing it
            $progress = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->first();

            if ($progress === null) {
                // Check legacy
                $legacyProgress = LessonProgress::query()
                    ->whereNull('enrollment_id')
                    ->where('user_id', $user->id)
                    ->where('lesson_id', $lesson->id)
                    ->first();

                if ($legacyProgress !== null) {
                    $legacyProgress->update([
                        'enrollment_id' => $enrollment->id,
                        'last_accessed_at' => $now,
                    ]);
                } else {
                    LessonProgress::create([
                        'academy_id' => $enrollment->academy_id,
                        'enrollment_id' => $enrollment->id,
                        'user_id' => $user->id,
                        'lesson_id' => $lesson->id,
                        'status' => 'in_progress',
                        'last_position_seconds' => 0,
                        'progress_percent' => 0,
                        'last_accessed_at' => $now,
                    ]);
                }
            } else {
                $progress->update([
                    'last_accessed_at' => $now,
                ]);
            }
        }, 5);
    }

    /**
     * Restore the student's resume lesson for an enrollment.
     * - Uses last_active_lesson_id if it remains valid, active, and unlocked.
     * - Falls back to the next incomplete lesson or first unlocked lesson.
     * - Never leaks or utilizes another enrollment's resume location.
     */
    public function getResumeLesson(Enrollment $enrollment, User $user): ?Lesson
    {
        $course = $enrollment->course;
        if ($course === null) {
            return null;
        }

        $orderedLessons = $this->navigationService->getOrderedLessons($course);
        if ($orderedLessons->isEmpty()) {
            return null;
        }

        // 1. Check stored last_active_lesson_id
        if (! empty($enrollment->last_active_lesson_id)) {
            /** @var Lesson|null $storedLesson */
            $storedLesson = $orderedLessons->first(
                fn (Lesson $l) => (string) $l->id === (string) $enrollment->last_active_lesson_id
            );

            if ($storedLesson !== null && $this->navigationService->isLessonUnlocked($storedLesson, $user, $course)) {
                return $storedLesson;
            }
        }

        // 2. Fallback: First unlocked, incomplete lesson for this enrollment
        $nextIncomplete = $this->getNextIncompleteLesson($enrollment, $user);
        if ($nextIncomplete !== null) {
            return $nextIncomplete;
        }

        // 3. Fallback: First unlocked lesson in the course
        foreach ($orderedLessons as $lesson) {
            if ($this->navigationService->isLessonUnlocked($lesson, $user, $course)) {
                return $lesson;
            }
        }

        return $orderedLessons->first();
    }

    /**
     * Retrieve the next incomplete lesson for an enrollment in sequence.
     */
    public function getNextIncompleteLesson(Enrollment $enrollment, ?User $user = null): ?Lesson
    {
        $course = $enrollment->course;
        if ($course === null) {
            return null;
        }

        $orderedLessons = $this->navigationService->getOrderedLessons($course);
        if ($orderedLessons->isEmpty()) {
            return null;
        }

        $completedLessonIds = LessonProgress::forEnrollment($enrollment)
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        foreach ($orderedLessons as $lesson) {
            if (! in_array((string) $lesson->id, $completedLessonIds, true)) {
                if ($user !== null) {
                    if ($this->navigationService->isLessonUnlocked($lesson, $user, $course)) {
                        return $lesson;
                    }
                } else {
                    return $lesson;
                }
            }
        }

        return null;
    }

    /**
     * Retrieve all progress records for an enrollment.
     *
     * @return EloquentCollection<int, LessonProgress>
     */
    public function getProgressForEnrollment(Enrollment $enrollment): EloquentCollection
    {
        return LessonProgress::forEnrollment($enrollment)->get();
    }

    /**
     * Retrieve progress for a specific lesson within an enrollment.
     */
    public function getLessonProgress(Enrollment $enrollment, Lesson $lesson): ?LessonProgress
    {
        return LessonProgress::forEnrollment($enrollment)
            ->where('lesson_id', $lesson->id)
            ->first();
    }

    /**
     * Check if a lesson is completed within an enrollment.
     */
    public function isLessonCompleted(Enrollment $enrollment, Lesson $lesson): bool
    {
        return LessonProgress::forEnrollment($enrollment)
            ->where('lesson_id', $lesson->id)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Calculate and return course progress metrics scoped strictly to an enrollment.
     *
     * @return array{
     *     total_lessons: int,
     *     completed_lessons: int,
     *     incomplete_lessons: int,
     *     completion_percentage: int,
     *     last_active_lesson: ?Lesson,
     *     last_active_at: ?Carbon,
     *     next_incomplete_lesson: ?Lesson
     * }
     */
    public function getCourseProgressSummary(Enrollment $enrollment): array
    {
        $course = $enrollment->course;
        if ($course === null) {
            return [
                'total_lessons' => 0,
                'completed_lessons' => 0,
                'incomplete_lessons' => 0,
                'completion_percentage' => 0,
                'last_active_lesson' => null,
                'last_active_at' => null,
                'next_incomplete_lesson' => null,
            ];
        }

        $orderedLessons = $this->navigationService->getOrderedLessons($course);
        $totalLessons = $orderedLessons->count();
        $orderedLessonIds = $orderedLessons->pluck('id')->all();

        // Scope progress strictly to this enrollment
        $completedLessonsCount = LessonProgress::forEnrollment($enrollment)
            ->whereIn('lesson_id', $orderedLessonIds)
            ->where('status', 'completed')
            ->count();

        $incompleteLessons = max(0, $totalLessons - $completedLessonsCount);
        $completionPercentage = $totalLessons > 0
            ? (int) round(($completedLessonsCount / $totalLessons) * 100)
            : 0;

        $lastActiveLesson = null;
        if (! empty($enrollment->last_active_lesson_id)) {
            $lastActiveLesson = $orderedLessons->first(
                fn (Lesson $l) => (string) $l->id === (string) $enrollment->last_active_lesson_id
            );
        }

        $nextIncompleteLesson = $this->getNextIncompleteLesson($enrollment);

        return [
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessonsCount,
            'incomplete_lessons' => $incompleteLessons,
            'completion_percentage' => $completionPercentage,
            'last_active_lesson' => $lastActiveLesson,
            'last_active_at' => $enrollment->last_active_at,
            'next_incomplete_lesson' => $nextIncompleteLesson,
        ];
    }

    /**
     * Authorize that the student owns the enrollment and has active course access.
     *
     * @throws AuthorizationException
     */
    public function authorizeStudentEnrollment(Enrollment $enrollment, User $user): void
    {
        if (! $user->is_active) {
            throw new AuthorizationException('User account is inactive.');
        }

        $studentProfile = $enrollment->studentProfile;
        if ($studentProfile === null || (string) $studentProfile->user_id !== (string) $user->id) {
            throw new AuthorizationException('You do not own this course enrollment.');
        }

        if (! $enrollment->allowsCourseAccess()) {
            throw new AuthorizationException('Enrollment does not permit course access.');
        }
    }

    /**
     * Validate that the lesson belongs to the enrolled course.
     *
     * @throws InvalidArgumentException
     */
    public function validateLessonBelongsToCourse(Enrollment $enrollment, Lesson $lesson): void
    {
        $lessonCourseId = $lesson->module?->course_id;

        if ($lessonCourseId === null || (string) $lessonCourseId !== (string) $enrollment->course_id) {
            throw new InvalidArgumentException('Lesson does not belong to the enrolled course.');
        }
    }
}
