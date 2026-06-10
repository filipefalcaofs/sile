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

    public function test_cidadao_autentica_e_vai_para_o_portal(): void
    {
        $user = User::factory()->cidadao()->create();

        $response = $this->post('/portal/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/portal/painel');
    }

    public function test_administrador_autentica_e_vai_para_a_gestao(): void
    {
        $user = User::factory()->administrador()->create();

        $response = $this->post('/portal/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/gestao');
    }

    public function test_login_gera_registro_no_historico_de_acessos(): void
    {
        $user = User::factory()->cidadao()->create();

        $this->post('/portal/login', [
            'email' => $user->email,
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
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');

        $this->assertDatabaseHas('access_logs', [
            'email' => $user->email,
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
                'email' => $user->email,
                'password' => 'senha-errada',
            ]);
        }

        $response = $this->post('/portal/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        // Com fortify.limiters.login = null o bloqueio vem do pipeline
        // (EnsureLoginIsNotThrottled -> Lockout): em request web a resposta
        // é redirect com erro de validação, não 429.
        $this->assertGuest();
        $response->assertSessionHasErrors('email');

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

    public function test_cidadao_logado_nao_acessa_gestao_e_bloqueio_e_auditado(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->post('/portal/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        $this->get('/gestao')->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }
}
