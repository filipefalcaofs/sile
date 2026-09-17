<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A identidade exposta (props compartilhadas do Inertia) é sempre a do guard
 * do ambiente: nunca a do portal vaza no console nem vice-versa, mesmo com os
 * dois guards autenticados na mesma sessão de navegador.
 */
class IdentityIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_console_mostra_identidade_do_servidor_nao_do_cidadao(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'web');
        $this->actingAs($servidor, 'gestao');

        $this->get('/gestao')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $servidor->id));
    }

    public function test_portal_mostra_identidade_do_cidadao_nao_do_servidor(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao');
        $this->actingAs($cidadao, 'web');

        $this->get('/portal/painel')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $cidadao->id));
    }
}
