<?php

namespace Database\Factories;

use App\Models\TllValor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TllValor>
 */
class TllValorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo_tll' => fake()->unique()->numerify('#.##'),
            'especificacao' => '',
            'exercicio' => (int) now()->year,
            'valor' => fake()->randomFloat(2, 100, 2000),
            'taxa_servico' => 0,
            'codigo_tll_sefaz' => null,
            'codigo_servico_sefaz' => null,
            'servico_sefaz' => null,
            'active' => true,
        ];
    }
}
