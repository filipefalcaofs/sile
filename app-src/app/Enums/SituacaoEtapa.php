<?php

namespace App\Enums;

enum SituacaoEtapa: string
{
    case Ok = 'ok';
    case Indisponivel = 'indisponivel';
    case Definitiva = 'definitiva';
    case Pulada = 'pulada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ok => 'Concluída',
            self::Indisponivel => 'Serviço indisponível',
            self::Definitiva => 'Sem resultado',
            self::Pulada => 'Não aplicável',
        };
    }
}
