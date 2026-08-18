<?php

namespace Tests\Feature\Routing;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EnvironmentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_landing_publica_renderiza(): void
    {
        // A raiz redireciona para o portal público, onde vive a landing (fase 2.3).
        $this->get('/')->assertRedirect('/portal');

        $this->get('/portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('home'));
    }

    public function test_visitante_e_redirecionado_ao_login_no_portal(): void
    {
        $this->get('/portal/painel')->assertRedirect('/portal/login');
    }

    public function test_visitante_na_gestao_e_redirecionado_ao_login_interno(): void
    {
        $this->get('/gestao')->assertRedirect('/gestao/login');
        $this->get('/gestao/cnaes')->assertRedirect('/gestao/login');
    }

    public function test_nao_verificado_na_gestao_e_redirecionado_ao_login_interno(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/gestao')
            ->assertRedirect('/gestao/login');

        $this->actingAs($user)
            ->get('/gestao/cnaes')
            ->assertRedirect('/gestao/login');
    }

    public function test_nao_verificado_autenticado_ve_tela_de_login_interno(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/gestao/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/gestao-login'));
    }

    public function test_tela_de_login_interno_renderiza(): void
    {
        $this->get('/gestao/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/gestao-login'));
    }

    public function test_servidor_autentica_pelo_login_interno(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->post('/gestao/login', [
            'email' => $administrador->email,
            'password' => 'password',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticatedAs($administrador, 'gestao');
    }

    public function test_autenticado_no_login_interno_vai_para_o_proprio_painel(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($administrador, 'gestao')
            ->get('/gestao/login')
            ->assertRedirect(route('gestao.dashboard'));
    }

    public function test_cidadao_acessa_dashboard_do_portal(): void
    {
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('portal/dashboard'));
    }

    public function test_props_de_autenticacao_sao_compartilhadas(): void
    {
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.user.id')
                ->has('auth.roles')
                ->has('auth.permissions')
                ->where('auth.roles.0', 'cidadao'));
    }

    public function test_cidadao_nao_acessa_gestao_e_tentativa_e_auditada(): void
    {
        $cidadao = User::factory()->cidadao()->create();

        // Sessão do portal é invisível para a gestão: vai ao login interno.
        $this->actingAs($cidadao)->get('/gestao')->assertRedirect('/gestao/login');

        // Defesa em profundidade: sessão da gestão que perdeu a permissão
        // (ex.: papel rebaixado após o login) é bloqueada e auditada —
        // a permissão é avaliada ANTES do gate LGPD (HU-002 CA-04).
        $this->actingAs($cidadao, 'gestao')->get('/gestao')->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_administrador_acessa_dashboard_da_gestao(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($administrador, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('gestao/dashboard'));
    }

    public function test_analista_acessa_gestao(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')->get('/gestao')->assertOk();
    }

    public function test_logout_da_gestao_volta_ao_login_interno_e_registra(): void
    {
        $administrador = User::factory()->administrador()->create();

        $response = $this->actingAs($administrador, 'gestao')->post('/gestao/logout');

        $response->assertRedirect(route('gestao.login'));
        $this->assertGuest('gestao');

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $administrador->id,
            'event' => 'logout',
            'channel' => 'gestao',
        ]);
    }

    public function test_logout_da_gestao_sem_sessao_da_gestao_volta_ao_login_interno(): void
    {
        // Sessão do portal não conta como sessão da gestão: o POST cai no
        // redirect de guest do contexto e o portal permanece intacto.
        $cidadao = User::factory()->cidadao()->create();

        $response = $this->actingAs($cidadao)->post('/gestao/logout');

        $response->assertRedirect(route('gestao.login'));
        $this->assertAuthenticatedAs($cidadao, 'web');
    }
}
