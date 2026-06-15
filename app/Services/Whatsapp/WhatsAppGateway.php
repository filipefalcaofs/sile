<?php

namespace App\Services\Whatsapp;

/**
 * Contrato de saída do canal WhatsApp (HU-095). O provider concreto é
 * substituível por binding — a Fase 13 liga o adaptador HTTP real (API comercial,
 * credenciais de integrations.whatsapp.*) sem tocar nenhum call site (mesmo padrão
 * de ReginParecerNotifier/SefazViabilidadeGateway).
 *
 * Hoje o binding default é o UnavailableWhatsAppGateway (degrada honesto).
 */
interface WhatsAppGateway
{
    /**
     * Transmite a mensagem ao provedor de WhatsApp. O provider concreto é trocável
     * por binding — a Fase 13 liga o adaptador real sem tocar call sites.
     *
     * @throws WhatsAppUnavailableException quando o provedor/credencial estão pendentes (degrada honesto — Fase 13)
     */
    public function send(WhatsAppMessage $message): void;
}
