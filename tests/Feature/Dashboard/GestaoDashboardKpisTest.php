<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Home da gestão (HU-122): só `kpis.operacao`, gated por consultar-relatorios.
 * Contagens de cadastro (CNAEs, usuários, perfis, acessos) saíram da home.
 */
class GestaoDashboardKpisTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrador_nao_recebe_kpis_de_cadastro(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/dashboard')
                ->missing('kpis.cnaes')
                ->missing('kpis.usuarios')
                ->missing('kpis.perfis')
                ->missing('kpis.acessos')
                ->has('kpis.operacao'));
    }

    public function test_analista_recebe_operacao_nula(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpis.operacao', null)
                ->missing('kpis.cnaes'));
    }
}
