<?php

namespace Database\Factories;

use App\Enums\Quadro10Permissao;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LouosQuadro10Permissao>
 */
class LouosQuadro10PermissaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_version_id' => RuleVersion::factory(),
            'zona' => 'ZPR-1',
            'grupo_uso' => 'nR1',
            'subgrupo' => 'nR1-01',
            'permissao' => Quadro10Permissao::Permitido,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
            'observacao' => null,
        ];
    }
}
