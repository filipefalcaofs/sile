<?php

namespace App\Enums;

/**
 * Origem (auditada) da solicitação de viabilidade. Portal é o caminho do
 * cidadão; Contingencia (HU-148) é o canal de operador — o caminho REAL de
 * operação enquanto o Regin não chega. Regin é GANCHO (Fase 13): existe no
 * enum extensível, mas a origem fica travada até o contrato do integrador.
 */
enum ViabilityRequestOrigin: string
{
    case Portal = 'portal';
    case Contingencia = 'contingencia';
    case Regin = 'regin';

    public function label(): string
    {
        return match ($this) {
            self::Portal => 'Portal do cidadão',
            self::Contingencia => 'Contingência (atendimento interno)',
            self::Regin => 'REDESIM/Regin',
        };
    }
}
