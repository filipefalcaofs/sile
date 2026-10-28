<?php

namespace App\Services\Vistoria;

use App\Models\User;
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

    public static function analistaForaDoSetor(User $analista): self
    {
        return new self("{$analista->name} não faz parte do setor que recebe o processo ou não pode analisar processos.");
    }

    /**
     * Processo fora da vistoria. Com encaminhamento registrado é o processo que
     * já voltou à Caixa do Setor; sem ele, nunca foi encaminhado à vistoria.
     */
    public static function processoForaDeVistoriado(ViabilityRequest $processo, bool $jaDevolvido): self
    {
        $atual = $processo->analysis_status?->label() ?? 'não iniciado';

        if ($jaDevolvido) {
            return new self("O processo #{$processo->id} já foi devolvido à Caixa do Setor e está em \"{$atual}\": não há o que encaminhar.");
        }

        return new self("O processo #{$processo->id} está em \"{$atual}\" e não foi encaminhado à vistoria, então não há o que devolver à Caixa do Setor.");
    }
}
