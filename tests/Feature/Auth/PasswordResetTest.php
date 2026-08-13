<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_pagina_esqueci_senha_renderiza(): void
    {
        $this->get('/portal/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/forgot-password'));
    }

    public function test_link_de_recuperacao_e_enviado(): void
    {
        Notification::fake();

        $user = User::factory()->cidadao()->create();

        $this->post('/portal/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordQueued::class);
    }

    public function test_pagina_de_redefinicao_renderiza_com_token(): void
    {
        Notification::fake();

        $user = User::factory()->cidadao()->create();

        $this->post('/portal/forgot-password', ['email' => $user->email]);

        $token = null;

        Notification::assertSentTo($user, ResetPasswordQueued::class, function (ResetPasswordQueued $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->get("/portal/reset-password/{$token}?email={$user->email}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/reset-password')
                ->where('email', $user->email)
                ->where('token', $token));
    }

    public function test_senha_e_redefinida_com_token_valido(): void
    {
        $user = User::factory()->cidadao()->create();

        $token = Password::createToken($user);

        $response = $this->post('/portal/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ]);

        $response->assertRedirect('/portal/login');
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('NovaSenhaForte123', $user->fresh()->password));
    }

    public function test_redefinicao_gera_auditoria(): void
    {
        $user = User::factory()->cidadao()->create();

        $token = Password::createToken($user);

        $this->post('/portal/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'senha-redefinida',
            'causer_id' => $user->id,
        ]);
    }

    public function test_token_invalido_nao_redefine(): void
    {
        $user = User::factory()->cidadao()->create();

        $response = $this->post('/portal/reset-password', [
            'token' => Str::random(60),
            'email' => $user->email,
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_email_desconhecido_nao_envia(): void
    {
        Notification::fake();

        $response = $this->post('/portal/forgot-password', ['email' => 'nao-existe@example.com']);

        $response->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_nova_senha_fraca_e_bloqueada(): void
    {
        $user = User::factory()->cidadao()->create();

        $token = Password::createToken($user);

        $response = $this->post('/portal/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ]);

        $response->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_link_de_redefinicao_e_congelado_no_disparo_nao_no_worker(): void
    {
        Notification::fake();

        $user = User::factory()->cidadao()->create();

        $this->post('/portal/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordQueued::class, function (ResetPasswordQueued $notification) {
            return $notification->resetUrl !== null
                && str_contains($notification->resetUrl, '/portal/reset-password/');
        });
    }

    public function test_autenticado_nao_acessa_recuperacao(): void
    {
        $user = User::factory()->cidadao()->create();

        // Rotas de recuperação são guest-only: redirectUsersTo manda o
        // autenticado para o próprio painel, conforme o perfil (fase 2.3).
        $this->actingAs($user)
            ->get('/portal/forgot-password')
            ->assertRedirect(route('portal.dashboard'));
    }
}
