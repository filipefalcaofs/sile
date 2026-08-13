<?php

namespace Database\Factories;

use App\Enums\AnalysisRecordStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisRecord>
 *
 * Default = revisão 1 em RASCUNHO pré-analisada pelo motor (engine_available
 * true, engine_snapshot/per_cnae preenchidos), associada a uma solicitação
 * protocolada. Os states cobrem a finalização (revisão imutável) e a degradação
 * honesta quando o motor está indisponível (FA-01).
 */
class AnalysisRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => ViabilityRequest::factory()->protocoled(),
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'analyst_user_id' => null,
            'engine_available' => true,
            // Snapshot do resolver (Fase 9 reusada) — shape plausível.
            'engine_snapshot' => [
                'consolidado' => 'permitido',
                'ponto' => ['lat' => -12.971, 'lng' => -38.511],
                'area_m2' => 120.5,
            ],
            'engine_rules_versions' => [
                'territorio' => ['camadas' => '2026.1'],
                'louos' => ['quadro7' => '2026.1', 'quadro10' => '2026.1'],
                'risco' => ['decreto' => '2026.1'],
            ],
            // per_cnae espelha a ficha SAPS (sugerido×escolhido, grupo de uso,
            // valor TLL, gatilhos, condicionantes).
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'status_sugerido' => 'permitido',
                    'status_escolhido' => 'permitido',
                    'grupo_uso' => 'Comércio varejista',
                    'valor_tll' => null,
                    'gatilhos' => [],
                    'condicionantes' => [],
                ],
            ],
            'conditions' => [],
            'parking' => [
                'vagas_requeridas' => 2,
                'vagas_exigidas' => 2,
                'vistoria' => false,
            ],
            'parecer' => null,
            'finalized_at' => null,
        ];
    }

    /**
     * Ficha finalizada (HU-135 RN-003) — imutável: parecer preenchido e
     * finalized_at marcado, com um analista responsável.
     */
    public function finalizada(): static
    {
        return $this->state(fn () => [
            'status' => AnalysisRecordStatus::Finalizada,
            'analyst_user_id' => User::factory(),
            'parecer' => 'Parecer técnico favorável com fundamentação na LOUOS.',
            'finalized_at' => now(),
        ]);
    }

    /**
     * Motor indisponível (HU-140 FA-01) — ficha NASCE vazia + engine_available
     * false; o analista preenche manualmente (degradação honesta, sem fachada).
     */
    public function semMotor(): static
    {
        return $this->state(fn () => [
            'engine_available' => false,
            'engine_snapshot' => null,
            'engine_rules_versions' => null,
            'per_cnae' => null,
        ]);
    }
}
