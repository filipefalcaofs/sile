<?php

namespace App\Enums;

/**
 * Estado da ficha de vistoria. Nasce EmPreenchimento (rascunho editável) e
 * vira Concluida — imutável após a conclusão: o parecer registrado na
 * conclusão é a peça oficial da vistoria.
 */
enum InspectionStatus: string
{
    case EmPreenchimento = 'em_preenchimento';
    case Concluida = 'concluida';

    public function label(): string
    {
        return match ($this) {
            self::EmPreenchimento => 'Em preenchimento',
            self::Concluida => 'Concluída',
        };
    }

    public function isConcluida(): bool
    {
        return $this === self::Concluida;
    }
}
