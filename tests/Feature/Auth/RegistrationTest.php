<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_pagina_de_cadastro_renderiza(): void
    {
        $this->get('/portal/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
    }

    public function test_usuario_se_cadastra_com_sucesso(): void
    {
        $response = $this->post('/portal/register', $this->validPayload());

        $response->assertRedirect('/portal/painel');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'cpf' => '52998224725',
        ]);

        $user = User::firstWhere('email', 'maria@example.com');

        $this->assertTrue($user->hasRole('cidadao'));
    }

    public function test_cadastro_gera_auditoria(): void
    {
        $this->post('/portal/register', $this->validPayload());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'cadastro',
            'event' => 'cadastro',
            'result' => 'sucesso',
        ]);
    }

    public function test_cadastro_bloqueado_com_dados_incompletos(): void
    {
        $response = $this->post('/portal/register', [
            'email' => 'maria@example.com',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
        ]);

        $response->assertSessionHasErrors(['name', 'cpf']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);

        $errors = session('errors')->get('name');

        $this->assertStringContainsString('obrigatória', $errors[0]);
        $this->assertStringContainsString('nome', $errors[0]);
    }

    public function test_cadastro_bloqueado_com_cpf_invalido(): void
    {
        $response = $this->post('/portal/register', [
            ...$this->validPayload(),
            'cpf' => '111.111.111-11',
        ]);

        $response->assertSessionHasErrors(['cpf']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_cadastro_bloqueado_com_email_duplicado(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);

        $response = $this->post('/portal/register', $this->validPayload());

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_cadastro_bloqueado_com_cpf_duplicado(): void
    {
        User::factory()->create(['cpf' => '52998224725']);

        $response = $this->post('/portal/register', [
            ...$this->validPayload(),
            'email' => 'outra@example.com',
        ]);

        $response->assertSessionHasErrors(['cpf']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_usuario_autenticado_nao_acessa_cadastro(): void
    {
        $user = User::factory()->cidadao()->create();

        $this->actingAs($user)->get('/portal/register')->assertRedirect();
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Maria da Silva',
            'email' => 'maria@example.com',
            'cpf' => '529.982.247-25',
            'phone' => '(71) 99999-0000',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
        ];
    }
}
