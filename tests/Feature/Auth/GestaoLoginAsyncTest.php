<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Contrato assíncrono do login do console: com o header
 * X-Viabiliza-Login-Async o destino volta em JSON, para o front rodar a
 * animação de entrada antes de navegar. Sem o header, o POST continua
 * redirecionando — o formulário sem JavaScript segue funcionando.
 */
class GestaoLoginAsyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_com_o_header_assincrono_o_destino_volta_em_json(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->postJson('/gestao/login', [
            'identifier' => $administrador->email,
            'password' => 'password',
        ], ['X-Viabiliza-Login-Async' => '1'])
            ->assertOk()
            ->assertExactJson(['destino' => route('gestao.dashboard')]);

        $this->assertAuthenticatedAs($administrador, 'gestao');
    }

    public function test_sem_o_header_o_login_continua_redirecionando(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->post('/gestao/login', [
            'identifier' => $administrador->email,
            'password' => 'password',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticatedAs($administrador, 'gestao');
    }

    public function test_credencial_invalida_no_modo_assincrono_devolve_422(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->postJson('/gestao/login', [
            'identifier' => $administrador->email,
            'password' => 'senha-errada',
        ], ['X-Viabiliza-Login-Async' => '1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('identifier');

        $this->assertGuest('gestao');
    }

    public function test_modo_assincrono_respeita_a_url_pretendida(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->withSession(['url.intended' => '/gestao/parametros'])
            ->postJson('/gestao/login', [
                'identifier' => $administrador->email,
                'password' => 'password',
            ], ['X-Viabiliza-Login-Async' => '1'])
            ->assertOk()
            ->assertExactJson(['destino' => '/gestao/parametros']);
    }

    public function test_modo_assincrono_registra_a_tentativa_na_auditoria(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->postJson('/gestao/login', [
            'identifier' => $administrador->email,
            'password' => 'password',
        ], ['X-Viabiliza-Login-Async' => '1'])->assertOk();

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $administrador->id,
            'event' => 'login',
            'channel' => 'gestao',
        ]);
    }
}
