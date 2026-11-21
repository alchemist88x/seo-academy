<?php

namespace App\Domain\Projects\Policies;

use App\Domain\Projects\Models\PracticalProject;
use App\Domain\Projects\Models\ProjectSubmission;
use App\Models\User;

class PracticalProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, PracticalProject $project): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            if (! $project->course_id) {
                return false;
            }

            return $project->course && $project->course->trainers()->where('users.id', $user->getKey())->exists();
        }

        if ($user->hasRole('student')) {
            if (! $project->isPublished()) {
                return false;
            }

            $profile = $user->studentProfile;
            if (! $profile) {
                return false;
            }

            // Check individual assignment
            if ($project->assignedStudents()->where('student_profiles.id', $profile->id)->exists()) {
                return true;
            }

            // Check enrolled course
            if ($project->course_id) {
                return $profile->enrollments()->where('course_id', $project->course_id)->allowsCourseAccess()->exists();
            }

            // Global standalone
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function update(User $user, PracticalProject $project): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function delete(User $user, PracticalProject $project): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function submit(User $user, PracticalProject $project): bool
    {
        if (! $user->is_active || ! $user->hasRole('student')) {
            return false;
        }

        return $this->view($user, $project) && $project->isPublished() && $project->isAvailable();
    }

    public function evaluate(User $user, ProjectSubmission $submission): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ((string) $user->getKey() === (string) $submission->user_id) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            $course = $submission->project?->course;

            return $course && $course->trainers()->where('users.id', $user->getKey())->exists();
        }

        return false;
    }
}
