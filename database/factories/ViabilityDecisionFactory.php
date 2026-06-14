<?php

namespace Database\Factories;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViabilityDecision>
 */
class ViabilityDecisionFactory extends Factory
{
    /**
     * Default = deferimento expresso do sistema (decided_by_user_id null), com
     * número TVL e os jsonb plausíveis (veredito por CNAE, versões de regras e
     * fundamentação legal). Os states cobrem o indeferimento e o indeferimento
     * por prazo BAP vencido (HU-134).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) now()->year;

        return [
            'viability_request_id' => ViabilityRequest::factory()->protocoled(),
            'flow' => 'expresso',
            'outcome' => DecisionOutcome::Deferida,
            'consolidated_result' => 'permitido',
            'tvl_product_number' => 'TVL-'.$year.'-000001',
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'resultado' => 'permitido',
                    'fluxo' => 'expresso',
                ],
            ],
            'rules_versions' => [
                'territorio' => '2026.1',
                'louos' => '2026.1',
                'risco' => '2026.1',
            ],
            'fundamentacao' => [
                'louos' => 'Lei nº 9.148/2016',
                'risco' => 'Decreto nº 32.636/2020',
            ],
            'reason' => null,
            'decided_by_user_id' => null,
            'decided_at' => now(),
        ];
    }

    /**
     * Indeferimento (RN-009): algum CNAE não permitido derruba o processo; sem TVL.
     */
    public function indeferida(): static
    {
        return $this->state(fn () => [
            'outcome' => DecisionOutcome::Indeferida,
            'consolidated_result' => 'nao_permitido',
            'tvl_product_number' => null,
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'resultado' => 'nao_permitido',
                    'fluxo' => 'expresso',
                ],
            ],
        ]);
    }

    /**
     * Indeferimento por prazo BAP vencido sem atuação na Junta (HU-134 dormente).
     */
    public function semAtuacaoBap(): static
    {
        return $this->indeferida()->state(fn () => [
            'reason' => 'indeferido sem atuação',
        ]);
    }
}
