<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Listeners\RegistrarEnvioComunicacao;
use App\Models\Communication;
use App\Models\User;
use App\Notifications\Contracts\ProcessNotification;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * RegistrarEnvioComunicacao (HU-096): listener AUTO-DESCOBERTO que fecha o ciclo
 * do ledger communications — na_fila → enviado (NotificationSent) / falhou
 * (NotificationFailed). Espelha o LogNotificationSent (marca por evento de
 * notificação) e resolve a linha exata pelo MAPA {driver→communicationId}
 * congelado na Notification pelo dispatcher (11-04).
 *
 * CRÍTICO (anti-fachada): trata SOMENTE os canais mail e database. whatsapp é
 * EXCLUÍDO — o WhatsAppChannel (11-03) é o dono único da linha whatsapp (só ele
 * distingue enviado de bloqueado); tratar aqui sobrescreveria bloqueado→enviado.
 * Notificações de CONTA (sem communicationIds, ex.: VerifyEmailQueued) não
 * encostam no ledger de processo — o LogNotificationSent segue cuidando do EmailLog.
 *
 * Registro ÚNICO por auto-descoberta (type-hint do evento no handle; NUNCA
 * Event::listen — lição Fases 8/9), travado por CONTAGEM.
 */
class RegistrarEnvioComunicacaoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Notification de processo de teste com canais congelados + mapa
     * {driver→communicationId}, exatamente como o dispatcher (11-04) entrega.
     *
     * @param  array<int, CommunicationChannel>  $frozen
     * @param  array<string, int>  $communicationIds
     */
    private function notificacao(array $frozen, array $communicationIds): Notification&ProcessNotification
    {
        return new class($frozen, $communicationIds) extends Notification implements ProcessNotification
        {
            /**
             * @param  array<int, CommunicationChannel>  $frozen
             * @param  array<string, int>  $communicationIds
             */
            public function __construct(public array $frozen, public array $communicationIds) {}

            public function viabilityRequestId(): ?int
            {
                return null;
            }

            public function communicationType(): CommunicationType
            {
                return CommunicationType::PendenciaAberta;
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
                return $this->frozen;
            }

            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return array_map(fn (CommunicationChannel $channel): string => match ($channel) {
                    CommunicationChannel::Email => 'mail',
                    CommunicationChannel::InApp => 'database',
                    CommunicationChannel::Whatsapp => 'whatsapp',
                }, $this->channels());
            }

            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)->subject('Teste')->line('Teste');
            }

            /**
             * @return array<string, string>
             */
            public function toDatabase(object $notifiable): array
            {
                return ['mensagem' => 'Teste'];
            }
        };
    }

    public function test_envio_real_no_canal_database_marca_a_linha_como_enviado_uma_unica_vez(): void
    {
        $user = User::factory()->create();
        $communication = Communication::factory()->inApp()->create([
            'recipient_user_id' => $user->id,
            'status' => CommunicationStatus::NaFila,
        ]);
        $notification = $this->notificacao(
            [CommunicationChannel::InApp],
            ['database' => $communication->id],
        );

        // Conta as marcações no ledger: a auto-descoberta registra o listener UMA
        // única vez (lição Fases 8/9) — um Event::listen adicional marcaria 2×.
        $marcacoes = 0;
        Communication::updated(function (Communication $marcada) use (&$marcacoes, $communication): void {
            if ($marcada->getKey() === $communication->getKey()) {
                $marcacoes++;
            }
        });

        $user->notify($notification);

        $fresh = $communication->fresh();
        $this->assertSame(CommunicationStatus::Enviado, $fresh->status);
        $this->assertNotNull($fresh->sent_at);
        $this->assertSame(1, $marcacoes, 'O listener auto-descoberto deve marcar a linha exatamente uma vez.');
    }

    public function test_notification_failed_marca_a_linha_como_falhou_com_motivo(): void
    {
        $user = User::factory()->create();
        $communication = Communication::factory()->inApp()->create([
            'recipient_user_id' => $user->id,
            'status' => CommunicationStatus::NaFila,
        ]);
        $notification = $this->notificacao(
            [CommunicationChannel::InApp],
            ['database' => $communication->id],
        );

        event(new NotificationFailed($user, $notification, 'database', ['exception' => new \RuntimeException('Conexão recusada')]));

        $fresh = $communication->fresh();
        $this->assertSame(CommunicationStatus::Falhou, $fresh->status);
        $this->assertSame('Conexão recusada', $fresh->error_message);
        $this->assertNotNull($fresh->failed_at);
    }

    public function test_canal_whatsapp_nao_e_tratado_por_este_listener(): void
    {
        $user = User::factory()->create();
        // O WhatsAppChannel (11-03) é o dono único da linha whatsapp. Mesmo no
        // caminho degradado o Laravel dispara NotificationSent('whatsapp') — este
        // listener NÃO pode tocar a linha (sobrescreveria bloqueado→enviado).
        $whatsapp = Communication::factory()->whatsapp()->create([
            'recipient_user_id' => $user->id,
            'status' => CommunicationStatus::NaFila,
        ]);
        $notification = $this->notificacao(
            [CommunicationChannel::Whatsapp],
            ['whatsapp' => $whatsapp->id],
        );

        event(new NotificationSent($user, $notification, 'whatsapp'));

        // A linha whatsapp permanece na_fila — a exclusão impede o "enviado" fictício.
        $this->assertSame(CommunicationStatus::NaFila, $whatsapp->fresh()->status);
    }

    public function test_notificacao_de_conta_nao_encosta_no_ledger_de_processo(): void
    {
        $user = User::factory()->create();

        // VerifyEmailQueued é comunicação de CONTA (sem communicationIds): o
        // LogNotificationSent cuida do EmailLog; o ledger de PROCESSO fica intacto.
        $user->notify(new VerifyEmailQueued);

        $this->assertSame(0, Communication::query()->count());
    }

    public function test_exatamente_um_listener_auto_descoberto_trata_notification_failed(): void
    {
        // CONTAGEM: o RegistrarEnvioComunicacao é o ÚNICO auto-descoberto de
        // NotificationFailed (o LogNotificationSent só trata NotificationSent). Um
        // Event::listen adicional quebraria a contagem (lição Fases 8/9).
        $this->assertCount(1, Event::getListeners(NotificationFailed::class));
    }

    public function test_handles_fazem_type_hint_dos_eventos_de_notificacao(): void
    {
        // Contrato da auto-descoberta: cada handle* recebe o evento por type-hint
        // (base do registro automático — sem Event::listen).
        $sent = (new \ReflectionMethod(RegistrarEnvioComunicacao::class, 'handleNotificationSent'))->getParameters();
        $failed = (new \ReflectionMethod(RegistrarEnvioComunicacao::class, 'handleNotificationFailed'))->getParameters();

        $this->assertSame(NotificationSent::class, $sent[0]->getType()?->getName());
        $this->assertSame(NotificationFailed::class, $failed[0]->getType()?->getName());
    }
}
