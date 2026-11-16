<?php

namespace App\Domain\Academies\Policies;

use App\Domain\Academies\Models\Academy;
use App\Models\User;

class AcademyPolicy
{
    public function view(User $user, Academy $academy): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function update(User $user, Academy $academy): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function manageSettings(User $user, Academy $academy): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function manageDomain(User $user, Academy $academy): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }
}
