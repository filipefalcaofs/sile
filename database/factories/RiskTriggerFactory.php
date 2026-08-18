<?php

namespace Database\Factories;

use App\Enums\TipoGatilho;
use App\Models\RiskTrigger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskTrigger>
 */
class RiskTriggerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => $this->faker->randomElement(TipoGatilho::cases()),
            'titulo' => $this->faker->sentence(3),
            'motivo' => $this->faker->sentence(10),
            'ativo' => true,
            'categoria' => 'semi_expresso',
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn (array $attributes): array => ['ativo' => false]);
    }
}
