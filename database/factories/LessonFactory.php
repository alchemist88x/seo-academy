<?php

namespace Database\Factories;

use App\Domain\Courses\Models\Module;
use App\Domain\Lessons\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'display_order' => fake()->numberBetween(1, 10),
            'video_path' => null,
            'thumbnail_path' => null,
            'status' => 'Active',
        ];
    }
}
