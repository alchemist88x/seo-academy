<?php

namespace App\Domain\Courses\Policies;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Course $course): bool
    {
        if (! $user->is_active || $course->academy_id === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $course);
        }

        if (! $user->belongsToAcademy($course->academy_id)) {
            return false;
        }

        if (! $course->isPublished()) {
            return false;
        }

        if ($course->visibility === 'public') {
            return true;
        }

        if ($user->hasRole('student')) {
            return $user->studentProfile?->enrollments()
                ->allowsCourseAccess()
                ->where('course_id', $course->id)
                ->exists() ?? false;
        }

        return in_array($course->visibility, ['public', 'academy_only'], true);
    }

    public function create(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function update(User $user, Course $course): bool
    {
        if (! $user->is_active || $course->academy_id === null) {
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

    public function delete(User $user, Course $course): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function archive(User $user, Course $course): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function assignTrainers(User $user, Course $course): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function preview(User $user, Course $course): bool
    {
        if (! $user->is_active || $course->academy_id === null) {
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

    private function isAssignedTrainer(User $user, Course $course): bool
    {
        return $course->trainers()
            ->where('users.id', $user->getKey())
            ->exists();
    }
}
