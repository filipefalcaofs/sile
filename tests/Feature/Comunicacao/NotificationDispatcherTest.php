<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Models\Communication;
use App\Models\Parameter;
use App\Models\User;
use App\Notifications\Contracts\ProcessNotification;
use App\Services\Comunicacao\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * NotificationDispatcher (HU-090/094): o ponto único onde os toggles de feature
 * e o mapa de canais (HU-014) viram envio real + ledger honesto. Resolve os
 * canais SÍNCRONO no disparo (notificacoes.mapa_canais ∩ features.notificacao_*),
 * cria as linhas `communications` (na_fila dos habilitados; desativado + auditoria
 * dos canais do mapa com toggle off — degradação COMUNICADA, nunca silenciosa),
 * CONGELA os canais resolvidos e o mapa {driver→communicationId} na Notification
 * (espelha VerifyEmailQueued::freezeUrlFor + emailLogId) e dispara notify().
 *
 * Os testes usam Notification::fake() — provam a resolução de canais e o ledger
 * de forma independente do driver whatsapp (11-03).
 */
class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function dispatcher(): NotificationDispatcher
    {
        return app(NotificationDispatcher::class);
    }

    /**
     * Notification de PROCESSO de teste: implementa o contrato e mapeia os canais
     * de negócio congelados para os drivers nativos no via() (Email=>mail,
     * InApp=>database, Whatsapp=>whatsapp), exatamente como as Notifications reais
     * de 11-05/06.
     */
    private function notificacao(CommunicationType $type = CommunicationType::PendenciaAberta, ?int $viabilityRequestId = null): Notification&ProcessNotification
    {
        return new class($type, $viabilityRequestId) extends Notification implements ProcessNotification
        {
            /** @var array<int, CommunicationChannel> */
            public array $frozen = [];

            /** @var array<string, int> */
            public array $communicationIds = [];

            public function __construct(public CommunicationType $type, public ?int $vrId) {}

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

    private function desligarToggle(string $feature): void
    {
        // Parameter::saved invalida o cache da chave (efeito sem deploy, HU-014).
        Parameter::query()->create([
            'key' => "features.{$feature}",
            'group' => 'features',
            'type' => 'boolean',
            'value' => '0',
            'default_value' => '1',
            'validation_rules' => ['required', 'boolean'],
            'description' => "Toggle de canal de notificação ({$feature}).",
        ]);
    }

    public function test_mapa_com_email_e_in_app_e_ambos_toggles_on_cria_duas_linhas_na_fila_e_congela_mail_e_database(): void
    {
        NotificationFacade::fake();
        $user = User::factory()->create();
        $notification = $this->notificacao(CommunicationType::PendenciaAberta);

        $this->dispatcher()->deliver($user, $notification);

        // Duas linhas na_fila (mapa default pendencia_aberta = [email, in_app]).
        $this->assertSame(2, Communication::query()->where('status', CommunicationStatus::NaFila)->count());
        $this->assertDatabaseHas('communications', [
            'recipient_user_id' => $user->id,
            'channel' => CommunicationChannel::Email->value,
            'type' => CommunicationType::PendenciaAberta->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
        $this->assertDatabaseHas('communications', [
            'recipient_user_id' => $user->id,
            'channel' => CommunicationChannel::InApp->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);

        // via() congelado contém os drivers mail + database (lidos no envio).
        NotificationFacade::assertSentTo(
            $user,
            get_class($notification),
            fn (object $sent, array $channels): bool => count($channels) === 2
                && in_array('mail', $channels, true)
                && in_array('database', $channels, true),
        );

        // Mapa {driver→communicationId} congelado espelha as linhas na_fila.
        $emailId = Communication::query()->where('channel', CommunicationChannel::Email->value)->value('id');
        $inAppId = Communication::query()->where('channel', CommunicationChannel::InApp->value)->value('id');
        $this->assertSame(['mail' => $emailId, 'database' => $inAppId], $notification->communicationIds);
    }

    public function test_toggle_in_app_off_gera_linha_desativada_com_auditoria_e_so_mail_na_fila(): void
    {
        NotificationFacade::fake();
        $this->desligarToggle('notificacao_in_app');
        $user = User::factory()->create();
        $notification = $this->notificacao(CommunicationType::PendenciaAberta);

        $this->dispatcher()->deliver($user, $notification);

        $this->assertDatabaseHas('communications', [
            'channel' => CommunicationChannel::Email->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
        // Degradação COMUNICADA: in_app vira desativado.
        $this->assertDatabaseHas('communications', [
            'channel' => CommunicationChannel::InApp->value,
            'status' => CommunicationStatus::Desativado->value,
        ]);
        // E é auditada (nunca falha silenciosa).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'notificacoes',
            'event' => CommunicationType::PendenciaAberta->value,
            'result' => 'desativado',
        ]);

        // Só o driver mail entra no via() congelado.
        NotificationFacade::assertSentTo(
            $user,
            get_class($notification),
            fn (object $sent, array $channels): bool => $channels === ['mail'],
        );
        $this->assertArrayHasKey('mail', $notification->communicationIds);
        $this->assertArrayNotHasKey('database', $notification->communicationIds);
    }

    public function test_whatsapp_no_mapa_com_toggle_off_gera_desativado_e_nao_entra_no_via(): void
    {
        NotificationFacade::fake();
        // Mapa custom inclui whatsapp (toggle off por default, 11-02).
        config()->set('sile.notificacoes.mapa_canais', [
            'pendencia_aberta' => ['email', 'in_app', 'whatsapp'],
        ]);
        $user = User::factory()->create();
        $notification = $this->notificacao(CommunicationType::PendenciaAberta);

        $this->dispatcher()->deliver($user, $notification);

        // whatsapp degrada para desativado + auditoria (nunca finge envio).
        $this->assertDatabaseHas('communications', [
            'channel' => CommunicationChannel::Whatsapp->value,
            'status' => CommunicationStatus::Desativado->value,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'notificacoes',
            'event' => CommunicationType::PendenciaAberta->value,
            'result' => 'desativado',
        ]);

        // email + in_app seguem na_fila.
        $this->assertSame(2, Communication::query()->where('status', CommunicationStatus::NaFila)->count());

        // whatsapp NÃO entra no via() congelado nem no mapa.
        NotificationFacade::assertSentTo(
            $user,
            get_class($notification),
            fn (object $sent, array $channels): bool => ! in_array('whatsapp', $channels, true)
                && in_array('mail', $channels, true)
                && in_array('database', $channels, true),
        );
        $this->assertArrayNotHasKey('whatsapp', $notification->communicationIds);
    }

    public function test_todos_os_canais_off_nao_envia_nada_e_registra_desativado(): void
    {
        NotificationFacade::fake();
        $this->desligarToggle('notificacao_email');
        $this->desligarToggle('notificacao_in_app');
        $user = User::factory()->create();
        $notification = $this->notificacao(CommunicationType::PendenciaAberta);

        $this->dispatcher()->deliver($user, $notification);

        // Nenhum canal habilitado: nada enviado, ledger honesto (só desativado).
        $this->assertSame(0, Communication::query()->where('status', CommunicationStatus::NaFila)->count());
        $this->assertSame(2, Communication::query()->where('status', CommunicationStatus::Desativado)->count());
        NotificationFacade::assertNothingSent();
    }
}
