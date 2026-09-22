<?php

namespace App\Enums;

/**
 * Origem de CRIAÇÃO do cadastro empresarial (imutável). Atualização via
 * REDESIM marca redesim_synced_at sem reescrever a origem.
 */
enum CompanySource: string
{
    case Manual = 'manual';
    case Redesim = 'redesim';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Cadastro manual',
            self::Redesim => 'REDESIM',
        };
    }
}
