<?php

namespace Database\Factories;

use App\Domain\Academies\Models\Academy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Academy>
 */
class AcademyFactory extends Factory
{
    protected $model = Academy::class;

    public function definition(): array
    {
        $name = fake()->company().' Academy';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'subdomain' => null,
            'custom_domain' => null,
            'description' => fake()->paragraph(),
            'settings' => ['timezone' => 'UTC'],
            'is_active' => true,
        ];
    }
}
