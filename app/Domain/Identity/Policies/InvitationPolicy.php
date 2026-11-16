<?php

namespace App\Domain\Identity\Policies;

use App\Domain\Academies\Models\Academy;
use App\Domain\Identity\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function viewAny(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function view(User $user, Invitation $invitation): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function create(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->revoke($user, $invitation);
    }
}
