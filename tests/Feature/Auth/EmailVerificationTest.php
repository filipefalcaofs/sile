<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_cadastro_dispara_email_de_verificacao(): void
    {
        Notification::fake();

        $this->post('/portal/register', [
            'name' => 'Maria da Silva',
            'email' => 'maria@example.com',
            'cpf' => '529.982.247-25',
            'phone' => '(71) 99999-0000',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
        ]);

        $user = User::firstWhere('email', 'maria@example.com');

        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_tela_de_verificacao_renderiza(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/portal/email/verify')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/verify-email'));
    }

    public function test_email_e_verificado_com_link_assinado(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirectContains('/portal/painel');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verificacao_gera_auditoria(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'email-confirmado',
            'causer_id' => $user->id,
        ]);
    }

    public function test_hash_invalido_nao_verifica(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('outro@example.com'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_nao_verificado_nao_acessa_portal(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/portal/painel')
            ->assertRedirect('/portal/email/verify');
    }

    public function test_reenvio_de_link(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/portal/email/verification-notification');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
