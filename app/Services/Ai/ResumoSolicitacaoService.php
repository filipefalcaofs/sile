<?php

namespace App\Services\Ai;

use App\Jobs\Ai\ResumoSolicitacaoJob;
use App\Models\ViabilityRequest;

/**
 * Camada de serviço do resumo da solicitação por IA (HU-116), pré-protocolo.
 * Degradação honesta: sem o toggle features.ia_resumo ligado E um provedor de
 * TEXTO ativo, NÃO despacha o job, NÃO chama o provedor e NÃO simula
 * (anti-fachada). O resultado é um RESUMO de CONFERÊNCIA que apoia o cidadão a
 * revisar os dados declarados antes de protocolar — sempre SUGESTÃO revisável,
 * nunca decisão nem afirmação de desfecho (AI-SPEC Failure Mode #1). O toggle é
 * COMPARTILHADO com o resumo do processo (HU-117); o RunAiAgentJob ainda re-checa
 * o portão no handle (corrida entre enfileirar e processar).
 */
class ResumoSolicitacaoService
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Despacha o resumo da solicitação como SUGESTÃO revisável de conferência.
     *
     * @return bool true se a função estava disponível e o job foi despachado;
     *              false quando indisponível (toggle off ou sem provedor de texto).
     */
    public function processar(ViabilityRequest $solicitacao, ?int $userId = null): bool
    {
        if (! $this->gate->available('resumo', 'text')) {
            return false;
        }

        ResumoSolicitacaoJob::dispatch($solicitacao->id, $userId);

        return true;
    }
}
