<?php

namespace App\Services\Ai;

use App\Jobs\Ai\LeituraDocumentoJob;
use App\Models\ViabilityRequestDocument;

/**
 * Camada de serviço da leitura documental por IA (HU-112 + HU-114). Decide, com
 * degradação honesta, SE a função roda: sem o toggle features.ia_ocr ligado E um
 * provedor de visão ativo, NÃO despacha o job, NÃO chama o provedor e NÃO simula
 * resultado (anti-fachada). O RunAiAgentJob ainda re-checa o portão no handle,
 * cobrindo a corrida entre enfileirar e processar.
 */
class LeituraDocumentoService
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Despacha a leitura por visão do documento como SUGESTÃO revisável.
     *
     * @return bool true se a função estava disponível e o job foi despachado;
     *              false quando indisponível (toggle off ou sem provedor de visão).
     */
    public function processar(ViabilityRequestDocument $documento, ?int $userId = null): bool
    {
        if (! $this->gate->available('ocr', 'vision')) {
            return false;
        }

        LeituraDocumentoJob::dispatch(
            $documento->id,
            $documento->viability_request_id,
            $userId,
        );

        return true;
    }
}
