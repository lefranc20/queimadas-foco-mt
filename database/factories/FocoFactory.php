<?php

namespace Database\Factories;

use App\Models\Foco;
use App\Models\Municipio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Foco>
 */
class FocoFactory extends Factory
{
    public function definition(): array
    {
        $dataHora = fake()->dateTimeBetween('-60 days', 'now', 'UTC');

        return [
            'id' => fake()->uuid(),
            // retângulo aproximado de Mato Grosso
            'lat' => fake()->randomFloat(6, -17.9, -7.4),
            'lon' => fake()->randomFloat(6, -61.6, -50.3),
            'data_hora_gmt' => $dataHora->format('Y-m-d H:i:s'),
            'data' => $dataHora->format('Y-m-d'),
            'satelite' => fake()->randomElement(['AQUA_M-T', 'GOES-19', 'NOAA-20', 'NPP-375']),
            'municipio_id' => Municipio::factory(),
            'bioma' => fake()->randomElement(['Amazônia', 'Cerrado', 'Pantanal']),
            'numero_dias_sem_chuva' => fake()->numberBetween(0, 120),
            'precipitacao' => 0.0,
            'risco_fogo' => fake()->randomFloat(2, 0, 1),
            'frp' => fake()->randomFloat(1, 1, 500),
        ];
    }

    public function em(string $data): static
    {
        return $this->state(fn () => ['data' => $data, 'data_hora_gmt' => "{$data} 17:30:00"]);
    }
}
