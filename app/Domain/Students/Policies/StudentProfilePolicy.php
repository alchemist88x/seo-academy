<?php

namespace App\Domain\Students\Policies;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class StudentProfilePolicy
{
    /**
     * Determine whether the user can browse the complete student administration index.
     * Strictly restricted to administrators.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view an individual student profile.
     * - Admin: full view access.
     * - Student: view own profile only.
     * - Trainer: only view students enrolled in courses assigned to this trainer.
     * - Support Staff: view student profile for authorized support investigations.
     */
    public function view(User $user, StudentProfile $studentProfile): bool
    {
        if (! $user->is_active || $studentProfile->academy_id === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        // Student can view their own profile
        if ($studentProfile->user_id === $user->getKey()) {
            return $user->belongsToAcademy($studentProfile->academy_id);
        }

        // Trainer can only view students enrolled in courses they train
        if ($user->hasRole('trainer')) {
            return $studentProfile->enrollments()
                ->whereHas('course.trainers', function (Builder $q) use ($user): void {
                    $q->where('users.id', $user->getKey());
                })
                ->exists();
        }

        // Support staff can view student details for authorized support work
        if ($user->hasRole('support_staff')) {
            return $user->belongsToAcademy($studentProfile->academy_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create a student profile.
     * Strictly restricted to administrators.
     */
    public function create(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can administratively update a student profile.
     * - Admin: full update access.
     * - Student: can update only own permitted profile attributes (via student self-profile).
     */
    public function update(User $user, StudentProfile $studentProfile): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete a student profile.
     */
    public function delete(User $user, StudentProfile $studentProfile): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can export student data.
     */
    public function export(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can import student data via CSV.
     */
    public function import(User $user, ?Academy $academy = null): bool
    {
        return $this->export($user, $academy);
    }
}
