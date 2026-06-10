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

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Sua conta está inativa. Procure o administrador do sistema.',
        ]);

        $this->assertDatabaseHas('access_logs', [
            'email' => $user->email,
            'event' => 'inativada',
        ]);
    }

    public function test_credencial_invalida_em_conta_inativada_nao_revela_o_estado(): void
    {
        $user = User::factory()->cidadao()->inactive()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => __('auth.failed')]);

        $errors = session('errors')->get('email');
        $this->assertStringNotContainsString('inativa', implode(' ', $errors));

        $this->assertDatabaseHas('access_logs', [
            'email' => $user->email,
            'event' => 'falha',
        ]);
    }

    public function test_conta_ativa_continua_logando_normalmente(): void
    {
        $user = User::factory()->cidadao()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_reativacao_volta_a_permitir_login(): void
    {
        $user = User::factory()->cidadao()->inactive()->create();

        $user->forceFill(['inactivated_at' => null])->save();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }
}
