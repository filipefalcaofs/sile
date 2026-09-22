<?php

namespace Database\Factories;

use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LouosQuadro11CondicaoVia>
 */
class LouosQuadro11CondicaoViaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_version_id' => RuleVersion::factory(),
            'classe_via' => 'via_local',
            'grupo_uso' => 'nR1',
            'condicoes' => ['recuo_frontal_m' => 5],
            'base_legal' => 'Quadro 11 da Lei nº 9.148/2016',
            'observacao' => null,
        ];
    }
}
