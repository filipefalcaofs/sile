<?php

namespace Database\Factories;

use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LouosQuadro7Faixa>
 */
class LouosQuadro7FaixaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_version_id' => RuleVersion::factory(),
            'cnae_code' => $this->faker->numerify('#######'),
            'grupo' => 'nR1',
            'subgrupo' => 'nR1-01',
            'area_min' => 0,
            'area_max' => 350,
            'observacao' => null,
        ];
    }
}
