<?php

namespace App\Domain\Courses\Policies;

use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use App\Models\User;

class ModulePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Module $module): bool
    {
        $course = $module->course;
        $academyId = $module->academy_id ?? $course?->academy_id;

        if (! $user->is_active || $academyId === null || $course === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $course);
        }

        if (! $user->belongsToAcademy($academyId)) {
            return false;
        }

        return $course->isPublished()
            && in_array($course->visibility, ['public', 'academy_only'], true);
    }

    public function create(User $user, Course $course): bool
    {
        if (! $user->is_active || $course->academy_id === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $course);
        }

        return false;
    }

    public function update(User $user, Module $module): bool
    {
        $course = $module->course;
        $academyId = $module->academy_id ?? $course?->academy_id;

        if (! $user->is_active || $academyId === null || $course === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('trainer')) {
            return $this->isAssignedTrainer($user, $course);
        }

        return false;
    }

    public function delete(User $user, Module $module): bool
    {
        return $this->update($user, $module);
    }

    private function isAssignedTrainer(User $user, Course $course): bool
    {
        return $course->trainers()
            ->where('users.id', $user->getKey())
            ->exists();
    }
}
