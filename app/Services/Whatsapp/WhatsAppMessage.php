<?php

namespace App\Services\Whatsapp;

/**
 * DTO imutável de uma mensagem de WhatsApp (HU-095). O conteúdo (body/meta) é
 * montado pela Notification de processo (toWhatsApp); o destino (to, em E.164) é
 * resolvido pelo WhatsAppChannel a partir do notifiable
 * (User::routeNotificationForWhatsapp) — o canal é a autoridade do "para onde".
 */
class WhatsAppMessage
{
    /**
     * @param  string  $to  Telefone do destinatário em formato E.164 (ex.: +5571999990000).
     * @param  array<string, mixed>  $meta  Metadados opcionais do envio (livre por design).
     */
    public function __construct(
        public readonly string $to,
        public readonly string $body,
        public readonly array $meta = [],
    ) {}
}
