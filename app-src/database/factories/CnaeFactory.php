<?php

namespace Database\Factories;

use App\Models\Cnae;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cnae>
 */
class CnaeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('#######'),
            'description' => fake()->sentence(3),
            'section_code' => 'A',
            'section_description' => 'Seção de teste',
            'division_code' => '01',
            'division_description' => 'Divisão de teste',
            'group_code' => '01.1',
            'group_description' => 'Grupo de teste',
            'class_code' => '01.11-3',
            'class_description' => 'Classe de teste',
            'active' => true,
            'exige_rt' => false,
            'exige_rt_se_alto' => false,
            'exige_fator_multiplicador' => false,
            'exige_detalhamento_multiplicador' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
