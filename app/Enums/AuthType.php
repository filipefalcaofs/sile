<?php

namespace App\Enums;

/**
 * Mecanismo de autenticação da conta: AD valida a credencial do console na
 * API institucional da SEDUR; Local valida contra o hash em users.password;
 * PortalServicos é a conta de cidadão criada na entrada pelo Portal de
 * Serviços da SEDUR (HU-142), sem senha utilizável no Viabiliza.
 * O vínculo AD é determinístico (ad_username) — nunca inferido por e-mail/CPF.
 */
enum AuthType: string
{
    case Ad = 'ad';
    case Local = 'local';
    case PortalServicos = 'portal_servicos';

    public function label(): string
    {
        return match ($this) {
            self::Ad => 'Active Directory (rede corporativa)',
            self::Local => 'Local (senha do sistema)',
            self::PortalServicos => 'Portal de Serviços da SEDUR',
        };
    }
}
