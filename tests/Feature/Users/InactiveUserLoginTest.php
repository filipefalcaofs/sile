<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveUserLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_conta_inativada_nao_loga_e_recebe_mensagem_clara(): void
    {
        $user = User::factory()->cidadao()->inactive()->create();

        $response = $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'cpf' => 'Sua conta está inativa. Procure o administrador do sistema.',
        ]);

        $this->assertDatabaseHas('access_logs', [
            'email' => $user->email,
            'event' => 'inativada',
        ]);
    }

    public function test_credencial_invalida_em_conta_inativada_nao_revela_o_estado(): void
    {
        $user = User::factory()->cidadao()->inactive()->create();

        $response = $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'senha-errada',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['cpf' => __('auth.failed')]);

        $errors = session('errors')->get('cpf');
        $this->assertStringNotContainsString('inativa', implode(' ', $errors));

        $this->assertDatabaseHas('access_logs', [
            'email' => $user->email,
            'event' => 'falha',
        ]);
    }

    public function test_conta_ativa_continua_logando_normalmente(): void
    {
        $user = User::factory()->cidadao()->create();

        $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_reativacao_volta_a_permitir_login(): void
    {
        $user = User::factory()->cidadao()->inactive()->create();

        $user->forceFill(['inactivated_at' => null])->save();

        $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_sessao_ativa_de_usuario_inativado_e_derrubada(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($user);

        $user->forceFill(['inactivated_at' => now()])->save();

        $response = $this->get('/portal/painel');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Sua conta está inativa. Procure o administrador do sistema.',
        ]);
        $this->assertGuest();

        $this->get('/portal/painel')->assertRedirect(route('login'));
    }

    public function test_usuario_ativo_navega_normalmente_com_o_middleware(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($user)->get('/portal/painel')->assertOk();
    }

    public function test_sessao_de_servidor_inativado_na_gestao_e_derrubada_para_o_login_interno(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao');

        $analista->forceFill(['inactivated_at' => now()])->save();

        $response = $this->get('/gestao');

        $response->assertRedirect(route('gestao.login'));
        $response->assertSessionHasErrors([
            'email' => 'Sua conta está inativa. Procure o administrador do sistema.',
        ]);
        $this->assertGuest('gestao');
    }
}
