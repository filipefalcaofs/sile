<?php

namespace App\Services\Ai;

use App\Jobs\Ai\ClassificacaoDocumentoJob;
use App\Models\ViabilityRequestDocument;

/**
 * Camada de serviço da classificação documental por IA (HU-113). Degradação
 * honesta: sem o toggle features.ia_classificacao ligado E um provedor de visão
 * ativo, NÃO despacha o job, NÃO chama o provedor e NÃO simula (anti-fachada). O
 * confronto categoria × exigência é levado ao job (que conhece a exigência do
 * documento) e registrado como ALERTA na sugestão — nunca rejeita o protocolo.
 */
class ClassificacaoDocumentoService
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Despacha a classificação do documento como SUGESTÃO revisável.
     *
     * @return bool true se a função estava disponível e o job foi despachado;
     *              false quando indisponível (toggle off ou sem provedor de visão).
     */
    public function processar(ViabilityRequestDocument $documento, ?int $userId = null): bool
    {
        if (! $this->gate->available('classificacao', 'vision')) {
            return false;
        }

        ClassificacaoDocumentoJob::dispatch(
            $documento->id,
            $documento->viability_request_id,
            $userId,
        );

        return true;
    }
}
