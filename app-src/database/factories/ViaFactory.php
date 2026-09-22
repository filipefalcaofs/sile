<?php

namespace Database\Factories;

use App\Models\Via;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Via>
 */
class ViaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('V? ##'),
            'nome' => fake()->words(3, true),
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(['ativo' => false]);
    }
}
