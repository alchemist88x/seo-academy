<?php

namespace App\Domain\Students\Policies;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;

class StudentProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(['admin', 'trainer']);
    }

    public function view(User $user, StudentProfile $studentProfile): bool
    {
        if (! $user->is_active || $studentProfile->academy_id === null) {
            return false;
        }

        if ($studentProfile->user_id === $user->getKey() && $user->belongsToAcademy($studentProfile->academy_id)) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'trainer']);
    }

    public function create(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function update(User $user, StudentProfile $studentProfile): bool
    {
        return $user->is_active && $user->hasAnyRole(['admin', 'trainer']);
    }

    public function delete(User $user, StudentProfile $studentProfile): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function export(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function import(User $user, ?Academy $academy = null): bool
    {
        return $this->export($user, $academy);
    }
}
