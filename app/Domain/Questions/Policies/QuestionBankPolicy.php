<?php

namespace App\Domain\Questions\Policies;

use App\Domain\Academies\Models\Academy;
use App\Domain\Questions\Models\QuestionBank;
use App\Models\User;

class QuestionBankPolicy
{
    public function viewAny(User $user, ?Academy $academy = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        // Support staff and students are denied access
        if ($user->hasRole('support_staff') || $user->hasRole('student')) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('trainer');
    }

    public function view(User $user, QuestionBank $bank, ?Academy $academy = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if (! $this->validateTenant($user, $bank, $academy)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return true;
        }

        return false;
    }

    public function create(User $user, ?Academy $academy = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('support_staff') || $user->hasRole('student')) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('trainer');
    }

    public function update(User $user, QuestionBank $bank, ?Academy $academy = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if (! $this->validateTenant($user, $bank, $academy)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            // Trainers can update banks they created
            return (string) $bank->created_by === (string) $user->id;
        }

        return false;
    }

    public function delete(User $user, QuestionBank $bank, ?Academy $academy = null): bool
    {
        return $this->update($user, $bank, $academy);
    }

    public function manageQuestions(User $user, QuestionBank $bank, ?Academy $academy = null): bool
    {
        return $this->update($user, $bank, $academy);
    }

    private function validateTenant(User $user, QuestionBank $bank, ?Academy $academy = null): bool
    {
        if ($academy && (string) $bank->academy_id !== (string) $academy->id) {
            return false;
        }

        if ($bank->academy_id && ! $user->belongsToAcademy($bank->academy_id)) {
            return false;
        }

        return true;
    }
}
