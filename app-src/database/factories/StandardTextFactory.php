<?php

namespace Database\Factories;

use App\Models\StandardText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StandardText>
 *
 * Default = texto-padrão ATIVO na versão 1 (HU-085). O state inativo() retira
 * da biblioteca sem excluir.
 */
class StandardTextFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(['deferimento', 'indeferimento', 'condicionante', 'pendencia']),
            'content' => fake()->paragraph(),
            'active' => true,
            'version' => 1,
        ];
    }

    /**
     * Texto inativo — fora da biblioteca disponível ao analista.
     */
    public function inativo(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
