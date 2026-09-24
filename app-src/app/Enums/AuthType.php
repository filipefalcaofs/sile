<?php

namespace App\Enums;

/**
 * Mecanismo de autenticação da conta no console: AD valida a credencial na
 * API institucional da SEDUR; Local valida contra o hash em users.password.
 * O vínculo AD é determinístico (ad_username) — nunca inferido por e-mail/CPF.
 */
enum AuthType: string
{
    case Ad = 'ad';
    case Local = 'local';

    public function label(): string
    {
        return match ($this) {
            self::Ad => 'Active Directory (rede corporativa)',
            self::Local => 'Local (senha do sistema)',
        };
    }
}
