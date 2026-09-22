<?php

namespace Tests\Feature\Gestao;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Conta do servidor isolada no console (/gestao/conta), guard gestao —
 * separada das telas de conta do portal do cidadão (/portal/conta).
 */
class ContaConsoleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_servidor_acessa_a_conta_do_console(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao')
            ->get('/gestao/conta/perfil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/conta/perfil')
                ->where('user.id', $servidor->id));
    }

    public function test_cidadao_logado_so_no_portal_nao_acessa_conta_do_console(): void
    {
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'web')
            ->get('/gestao/conta/perfil')
            ->assertRedirect('/gestao/login');
    }

    public function test_servidor_altera_a_propria_senha_no_console(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao')->put('/gestao/conta/senha', [
            'current_password' => 'password',
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NovaSenhaForte123', $servidor->fresh()->password));
    }

    public function test_servidor_atualiza_o_proprio_perfil_no_console(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao')->patch('/gestao/conta/perfil', [
            'name' => 'Servidora Atualizada',
            'email' => $servidor->email,
            'phone' => '(71) 90000-0000',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Servidora Atualizada', $servidor->fresh()->name);
    }
}
