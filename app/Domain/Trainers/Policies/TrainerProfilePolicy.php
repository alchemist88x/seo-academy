<?php

namespace App\Domain\Trainers\Policies;

use App\Domain\Academies\Models\Academy;
use App\Domain\Trainers\Models\TrainerProfile;
use App\Models\User;

class TrainerProfilePolicy
{
    public function viewAny(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasAnyRole(['admin', 'trainer']);
    }

    public function view(User $user, TrainerProfile $trainerProfile): bool
    {
        if (! $user->is_active || $trainerProfile->academy_id === null) {
            return false;
        }

        if ($trainerProfile->user_id === $user->getKey() && $user->belongsToAcademy($trainerProfile->academy_id)) {
            return true;
        }

        return $user->hasRole('admin');
    }

    public function create(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function update(User $user, TrainerProfile $trainerProfile): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function delete(User $user, TrainerProfile $trainerProfile): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function restore(User $user, TrainerProfile $trainerProfile): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }
}
