<?php

namespace App\Services\Analise;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;

/**
 * Retorno imutável da decisão técnica humana (AnaliseTecnicaDecisionService::decide,
 * 10-10): o desfecho (deferida/indeferida), a ViabilityDecision criada (flow
 * 'analise_tecnica') e se o ResultadoEmitido foi disparado nesta chamada.
 *
 * `emitted` é false na corrida idempotente que reaproveita a decisão já gravada
 * (a unique viability_request_id da Fase 9 barra a 2ª) — não redispara o evento.
 * É o insumo do controller/comando que conclui o processo (endpoint em 10-15) e
 * do TVL PDF (10-13): nunca um resultado simulado, só o que foi decidido de fato.
 */
final readonly class AnaliseDecisionResult
{
    public function __construct(
        public DecisionOutcome $outcome,
        public ViabilityDecision $decision,
        public bool $emitted,
    ) {}
}
