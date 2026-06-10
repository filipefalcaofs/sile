<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessLogRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_bem_sucedido_registra_acesso(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => 'login',
            'ip_address' => '127.0.0.1',
            'channel' => 'portal',
        ]);
    }

    public function test_falha_de_login_registra_tentativa_com_usuario_conhecido(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $this->assertGuest();

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => 'falha',
        ]);
    }

    public function test_falha_de_login_registra_tentativa_com_email_desconhecido(): void
    {
        $this->post('/login', [
            'email' => 'nao-existe@example.com',
            'password' => 'x',
        ]);

        $this->assertGuest();

        $this->assertDatabaseHas('access_logs', [
            'user_id' => null,
            'email' => 'nao-existe@example.com',
            'event' => 'falha',
        ]);
    }

    public function test_logout_registra_saida(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);
    }

    public function test_bloqueio_temporario_registra_evento(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $tentativa) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'senha-errada',
            ]);
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $this->assertDatabaseHas('access_logs', [
            'email' => $user->email,
            'event' => 'bloqueio',
        ]);
    }

    public function test_cadastro_gera_activity_de_cadastro(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->post('/register', [
            'name' => 'Maria da Silva',
            'email' => 'maria@example.com',
            'cpf' => '529.982.247-25',
            'phone' => '(71) 99999-0000',
            'password' => 'SenhaForte1',
            'password_confirmation' => 'SenhaForte1',
        ]);

        $user = User::firstWhere('email', 'maria@example.com');

        $this->assertNotNull($user, 'Esperava usuário criado pelo cadastro');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'cadastro',
            'event' => 'cadastro',
            'result' => 'sucesso',
            'causer_id' => $user->id,
        ]);
    }

    public function test_confirmacao_de_email_gera_activity(): void
    {
        $user = User::factory()->create();

        event(new Verified($user));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'email-confirmado',
            'causer_id' => $user->id,
        ]);
    }

    public function test_redefinicao_de_senha_gera_activity(): void
    {
        $user = User::factory()->create();

        event(new PasswordReset($user));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'senha-redefinida',
            'causer_id' => $user->id,
        ]);
    }
}
