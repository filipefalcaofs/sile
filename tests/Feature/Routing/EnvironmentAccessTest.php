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

        $this->assertAuthenticatedAs($administrador);
    }

    public function test_autenticado_no_login_interno_vai_para_o_proprio_painel(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($administrador)
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
        // Sem aceite do termo de propósito: a permissão é avaliada ANTES do
        // gate LGPD, então o 403 deve prevalecer (HU-002 CA-04).
        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($cidadao)->get('/gestao')->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_administrador_acessa_dashboard_da_gestao(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($administrador)
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('gestao/dashboard'));
    }

    public function test_analista_acessa_gestao(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista)->get('/gestao')->assertOk();
    }
}
