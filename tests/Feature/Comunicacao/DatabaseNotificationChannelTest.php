<?php

namespace Tests\Feature\Comunicacao;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

/**
 * Smoke do canal `database` NATIVO de Notifications (HU-090): com a tabela
 * `notifications` migrada, um User (já Notifiable) recebe uma notificação no
 * canal database, ela passa a contar como não-lida e markAsRead grava read_at.
 * Base real da central in-app — sem regra de negócio, só o mecanismo de ponta a
 * ponta.
 */
class DatabaseNotificationChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_notificacao_no_canal_database_e_gravada_e_contada_como_nao_lida(): void
    {
        $user = User::factory()->create();

        $user->notify($this->notificacaoDeTeste());

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => $user->getMorphClass(),
        ]);
        $this->assertSame(1, $user->unreadNotifications()->count());
    }

    public function test_mark_as_read_zera_as_nao_lidas_e_grava_read_at(): void
    {
        $user = User::factory()->create();
        $user->notify($this->notificacaoDeTeste());

        $user->unreadNotifications->first()->markAsRead();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    private function notificacaoDeTeste(): Notification
    {
        return new class extends Notification
        {
            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            /**
             * @return array<string, string>
             */
            public function toDatabase(object $notifiable): array
            {
                return ['mensagem' => 'Pendência aberta na sua solicitação'];
            }
        };
    }
}
