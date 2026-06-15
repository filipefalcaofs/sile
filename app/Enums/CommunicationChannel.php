<?php

namespace App\Enums;

/**
 * Canal de entrega de uma comunicação de PROCESSO (ledger communications).
 * Email e InApp (canal database nativo) são reais; Whatsapp entra atrás de
 * toggle off + contrato indisponível (degrada honesto — Wave 2/3).
 */
enum CommunicationChannel: string
{
    case Email = 'email';
    case InApp = 'in_app';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::InApp => 'No sistema',
            self::Whatsapp => 'WhatsApp',
        };
    }
}
