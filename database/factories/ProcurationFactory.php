<?php

namespace Database\Factories;

use App\Models\Cidadao;
use App\Models\Procuration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procuration>
 */
class ProcurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grantor_cidadao_id' => Cidadao::factory(),
            'attorney_cidadao_id' => Cidadao::factory(),
            'starts_at' => now(),
            'expires_at' => null,
        ];
    }

    /**
     * Procuração revogada pelo próprio outorgante.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }

    /**
     * Procuração com vigência já encerrada.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);
    }
}
