<?php

namespace Database\Factories;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'academy_id' => Academy::factory(),
            'student_code' => 'STU-'.strtoupper(Str::random(6)),
            'batch' => 'Batch '.fake()->numberBetween(1, 5),
            'enrolled_at' => now()->toDateString(),
            'level' => 'Beginner',
            'active' => true,
        ];
    }
}
