<?php

namespace Database\Factories;

use App\Domain\Academies\Models\Academy;
use App\Domain\Courses\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'academy_id' => Academy::factory(),
            'category_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'description' => fake()->paragraph(),
            'difficulty' => fake()->randomElement(Course::DIFFICULTIES),
            'price' => 0.00,
            'currency' => 'USD',
            'is_free' => true,
            'visibility' => 'public',
            'enrollment_limit' => null,
            'thumbnail_path' => null,
            'duration_months' => fake()->numberBetween(1, 12),
            'is_sequential' => false,
            'status' => 'Published',
            'published_at' => now(),
        ];
    }
}
