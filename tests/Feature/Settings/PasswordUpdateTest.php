<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_pagina_de_alteracao_de_senha_renderiza(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($user)
            ->get('/settings/password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/password'));
    }

    public function test_senha_e_alterada_com_dados_validos(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $response = $this->actingAs($user)->put('/user/password', [
            'current_password' => 'password',
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NovaSenhaForte123', $user->fresh()->password));
    }

    public function test_alteracao_gera_auditoria(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'password',
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'senha-alterada',
            'result' => 'sucesso',
            'causer_id' => $user->id,
        ]);
    }

    public function test_senha_atual_incorreta_bloqueia(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $response = $this->actingAs($user)->put('/user/password', [
            'current_password' => 'senha-errada',
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ]);

        $response->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_nova_senha_fora_da_politica_bloqueia(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $response = $this->actingAs($user)->put('/user/password', [
            'current_password' => 'password',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ]);

        $response->assertSessionHasErrorsIn('updatePassword', ['password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_visitante_nao_altera_senha(): void
    {
        $this->get('/settings/password')->assertRedirect('/login');

        $this->put('/user/password', [
            'current_password' => 'password',
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ])->assertRedirect('/login');
    }
}
