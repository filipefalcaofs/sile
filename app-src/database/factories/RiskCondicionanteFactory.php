<?php

namespace Database\Factories;

use App\Enums\RiscoSanitario;
use App\Enums\TipoRespostaCondicionante;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskCondicionante>
 */
class RiskCondicionanteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_version_id' => RuleVersion::factory(),
            'cnae_code' => $this->faker->numerify('#######'),
            'pergunta' => $this->faker->sentence().'?',
            'tipo_resposta' => TipoRespostaCondicionante::BooleanoSimNao,
            'regra_reclassificacao' => [
                'resposta_gatilho' => true,
                'reclassifica_para' => RiscoSanitario::Alto->value,
                'fundamento' => $this->faker->sentence(),
            ],
            'texto_parecer' => null,
        ];
    }
}
