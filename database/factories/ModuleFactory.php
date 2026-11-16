<?php

namespace Database\Factories;

use App\Domain\Courses\Models\Course;
use App\Domain\Courses\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'display_order' => fake()->numberBetween(1, 10),
            'status' => 'Active',
        ];
    }
}
