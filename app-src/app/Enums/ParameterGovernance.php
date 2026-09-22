<?php

namespace App\Enums;

/**
 * Classificação de governança do catálogo HU-014. Operacional vale no PUT.
 * Decisório (roteamento/emissão) exige quatro olhos — o valor vigente só
 * muda quando um segundo usuário com manter-parametros aprova a proposta.
 */
enum ParameterGovernance: string
{
    case Operational = 'operational';
    case Decision = 'decision';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operacional',
            self::Decision => 'Decisório',
        };
    }
}
