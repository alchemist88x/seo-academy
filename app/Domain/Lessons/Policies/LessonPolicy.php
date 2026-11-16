<?php

namespace App\Domain\Lessons\Policies;

use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Lesson $lesson): bool
    {
        $course = $lesson->module?->course;
        $academyId = $lesson->academy_id ?? $lesson->module?->academy_id ?? $course?->academy_id;

        if (! $user->is_active || $academyId === null || $course === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $course);
        }

        if (! $user->belongsToAcademy($academyId) || $lesson->visibility === 'hidden') {
            return false;
        }

        if (! $course->isPublished() || ! in_array($course->visibility, ['public', 'academy_only'], true)) {
            return false;
        }

        if ($lesson->isFreePreview()) {
            return true;
        }

        return $user->studentProfile?->enrollments()
            ->allowsCourseAccess()
            ->where('course_id', $course->id)
            ->exists() ?? false;
    }

    public function create(User $user, Module $module): bool
    {
        $course = $module->course;
        $academyId = $module->academy_id ?? $course?->academy_id;

        if (! $user->is_active || $academyId === null || $course === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $course);
        }

        return false;
    }

    public function update(User $user, Lesson $lesson): bool
    {
        $course = $lesson->module?->course;
        $academyId = $lesson->academy_id ?? $lesson->module?->academy_id ?? $course?->academy_id;

        if (! $user->is_active || $academyId === null || $course === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $course);
        }

        return false;
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }

    public function preview(User $user, Lesson $lesson): bool
    {
        $course = $lesson->module?->course;
        $academyId = $lesson->academy_id ?? $lesson->module?->academy_id ?? $course?->academy_id;

        if (! $user->is_active || $academyId === null || $course === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $course);
        }

        if (! $user->belongsToAcademy($academyId)) {
            return false;
        }

        if (! $course->isPublished() || ! in_array($course->visibility, ['public', 'academy_only'], true)) {
            return false;
        }

        if ($lesson->isFreePreview()) {
            return true;
        }

        // Active validly enrolled students can access preview and full view of lessons
        return $user->studentProfile?->enrollments()
            ->allowsCourseAccess()
            ->where('course_id', $course->id)
            ->exists() ?? false;
    }

    private function isAssignedTrainer(User $user, Course $course): bool
    {
        return $course->trainers()
            ->where('users.id', $user->getKey())
            ->exists();
    }
}
