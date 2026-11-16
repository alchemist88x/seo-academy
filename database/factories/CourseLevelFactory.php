<?php

namespace Database\Factories;

use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\CourseLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLevel>
 */
class CourseLevelFactory extends Factory
{
    protected $model = CourseLevel::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => fake()->randomElement(['Beginner', 'Intermediate', 'Advanced']),
            'display_order' => fake()->numberBetween(1, 3),
            'description' => fake()->sentence(),
        ];
    }
}
