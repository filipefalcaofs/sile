<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Autenticação da retaguarda em guard próprio (gestao): sessão totalmente
 * independente da sessão do portal do cidadão (guard web).
 */
class GestaoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_servidor_autentica_no_guard_gestao_sem_tocar_o_portal(): void
    {
        $administrador = User::factory()->administrador()->create();

        $response = $this->post('/gestao/login', [
            'email' => $administrador->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/gestao');

        $this->assertAuthenticatedAs($administrador, 'gestao');
        $this->assertGuest('web');

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $administrador->id,
            'event' => 'login',
            'channel' => 'gestao',
        ]);
    }

    public function test_sessao_do_portal_nao_da_acesso_a_gestao(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        // Logado no portal (guard web), a gestão continua exigindo login próprio.
        $this->actingAs($administrador)->get('/gestao')->assertRedirect('/gestao/login');
    }

    public function test_sessao_da_gestao_nao_da_acesso_ao_portal(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($administrador, 'gestao')->get('/portal/painel')->assertRedirect('/portal/login');
    }

    public function test_cidadao_nao_loga_no_console_e_tentativa_e_auditada(): void
    {
        $cidadao = User::factory()->cidadao()->create();

        $response = $this->post('/gestao/login', [
            'email' => $cidadao->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertGuest('gestao');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_credencial_invalida_nao_loga_e_registra_falha(): void
    {
        $administrador = User::factory()->administrador()->create();

        $response = $this->post('/gestao/login', [
            'email' => $administrador->email,
            'password' => 'senha-errada',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertGuest('gestao');

        $this->assertDatabaseHas('access_logs', [
            'email' => $administrador->email,
            'event' => 'falha',
            'channel' => 'gestao',
        ]);
    }

    public function test_conta_inativada_nao_loga_no_console(): void
    {
        $administrador = User::factory()->administrador()->inactive()->create();

        $response = $this->post('/gestao/login', [
            'email' => $administrador->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Sua conta está inativa. Procure o administrador do sistema.',
        ]);

        $this->assertGuest('gestao');

        $this->assertDatabaseHas('access_logs', [
            'email' => $administrador->email,
            'event' => 'inativada',
        ]);
    }

    public function test_email_nao_verificado_nao_loga_no_console(): void
    {
        $analista = User::factory()->analista()->unverified()->create();

        $response = $this->post('/gestao/login', [
            'email' => $analista->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertGuest('gestao');
    }

    public function test_bloqueio_temporario_apos_tentativas_excedidas(): void
    {
        config(['sile.security.login.max_attempts' => 3]);

        $administrador = User::factory()->administrador()->create();

        $max = (int) Settings::get('security.login.max_attempts');

        foreach (range(1, $max) as $tentativa) {
            $this->post('/gestao/login', [
                'email' => $administrador->email,
                'password' => 'senha-errada',
            ]);
        }

        $response = $this->post('/gestao/login', [
            'email' => $administrador->email,
            'password' => 'senha-errada',
        ]);

        $this->assertGuest('gestao');
        $response->assertSessionHasErrors('email');

        $this->assertDatabaseHas('access_logs', [
            'email' => $administrador->email,
            'event' => 'bloqueio',
            'channel' => 'gestao',
        ]);
    }

    public function test_intended_da_gestao_e_respeitado_no_login_interno(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->get('/gestao/cnaes')->assertRedirect('/gestao/login');

        $response = $this->post('/gestao/login', [
            'email' => $administrador->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/gestao/cnaes');
    }

    public function test_logout_da_gestao_preserva_a_sessao_do_portal(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($administrador);
        $this->actingAs($administrador, 'gestao');

        $response = $this->post('/gestao/logout');

        $response->assertRedirect(route('gestao.login'));

        $this->assertGuest('gestao');
        $this->assertAuthenticatedAs($administrador, 'web');
    }

    public function test_autenticado_na_gestao_nao_ve_o_login_interno(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($administrador, 'gestao')
            ->get('/gestao/login')
            ->assertRedirect(route('gestao.dashboard'));
    }

    public function test_sessao_do_portal_nao_redireciona_o_login_interno(): void
    {
        // Com guards separados, a sessão do portal é invisível para a gestão:
        // o formulário de login interno aparece normalmente.
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao)->get('/gestao/login')->assertOk();
    }

    public function test_login_interno_compartilha_versao_e_revisao_da_aplicacao(): void
    {
        config([
            'app.version' => '9.9.9',
            'app.revision' => 'abc1234',
        ]);

        $this->get('/gestao/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/gestao-login')
                ->where('appVersion', '9.9.9')
                ->where('appRevision', 'abc1234'));
    }
}
