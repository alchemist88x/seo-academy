<?php

namespace Database\Factories;

use App\Domain\Academies\Models\Academy;
use App\Domain\Students\Models\ProficiencyLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProficiencyLevel>
 */
class ProficiencyLevelFactory extends Factory
{
    protected $model = ProficiencyLevel::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'academy_id' => Academy::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name, '_'),
            'description' => fake()->sentence(),
            'display_order' => fake()->numberBetween(1, 10),
            'color' => fake()->randomElement(['blue', 'amber', 'purple', 'emerald', 'cyan']),
            'is_active' => true,
        ];
    }
}
