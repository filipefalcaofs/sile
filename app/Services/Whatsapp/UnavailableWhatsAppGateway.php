<?php

namespace App\Services\Whatsapp;

/**
 * Gateway INDISPONÍVEL do WhatsApp: o provedor real (API comercial) é pendência da
 * Fase 13. Sem provedor/credencial, NUNCA transmitimos nem simulamos — degradação
 * honesta (entrega-funcional), jamais adaptador falso. A Fase 13 troca SÓ este
 * binding pelo adaptador HTTP conveniado, sem tocar o WhatsAppChannel.
 */
class UnavailableWhatsAppGateway implements WhatsAppGateway
{
    public function send(WhatsAppMessage $message): void
    {
        // Provedor pendente (Fase 13): degrada honestamente, nunca finge envio.
        throw new WhatsAppUnavailableException;
    }
}
