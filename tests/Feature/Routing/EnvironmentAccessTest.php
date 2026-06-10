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
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('home'));
    }

    public function test_visitante_e_redirecionado_ao_login_no_portal(): void
    {
        $this->get('/portal')->assertRedirect('/login');
    }

    public function test_cidadao_acessa_dashboard_do_portal(): void
    {
        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($cidadao)
            ->get('/portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('portal/dashboard'));
    }

    public function test_props_de_autenticacao_sao_compartilhadas(): void
    {
        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($cidadao)
            ->get('/portal')
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

        $this->actingAs($cidadao)->get('/gestao')->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_administrador_acessa_dashboard_da_gestao(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->actingAs($administrador)
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('gestao/dashboard'));
    }

    public function test_analista_acessa_gestao(): void
    {
        $analista = User::factory()->analista()->create();

        $this->actingAs($analista)->get('/gestao')->assertOk();
    }
}
