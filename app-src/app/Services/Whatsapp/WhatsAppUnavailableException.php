<?php

namespace App\Services\Whatsapp;

use RuntimeException;

/**
 * Indisponibilidade do canal WhatsApp: NÃO há provedor/credencial (API comercial)
 * — pendência da Fase 13. O gateway real degrada honestamente, NUNCA simula o
 * envio. A Fase 13 troca o binding pelo adaptador HTTP conveniado, sem tocar o
 * WhatsAppChannel que a consome.
 */
class WhatsAppUnavailableException extends RuntimeException
{
    public function __construct(public readonly ?string $motivo = null)
    {
        parent::__construct(
            'Envio de WhatsApp indisponível: provedor/credencial pendentes (Fase 13).',
        );
    }
}
