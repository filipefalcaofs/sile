<?php

namespace App\Notifications\Channels;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Models\Communication;
use App\Notifications\Contracts\ProcessNotification;
use App\Services\Whatsapp\WhatsAppGateway;
use App\Services\Whatsapp\WhatsAppMessage;
use App\Services\Whatsapp\WhatsAppUnavailableException;
use App\Support\Audit\AuditService;
use Illuminate\Notifications\Notification;

/**
 * Canal customizado de WhatsApp (HU-095) — DONO ÚNICO da linha communications do
 * canal whatsapp: ninguém mais marca enviado/bloqueado nessa linha. Espelha
 * ComunicarResultadoRegin (o efeito de integração que audita o bloqueio):
 *
 * - resolve o destino (E.164) no notifiable (User::routeNotificationForWhatsapp);
 *   sem destino → degrada honesto (não transmite, não marca);
 * - resolve o gateway pelo contrato (binding default = UnavailableWhatsAppGateway);
 * - ao capturar WhatsAppUnavailableException: marca o Communication como BLOQUEADO
 *   + AUDITA a pendência (logName 'notificacoes', result 'bloqueado') e NÃO relança
 *   (canal indisponível jamais quebra o fluxo) — JAMAIS "enviado";
 * - no sucesso (gateway real/spy): marca o Communication como ENVIADO — prova de
 *   que liga sozinho quando a Fase 13 trocar SÓ o binding.
 *
 * A linha do ledger é localizada pelo contrato ProcessNotification
 * (viability_request_id + communicationType + channel=whatsapp + status na_fila),
 * usando o índice composto (viability_request_id, type, channel) criado em 11-01.
 */
class WhatsAppChannel
{
    public function __construct(
        private WhatsAppGateway $gateway,
        private AuditService $audit,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (blank($to)) {
            // Sem telefone E.164: o canal não tem "para onde" — degrada honesto.
            return;
        }

        // Conteúdo (body/meta) é da Notification; o destino (para onde) é resolvido
        // AQUI a partir do notifiable — o canal é a autoridade do destino.
        $content = $notification->toWhatsApp($notifiable);
        $message = new WhatsAppMessage(to: (string) $to, body: $content->body, meta: $content->meta);

        $communication = $this->resolveCommunication($notification);

        try {
            $this->gateway->send($message);

            $communication?->markAsSent();
        } catch (WhatsAppUnavailableException $e) {
            // Degradação honesta (provedor pendente — Fase 13): a transmissão NÃO
            // ocorreu. Marca o bloqueio + audita a pendência, NUNCA "enviado". Não
            // relança: o canal indisponível não quebra o fluxo da notificação.
            $communication?->markAsBlocked($e->getMessage());

            $this->audit->log(
                logName: 'notificacoes',
                event: $this->auditEvent($notification),
                description: 'Notificação por WhatsApp bloqueada (provedor indisponível — Fase 13).',
                properties: [
                    'viability_request_id' => $communication?->viability_request_id,
                    'communication_id' => $communication?->id,
                    'channel' => CommunicationChannel::Whatsapp->value,
                    'erro' => $e->getMessage(),
                ],
                subject: $communication,
                result: 'bloqueado',
            );
        }
    }

    /**
     * Localiza a linha do ledger do canal whatsapp pendente de envio. À discrição
     * do plano (id congelado pela Notification vs. lookup): adotado o LOOKUP pelo
     * contrato ProcessNotification, autossuficiente e sem acoplar ao dispatcher
     * (11-04, paralelo). Quando o 11-04 congelar {canal→communicationId} na
     * Notification, o canal pode evoluir para o id explícito — o lookup segue
     * correto como fallback.
     */
    private function resolveCommunication(Notification $notification): ?Communication
    {
        if (! $notification instanceof ProcessNotification) {
            return null;
        }

        $viabilityRequestId = $notification->viabilityRequestId();

        if ($viabilityRequestId === null) {
            return null;
        }

        return Communication::query()
            ->where('viability_request_id', $viabilityRequestId)
            ->where('type', $notification->communicationType())
            ->where('channel', CommunicationChannel::Whatsapp)
            ->where('status', CommunicationStatus::NaFila)
            ->latest('id')
            ->first();
    }

    private function auditEvent(Notification $notification): string
    {
        if ($notification instanceof ProcessNotification) {
            return $notification->communicationType()->value;
        }

        return CommunicationChannel::Whatsapp->value;
    }
}
