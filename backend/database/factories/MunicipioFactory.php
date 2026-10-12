<?php

namespace Database\Factories;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Municipio>
 */
class MunicipioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(5190000, 5199999),
            'nome' => fake()->unique()->city(),
        ];
    }
}
