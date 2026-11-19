<?php

namespace App\Domain\Certificates\Policies;

use App\Domain\Certificates\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function view(User $user, Certificate $certificate): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('student')) {
            return $certificate->user_id === $user->getKey();
        }

        return false;
    }

    public function revoke(User $user, Certificate $certificate): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function reinstate(User $user, Certificate $certificate): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function audit(User $user, Certificate $certificate): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function delete(User $user, Certificate $certificate): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }
}
