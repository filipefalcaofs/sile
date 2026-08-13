<?php

namespace App\Enums;

/**
 * Papel do usuário NO CONTEXTO da empresa (não confundir com papel spatie).
 */
enum CompanyLinkRole: string
{
    case Responsavel = 'responsavel';
    case Procurador = 'procurador';

    public function label(): string
    {
        return match ($this) {
            self::Responsavel => 'Responsável',
            self::Procurador => 'Procurador',
        };
    }
}
