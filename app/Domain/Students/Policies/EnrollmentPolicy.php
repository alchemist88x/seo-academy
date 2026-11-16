<?php

namespace App\Domain\Students\Policies;

use App\Domain\Students\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) ($user->is_active && $user->hasRole('admin'));
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        $studentProfile = $enrollment->studentProfile ?? $enrollment->studentProfile()->first();

        return $user->hasRole('student')
            && $studentProfile !== null
            && (string) $studentProfile->user_id === (string) $user->getKey();
    }

    public function create(User $user): bool
    {
        return (bool) ($user->is_active && $user->hasRole('admin'));
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return (bool) ($user->is_active && $user->hasRole('admin'));
    }

    /**
     * Determine whether the user can delete the enrollment.
     * Enrollment history is immutable and must not be deleted. Lifecycle state
     * must be managed via cancellation, suspension, expiry, or completion.
     */
    public function delete(User $user, Enrollment $enrollment): bool
    {
        return false;
    }
}
