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
            // Shapes idênticos aos gravados pelo FluxoExpressoService (RN-005/009):
            // per_cnae completo, rules_versions ANINHADO por domínio e fundamentacao
            // como LISTA — para os testes exercitarem o contrato real de consumo.
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'tendencia' => 'permitido',
                    'tendencia_label' => 'Permitido',
                    'fluxo' => 'expresso',
                    'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 7'],
                ],
            ],
            'rules_versions' => [
                'territorio' => ['camadas' => '2026.1'],
                'louos' => ['quadro7' => '2026.1', 'quadro10' => '2026.1'],
                'risco' => ['decreto' => '2026.1'],
            ],
            'fundamentacao' => [
                'Lei nº 9.148/2016 (LOUOS) — Quadro 7',
                'Decreto nº 32.636/2020 — classificação de risco',
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
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'tendencia' => 'nao_permitido',
                    'tendencia_label' => 'Não permitido',
                    'fluxo' => 'expresso',
                    'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 10'],
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
