<?php

namespace App\Domain\Academies\Policies;

use App\Domain\Academies\Models\Academy;
use App\Domain\Academies\Models\AcademyUser;
use App\Models\User;

class AcademyUserPolicy
{
    public function viewAny(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function view(User $user, AcademyUser $academyUser): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function create(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function update(User $user, AcademyUser $academyUser): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function assignRole(User $user, AcademyUser $academyUser): bool
    {
        return $this->update($user, $academyUser);
    }

    public function delete(User $user, AcademyUser $academyUser): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }
}
