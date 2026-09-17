<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\GestaoResetPasswordQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Recuperação de senha própria do console (guard gestao), isolada do portal:
 * link aponta para /gestao/reset-password, restrita a contas com acesso ao
 * console e anti-oráculo (não revela a existência da conta).
 */
class GestaoPasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_pagina_esqueci_senha_do_console_renderiza(): void
    {
        $this->get('/gestao/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/gestao-forgot-password'));
    }

    public function test_link_de_recuperacao_do_console_aponta_para_gestao(): void
    {
        Notification::fake();

        $servidor = User::factory()->administrador()->create();

        $this->post('/gestao/forgot-password', ['email' => $servidor->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($servidor, GestaoResetPasswordQueued::class, function (GestaoResetPasswordQueued $n) {
            return $n->resetUrl !== null && str_contains($n->resetUrl, '/gestao/reset-password/');
        });
    }

    public function test_email_sem_acesso_ao_console_nao_recebe_link_anti_oraculo(): void
    {
        Notification::fake();

        $cidadao = User::factory()->cidadao()->create();

        $this->post('/gestao/forgot-password', ['email' => $cidadao->email])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_pagina_de_redefinicao_do_console_renderiza_com_token(): void
    {
        $servidor = User::factory()->administrador()->create();
        $token = Password::broker('users')->createToken($servidor);

        $this->get("/gestao/reset-password/{$token}?email={$servidor->email}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/gestao-reset-password')
                ->where('email', $servidor->email)
                ->where('token', $token));
    }

    public function test_senha_do_console_e_redefinida_com_token_valido(): void
    {
        $servidor = User::factory()->administrador()->create();
        $token = Password::broker('users')->createToken($servidor);

        $this->post('/gestao/reset-password', [
            'token' => $token,
            'email' => $servidor->email,
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ])->assertRedirect('/gestao/login');

        $this->assertTrue(Hash::check('NovaSenhaForte123', $servidor->fresh()->password));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'senha-redefinida',
            'causer_id' => $servidor->id,
        ]);
    }

    public function test_token_invalido_do_console_nao_redefine(): void
    {
        $servidor = User::factory()->administrador()->create();

        $this->post('/gestao/reset-password', [
            'token' => 'token-invalido',
            'email' => $servidor->email,
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $servidor->fresh()->password));
    }

    public function test_recuperacao_do_console_e_guest_only(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao')
            ->get('/gestao/forgot-password')
            ->assertRedirect('/gestao');
    }
}
