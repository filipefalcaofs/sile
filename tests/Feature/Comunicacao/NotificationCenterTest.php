<?php

namespace Tests\Feature\Comunicacao;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Central in-app (HU-090): o usuário — portal (guard web) e gestão (guard
 * gestao), o MESMO User notifiable — lista suas notificações do canal database
 * nativo (lidas + não-lidas, paginadas), marca uma e marca todas como lidas.
 *
 * Escopo do DONO: cada usuário só lê/marca as próprias notificações (anti-IDOR:
 * marcar a de outro → 404, fora do escopo da relação). O badge do sininho tem
 * dado real — a contagem de não-lidas é shared prop (HandleInertiaRequests),
 * avaliada na serialização da resposta Inertia.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function gestaoUser(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Cria uma notificação real no canal database nativo (tabela notifications)
     * do usuário — espelha o que o toDatabase() das Notifications de processo
     * grava (title/summary/url).
     *
     * @param  array<string, mixed>  $data
     */
    private function notificar(User $user, bool $lida = false, array $data = []): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\PendenciaSolicitadaNotification',
            'data' => $data !== [] ? $data : [
                'title' => 'Nova pendência na sua solicitação',
                'summary' => 'Envie o IPTU atualizado.',
                'url' => 'https://sile.test/portal/solicitacoes/1/pendencias',
            ],
            'read_at' => $lida ? now() : null,
        ]);
    }

    public function test_portal_lista_notificacoes_e_expoe_badge_de_nao_lidas(): void
    {
        $user = $this->portalUser();
        $this->notificar($user);
        $this->notificar($user);
        $this->notificar($user, lida: true);

        // Inspeciona as props sem exigir o componente .tsx (a tela é 11-09),
        // como o ProcessoConsultaTest faz para os endpoints que precedem a UI.
        $page = $this->actingAs($user)
            ->get(route('portal.notificacoes.index'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('portal/notificacoes/index', $page['component']);
        $this->assertCount(3, $page['props']['lista']['data']);
        $this->assertSame(3, $page['props']['lista']['total']);
        // Badge do sininho (shared prop): contagem real de não-lidas.
        $this->assertSame(2, $page['props']['notificacoes']['nao_lidas']);
    }

    public function test_portal_marca_uma_notificacao_como_lida(): void
    {
        $user = $this->portalUser();
        $notificacao = $this->notificar($user);

        $this->actingAs($user)
            ->from(route('portal.notificacoes.index'))
            ->post(route('portal.notificacoes.ler', $notificacao->id))
            ->assertRedirect();

        $this->assertNotNull($notificacao->refresh()->read_at);
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_portal_marca_todas_as_notificacoes_como_lidas(): void
    {
        $user = $this->portalUser();
        $this->notificar($user);
        $this->notificar($user);

        $this->actingAs($user)
            ->from(route('portal.notificacoes.index'))
            ->post(route('portal.notificacoes.ler-todas'))
            ->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_usuario_nao_marca_notificacao_de_outro_anti_idor(): void
    {
        $dono = $this->portalUser();
        $intruso = $this->portalUser();
        $notificacao = $this->notificar($dono);

        $this->actingAs($intruso)
            ->from(route('portal.notificacoes.index'))
            ->post(route('portal.notificacoes.ler', $notificacao->id))
            ->assertNotFound();

        $this->assertNull($notificacao->refresh()->read_at);
    }

    public function test_gestao_lista_marca_e_expoe_badge(): void
    {
        $user = $this->gestaoUser();
        $notificacao = $this->notificar($user);
        $this->notificar($user);

        $page = $this->actingAs($user, 'gestao')
            ->get(route('gestao.notificacoes.index'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('gestao/notificacoes/index', $page['component']);
        $this->assertCount(2, $page['props']['lista']['data']);
        $this->assertSame(2, $page['props']['notificacoes']['nao_lidas']);

        $this->actingAs($user, 'gestao')
            ->from(route('gestao.notificacoes.index'))
            ->post(route('gestao.notificacoes.ler', $notificacao->id))
            ->assertRedirect();

        $this->assertNotNull($notificacao->refresh()->read_at);
        $this->assertSame(1, $user->unreadNotifications()->count());
    }
}
