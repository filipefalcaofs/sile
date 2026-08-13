<?php

namespace App\Services\Ai;

use App\Jobs\Ai\ResumoProcessoJob;
use App\Models\ViabilityRequest;

/**
 * Camada de serviço do resumo do processo por IA (HU-117). Degradação honesta:
 * sem o toggle features.ia_resumo ligado E um provedor de TEXTO ativo, NÃO
 * despacha o job, NÃO chama o provedor e NÃO simula (anti-fachada). O resultado
 * é um RESUMO que apoia a leitura do analista na ficha de análise — sempre
 * SUGESTÃO revisável, nunca decisão nem afirmação de desfecho (AI-SPEC Failure
 * Mode #1). O RunAiAgentJob ainda re-checa o portão no handle (corrida entre
 * enfileirar e processar).
 */
class ResumoProcessoService
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Despacha o resumo do processo como SUGESTÃO revisável.
     *
     * @return bool true se a função estava disponível e o job foi despachado;
     *              false quando indisponível (toggle off ou sem provedor de texto).
     */
    public function processar(ViabilityRequest $processo, ?int $userId = null): bool
    {
        if (! $this->gate->available('resumo', 'text')) {
            return false;
        }

        ResumoProcessoJob::dispatch($processo->id, $userId);

        return true;
    }
}
