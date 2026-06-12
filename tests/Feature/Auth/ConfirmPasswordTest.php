<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConfirmPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_tela_de_confirmacao_de_senha_renderiza(): void
    {
        $user = User::factory()->cidadao()->create();

        $this->actingAs($user)
            ->get('/portal/user/confirm-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/confirm-password'));
    }

    public function test_senha_correta_confirma_a_sessao(): void
    {
        $user = User::factory()->cidadao()->create();

        $response = $this->actingAs($user)->post('/portal/user/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('auth.password_confirmed_at');
    }

    public function test_senha_incorreta_nao_confirma(): void
    {
        $user = User::factory()->cidadao()->create();

        $response = $this->actingAs($user)->post('/portal/user/confirm-password', [
            'password' => 'senha-errada',
        ]);

        $response->assertSessionHasErrors('password');
        $response->assertSessionMissing('auth.password_confirmed_at');
    }

    public function test_visitante_nao_acessa_confirmacao(): void
    {
        $this->get('/portal/user/confirm-password')->assertRedirect('/portal/login');
    }
}
