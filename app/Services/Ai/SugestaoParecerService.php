<?php

namespace App\Services\Ai;

use App\Jobs\Ai\SugestaoParecerJob;
use App\Models\ViabilityRequest;

/**
 * Camada de serviço da sugestão de minuta de parecer por IA (HU-118) — Failure
 * Mode #1. Degradação honesta dupla: (1) sem o toggle features.ia_parecer ligado
 * E um provedor de TEXTO ativo; ou (2) sem a pré-análise do motor (engine_snapshot)
 * na ficha — em qualquer dos casos NÃO despacha o job, NÃO chama o provedor e NÃO
 * simula. Sem motor não há fundamentação REAL a citar: a função escala ao humano,
 * que redige o parecer manualmente — jamais inventa quadro/artigo para "destravar".
 * A minuta resultante é SEMPRE sugestão revisável; nunca decisão (o job só cria a
 * AiSuggestion, não toca AnalysisRecord/ViabilityDecision). O RunAiAgentJob ainda
 * re-checa o portão no handle (corrida entre enfileirar e processar).
 */
class SugestaoParecerService
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Despacha a sugestão de minuta de parecer como SUGESTÃO revisável.
     *
     * @return bool true se a função estava disponível (toggle + provedor) E havia
     *              fundamentação do motor, com o job despachado; false quando
     *              indisponível — caso em que o analista redige manualmente.
     */
    public function processar(ViabilityRequest $processo, ?int $userId = null): bool
    {
        if (! $this->gate->available('parecer', 'text')) {
            return false;
        }

        if (! $this->temFundamentacaoDoMotor($processo)) {
            return false;
        }

        SugestaoParecerJob::dispatch($processo->id, $userId);

        return true;
    }

    /**
     * Há fundamentação REAL do motor quando a revisão vigente da ficha foi
     * pré-analisada (engine_available) e tem um engine_snapshot não vazio. Sem
     * isso, a minuta não teria base legal rastreável — e não é sugerida.
     */
    private function temFundamentacaoDoMotor(ViabilityRequest $processo): bool
    {
        $ficha = $processo->currentAnalysisRecord;

        return $ficha !== null
            && $ficha->engine_available === true
            && is_array($ficha->engine_snapshot)
            && $ficha->engine_snapshot !== [];
    }
}
