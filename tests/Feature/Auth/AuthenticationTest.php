<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_pagina_de_login_renderiza(): void
    {
        $this->get('/portal/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->has('canResetPassword'));
    }

    public function test_cidadao_autentica_com_cpf_e_vai_para_o_portal(): void
    {
        $user = User::factory()->cidadao()->create();

        $response = $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/portal/painel');
    }

    public function test_cpf_com_mascara_tambem_autentica(): void
    {
        $user = User::factory()->cidadao()->create();

        $masked = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $user->cpf);

        $response = $this->post('/portal/login', [
            'cpf' => $masked,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/portal/painel');
    }

    public function test_administrador_no_portal_fica_no_portal(): void
    {
        // Ambientes independentes: o login do portal nunca leva à gestão —
        // o console tem login próprio em /gestao/login (guard gestao).
        $user = User::factory()->administrador()->create();

        $response = $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('gestao');
        $response->assertRedirect('/portal/painel');
    }

    public function test_login_gera_registro_no_historico_de_acessos(): void
    {
        $user = User::factory()->cidadao()->create();

        $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'login',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_credencial_invalida_nao_autentica_e_registra_falha(): void
    {
        $user = User::factory()->cidadao()->create();

        $response = $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'senha-errada',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('cpf');

        // A trilha resolve o titular pelo CPF e registra o e-mail da conta.
        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => 'falha',
        ]);
    }

    public function test_cpf_inexistente_falha_sem_revelar_cadastro(): void
    {
        $response = $this->post('/portal/login', [
            'cpf' => '529.982.247-25',
            'password' => 'qualquer-senha',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['cpf' => __('auth.failed')]);

        // Tentativa sem conta correspondente fica rastreável pelo CPF digitado.
        $this->assertDatabaseHas('access_logs', [
            'email' => '52998224725',
            'event' => 'falha',
        ]);
    }

    public function test_bloqueio_temporario_apos_tentativas_excedidas(): void
    {
        config(['sile.security.login.max_attempts' => 3]);

        $user = User::factory()->cidadao()->create();

        $max = (int) Settings::get('security.login.max_attempts');

        foreach (range(1, $max) as $tentativa) {
            $this->post('/portal/login', [
                'cpf' => $user->cpf,
                'password' => 'senha-errada',
            ]);
        }

        $response = $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'senha-errada',
        ]);

        // Com fortify.limiters.login = null o bloqueio vem do pipeline
        // (EnsureLoginIsNotThrottled -> Lockout): em request web a resposta
        // é redirect com erro de validação, não 429.
        $this->assertGuest();
        $response->assertSessionHasErrors('cpf');

        $this->assertDatabaseHas('access_logs', [
            'email' => $user->email,
            'event' => 'bloqueio',
        ]);
    }

    public function test_mascara_no_cpf_nao_burla_o_limite_de_tentativas(): void
    {
        config(['sile.security.login.max_attempts' => 3]);

        $user = User::factory()->cidadao()->create();

        $masked = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $user->cpf);

        // Alterna formato com e sem máscara: o throttle normaliza para
        // dígitos e conta todas as tentativas na mesma chave.
        foreach ([$user->cpf, $masked, $user->cpf] as $cpf) {
            $this->post('/portal/login', [
                'cpf' => $cpf,
                'password' => 'senha-errada',
            ]);
        }

        $this->post('/portal/login', [
            'cpf' => $masked,
            'password' => 'senha-errada',
        ]);

        $this->assertDatabaseHas('access_logs', [
            'email' => $user->email,
            'event' => 'bloqueio',
        ]);
    }

    public function test_logout_encerra_sessao_e_registra(): void
    {
        $user = User::factory()->cidadao()->create();

        $this->actingAs($user)->post('/portal/logout');

        $this->assertGuest();

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);
    }

    public function test_intended_da_gestao_e_descartado_no_login_do_portal(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        // Visita guest a uma rota da gestão salva a URL pretendida na sessão.
        $this->get('/gestao/cnaes')->assertRedirect('/gestao/login');

        // O login do portal descarta o destino da gestão (lá exige o login
        // interno) — ninguém aterrissa no login do console sem querer.
        $response = $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
    }

    public function test_servidor_logando_no_portal_tambem_fica_no_portal(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->get('/gestao/cnaes')->assertRedirect('/gestao/login');

        $response = $this->post('/portal/login', [
            'cpf' => $administrador->cpf,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
    }

    public function test_cidadao_logado_no_portal_nao_entra_na_gestao(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'password',
        ]);

        $this->assertAuthenticated('web');

        // A sessão do portal não vale na gestão: cai no login interno —
        // e o login interno bloqueia conta sem permissão (CA-04, coberto
        // em GestaoLoginTest::test_cidadao_nao_loga_no_console).
        $this->get('/gestao')->assertRedirect('/gestao/login');
    }
}
