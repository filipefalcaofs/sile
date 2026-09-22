<?php

namespace App\Enums;

/**
 * Situação de vigência de uma camada (HU-036 RN-004). pendente_fonte marca a
 * camada cuja fonte pública ainda não existe (zona/lote): registrada e
 * comunicada, sem features — nunca simulada.
 */
enum GeoLayerStatus: string
{
    case Vigente = 'vigente';
    case Substituida = 'substituida';
    case PendenteFonte = 'pendente_fonte';

    public function label(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Substituida => 'Substituída',
            self::PendenteFonte => 'Pendente de fonte',
        };
    }
}
