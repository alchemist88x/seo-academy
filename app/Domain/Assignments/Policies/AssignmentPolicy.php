<?php

namespace App\Domain\Assignments\Policies;

use App\Domain\Assignments\Models\Assignment;
use App\Domain\Courses\Models\Course;
use App\Models\User;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Assignment $assignment): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $assignment->course);
        }

        if ($user->hasRole('student')) {
            if (! $assignment->isPublished()) {
                return false;
            }

            return $user->studentProfile?->enrollments()
                ->allowsCourseAccess()
                ->where('course_id', $assignment->course_id)
                ->exists() ?? false;
        }

        return false;
    }

    public function create(User $user, ?Course $course = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            if ($course === null) {
                return true;
            }

            return $this->isAssignedTrainer($user, $course);
        }

        return false;
    }

    public function update(User $user, Assignment $assignment): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $assignment->course);
        }

        return false;
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            if (! $this->isAssignedTrainer($user, $assignment->course)) {
                return false;
            }

            // Cannot delete if submissions already exist
            return ! $assignment->submissions()->exists();
        }

        return false;
    }

    public function publish(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function archive(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        if (! $user->is_active || ! $user->hasRole('student')) {
            return false;
        }

        $hasAccess = $user->studentProfile?->enrollments()
            ->allowsCourseAccess()
            ->where('course_id', $assignment->course_id)
            ->exists() ?? false;

        if (! $hasAccess) {
            return false;
        }

        return $assignment->canStudentSubmit($user);
    }

    public function grade(User $user, Assignment $assignment): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $assignment->course);
        }

        return false;
    }

    public function overrideGrade(User $user, Assignment $assignment): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    private function isAssignedTrainer(User $user, ?Course $course): bool
    {
        if ($course === null) {
            return false;
        }

        return $course->trainers()
            ->where('users.id', $user->getKey())
            ->exists();
    }
}
