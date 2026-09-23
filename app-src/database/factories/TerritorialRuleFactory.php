<?php

namespace Database\Factories;

use App\Enums\TerritorialRuleEffect;
use App\Enums\TerritorialRuleType;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TerritorialRule>
 */
class TerritorialRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_version_id' => RuleVersion::factory(),
            'codigo' => 'RT-'.fake()->unique()->numerify('##'),
            'titulo' => fake()->sentence(4),
            'tipo' => TerritorialRuleType::LogradouroCnae,
            'efeito' => TerritorialRuleEffect::Indeferir,
            'cod_log' => null,
            'logradouro_nome' => null,
            'camada' => null,
            'camada_filtro' => null,
            'zona_alvo' => null,
            'motivo_analise' => null,
            'decision_text_id' => null,
            'base_legal' => null,
            'ativo' => true,
        ];
    }
}
