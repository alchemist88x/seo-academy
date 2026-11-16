<?php

namespace App\Domain\Courses\Policies;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\CourseCategory;
use App\Models\User;

class CourseCategoryPolicy
{
    public function viewAny(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function view(User $user, CourseCategory $category): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function create(User $user, ?Academy $academy = null): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function update(User $user, CourseCategory $category): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }

    public function delete(User $user, CourseCategory $category): bool
    {
        return $user->is_active && $user->hasRole('admin');
    }
}
