<?php

namespace App\Enums;

/**
 * Veredito consolidado do parecer de viabilidade locacional da LOUOS (HU-044),
 * produzido pelo motor a partir dos Quadros 7/10/11/11A. `Pendente` é a
 * degradação honesta quando uma dimensão necessária está indisponível (ex.:
 * zona pendente SEDUR para o Quadro 10) — o motor NUNCA decide permitido/não
 * permitido sem o dado real; encaminha para análise técnica.
 */
enum ResultadoViabilidade: string
{
    case Permitido = 'permitido';
    case PermitidoComCondicoes = 'permitido_com_condicoes';
    case NaoPermitido = 'nao_permitido';
    case Pendente = 'pendente';

    public function label(): string
    {
        return match ($this) {
            self::Permitido => 'Permitido',
            self::PermitidoComCondicoes => 'Permitido com condições',
            self::NaoPermitido => 'Não permitido',
            self::Pendente => 'Pendente de análise técnica',
        };
    }
}
