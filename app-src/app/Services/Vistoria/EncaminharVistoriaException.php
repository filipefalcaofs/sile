<?php

namespace App\Services\Vistoria;

use App\Models\ViabilityRequest;
use DomainException;

/**
 * Precondições do encaminhamento à vistoria: o processo precisa estar em
 * análise (status canônico) e num ponto do eixo operacional de onde a
 * vistoria é alcançável (a state machine só permite EmAnalise → Vistoriar).
 */
class EncaminharVistoriaException extends DomainException
{
    public static function foraDeAnalise(ViabilityRequest $processo): self
    {
        return new self("O processo #{$processo->id} não está em análise — só é possível encaminhar à vistoria durante a análise técnica.");
    }

    public static function eixoNaoPermite(ViabilityRequest $processo): self
    {
        $atual = $processo->analysis_status?->label() ?? 'não iniciado';

        return new self("O processo #{$processo->id} está em \"{$atual}\" no eixo operacional — a vistoria só pode ser acionada a partir de \"Em análise\".");
    }
}
