<?php

namespace App\Services\Vistoria;

use App\Models\ViabilityRequest;
use DomainException;

/**
 * Precondições do encaminhamento à vistoria: o processo precisa estar em
 * análise (status canônico) e com o eixo operacional ainda aberto
 * (para distribuir, encaminhado, analisar ou em análise). Análise
 * concluída, convite e vistoria já encerrada recusam o handoff.
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

        return new self("O processo #{$processo->id} está em \"{$atual}\" — o encaminhamento à vistoria só vale enquanto a análise ainda está aberta.");
    }
}
