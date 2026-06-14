<?php

namespace App\Enums;

/**
 * Situação de vigência de uma versão de regra (HU-019/HU-020/HU-053).
 * Espelha GeoLayerStatus (vigente/substituida) e acrescenta o estado rascunho,
 * que coexiste com a vigente e habilita o sandbox da HU-143 (Fase 5).
 */
enum RuleVersionStatus: string
{
    case Rascunho = 'rascunho';
    case Vigente = 'vigente';
    case Substituida = 'substituida';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Vigente => 'Vigente',
            self::Substituida => 'Substituída',
        };
    }
}
