<?php

namespace Database\Factories;

use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sector>
 */
class SectorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Setor '.ucfirst(fake()->unique()->word()),
            'active' => true,
        ];
    }

    /**
     * Setor ativo (recebe distribuições) — explicita o default.
     */
    public function ativo(): static
    {
        return $this->state(fn () => ['active' => true]);
    }

    /**
     * Setor inativo — não recebe novas distribuições (mas não some com pendência).
     */
    public function inativo(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
