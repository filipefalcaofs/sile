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

    public static function vistoriaNaoConcluida(ViabilityRequest $processo): self
    {
        return new self("O processo #{$processo->id} só volta à Caixa do Setor depois que a ficha de vistoria é concluída.");
    }

    /**
     * Ficha concluída, mas o processo não está em Vistoriado. Com encaminhamento
     * registrado é o processo que já saiu da vistoria (voltou à Caixa do Setor e
     * a análise seguiu); sem ele, a ficha foi concluída sem o processo ter sido
     * encaminhado à vistoria.
     */
    public static function processoForaDeVistoriado(ViabilityRequest $processo, bool $jaDevolvido): self
    {
        $atual = $processo->analysis_status?->label() ?? 'não iniciado';

        if ($jaDevolvido) {
            return new self("O processo #{$processo->id} já foi devolvido à Caixa do Setor e está em \"{$atual}\": não há o que encaminhar.");
        }

        return new self("O processo #{$processo->id} está em \"{$atual}\" e não em \"Vistoriado\": ele não foi encaminhado à vistoria antes de a ficha ser concluída, então não há o que devolver à Caixa do Setor.");
    }
}
