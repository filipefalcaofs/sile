<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Models\Activity;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\Contracts\ProcessNotification;
use App\Services\Whatsapp\WhatsAppGateway;
use App\Services\Whatsapp\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

/**
 * Canal customizado de WhatsApp (HU-095) — DONO ÚNICO da linha communications do
 * canal whatsapp. Espelha ComunicarResultadoRegin: ao enviar com o gateway
 * indisponível (binding default), CAPTURA WhatsAppUnavailableException, marca o
 * Communication como BLOQUEADO + AUDITA — JAMAIS "enviado", e NÃO relança (degrada
 * controlado). Com o gateway DISPONÍVEL (spy) ele envia e marca enviado — prova de
 * que liga sozinho na Fase 13 trocando SÓ o binding. Sem destino (phone) → não
 * envia (degrada honesto).
 */
class WhatsAppChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_canal_indisponivel_marca_communication_bloqueado_e_audita_sem_enviado(): void
    {
        // Caminho REAL hoje: binding default (Unavailable) lança a exceção. O canal
        // captura, marca bloqueado e audita a pendência — nunca um envio fictício.
        $user = User::factory()->create(['phone' => '+5571999990000']);
        $request = ViabilityRequest::factory()->create();
        $communication = $this->linhaWhatsAppNaFila($request->id, $user->id, CommunicationType::PendenciaAberta);

        $user->notify($this->notificacaoWhatsApp($request->id, CommunicationType::PendenciaAberta, 'Você tem uma pendência.'));

        $this->assertSame(
            CommunicationStatus::Bloqueado,
            $communication->refresh()->status,
            'Canal on + provedor indisponível: a linha vira BLOQUEADO (nunca enviado).'
        );

        $this->assertSame(1, Activity::query()
            ->where('log_name', 'notificacoes')
            ->where('result', 'bloqueado')
            ->count(), 'O bloqueio do canal WhatsApp deve ser auditado uma única vez.');

        // Anti-fachada: nenhuma comunicação "enviada".
        $this->assertSame(0, Communication::query()
            ->where('status', CommunicationStatus::Enviado)
            ->count());
    }

    public function test_caminho_de_sucesso_com_spy_envia_em_e164_e_marca_enviado(): void
    {
        // Gateway DISPONÍVEL (spy): prova de que a lógica liga sozinha quando a Fase
        // 13 trocar SÓ o binding. O canal injeta o destino E.164 do destinatário.
        $user = User::factory()->create(['phone' => '+5571988887777']);
        $request = ViabilityRequest::factory()->create();
        $communication = $this->linhaWhatsAppNaFila($request->id, $user->id, CommunicationType::PendenciaAberta);

        $spy = $this->gatewaySpy();
        $this->app->instance(WhatsAppGateway::class, $spy);

        $user->notify($this->notificacaoWhatsApp($request->id, CommunicationType::PendenciaAberta, 'Corpo da mensagem.'));

        $this->assertCount(1, $spy->messages, 'O canal deve transmitir a mensagem ao gateway disponível.');
        $this->assertSame('+5571988887777', $spy->messages[0]->to, 'O destino vem do User (routeNotificationForWhatsapp, E.164).');
        $this->assertSame('Corpo da mensagem.', $spy->messages[0]->body);

        $this->assertSame(
            CommunicationStatus::Enviado,
            $communication->refresh()->status,
            'Com o gateway disponível, a linha do canal whatsapp vira ENVIADO.'
        );
    }

    public function test_sem_telefone_no_destinatario_nao_envia_e_degrada(): void
    {
        // Sem destino E.164: o canal não tem "para onde" — degrada honesto (não
        // envia, não marca enviado nem bloqueado).
        $user = User::factory()->create(['phone' => null]);
        $request = ViabilityRequest::factory()->create();
        $communication = $this->linhaWhatsAppNaFila($request->id, $user->id, CommunicationType::PendenciaAberta);

        $spy = $this->gatewaySpy();
        $this->app->instance(WhatsAppGateway::class, $spy);

        $user->notify($this->notificacaoWhatsApp($request->id, CommunicationType::PendenciaAberta, 'Corpo.'));

        $this->assertCount(0, $spy->messages, 'Sem telefone E.164 o canal não transmite (degrada honesto).');
        $this->assertSame(
            CommunicationStatus::NaFila,
            $communication->refresh()->status,
            'Sem destino: a linha permanece na_fila (nem enviado, nem bloqueado).'
        );
    }

    private function linhaWhatsAppNaFila(int $viabilityRequestId, int $recipientUserId, CommunicationType $type): Communication
    {
        return Communication::factory()->whatsapp()->create([
            'viability_request_id' => $viabilityRequestId,
            'recipient_user_id' => $recipientUserId,
            'type' => $type,
            'status' => CommunicationStatus::NaFila,
        ]);
    }

    /**
     * Notification de processo inline (as Notifications reais implementam
     * toWhatsApp em 11-05/06). O toWhatsApp devolve a WhatsAppMessage com `to`
     * vazio de propósito — provando que o CANAL injeta o destino do destinatário.
     */
    private function notificacaoWhatsApp(int $viabilityRequestId, CommunicationType $type, string $body): Notification
    {
        return new class($viabilityRequestId, $type, $body) extends Notification implements ProcessNotification
        {
            /** @var array<int, CommunicationChannel> */
            private array $frozen = [];

            public function __construct(
                private int $vrId,
                private CommunicationType $type,
                private string $body,
            ) {}

            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return ['whatsapp'];
            }

            public function toWhatsApp(object $notifiable): WhatsAppMessage
            {
                return new WhatsAppMessage(to: '', body: $this->body, meta: ['origem' => 'teste']);
            }

            public function viabilityRequestId(): ?int
            {
                return $this->vrId;
            }

            public function communicationType(): CommunicationType
            {
                return $this->type;
            }

            /**
             * @param  array<int, CommunicationChannel>  $channels
             */
            public function freezeChannels(array $channels): void
            {
                $this->frozen = $channels;
            }

            /**
             * @return array<int, CommunicationChannel>
             */
            public function channels(): array
            {
                return $this->frozen ?: [CommunicationChannel::Whatsapp];
            }
        };
    }

    /**
     * Spy do gateway que REGISTRA cada chamada (prova de que o canal liga quando o
     * provedor existir — Fase 13). Spy SÓ no teste, prática padrão.
     */
    private function gatewaySpy(): WhatsAppGateway
    {
        return new class implements WhatsAppGateway
        {
            /** @var list<WhatsAppMessage> */
            public array $messages = [];

            public function send(WhatsAppMessage $message): void
            {
                $this->messages[] = $message;
            }
        };
    }
}
