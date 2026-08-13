<?php

namespace App\Enums;

/**
 * Destino do encaminhamento de uma viabilidade locacional (HU-049/HU-050): o
 * fluxo expresso (deferimento/indeferimento sem análise humana, quando a lei
 * permite) ou a análise técnica. NÃO existe "semi-expresso" como fluxo —
 * semi-expresso é a CATEGORIA de um gatilho (TipoGatilho) que derruba o
 * encaminhamento para 'analise'; o destino final é sempre expresso ou analise.
 */
enum Fluxo: string
{
    case Expresso = 'expresso';
    case Analise = 'analise';

    public function label(): string
    {
        return match ($this) {
            self::Expresso => 'Fluxo expresso',
            self::Analise => 'Análise técnica',
        };
    }
}
