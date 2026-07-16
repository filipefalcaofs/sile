<?php

namespace App\Services\Analise;

use App\Models\AnalysisPendency;
use App\Models\ViabilityRequest;
use RuntimeException;

/**
 * Operação do ciclo de pendência não permitida no estado atual (HU-083/084,
 * CA-03): abrir uma pendência exige o processo em em_analise; responder exige a
 * pendência aberta com o processo em em_pendencia. A operação NÃO acontece (nada
 * é gravado/transicionado); o caller (controller do portal/endpoint gestão)
 * traduz em aviso comunicado — nunca silencioso, nunca fingido.
 */
class PendenciaInvalidaException extends RuntimeException
{
    public static function naoAbrivel(ViabilityRequest $request): self
    {
        return new self(
            "Não é possível abrir convite: a solicitação #{$request->id} não está em análise (estado atual: {$request->status->label()}).",
        );
    }

    public static function naoRespondivel(AnalysisPendency $pendency): self
    {
        return new self(
            "Não é possível responder o convite #{$pendency->id}: ele não está aberto ou a solicitação não está em convite.",
        );
    }

    public static function naoCancelavel(AnalysisPendency $pendency): self
    {
        return new self(
            "Não é possível cancelar o convite #{$pendency->id}: ele não está aberto ou a solicitação não está em convite.",
        );
    }
}
