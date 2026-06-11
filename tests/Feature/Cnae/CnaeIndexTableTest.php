<?php

namespace Tests\Feature\Cnae;

use App\Models\Cnae;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Controles de tabela da listagem de CNAEs (Fase 2.4): ordenação,
 * itens por página e filtro de situação — server-driven via Inertia.
 */
class CnaeIndexTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actingAsAdmin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_ordena_por_denominacao_nas_duas_direcoes(): void
    {
        $admin = $this->actingAsAdmin();

        Cnae::factory()->create(['code' => '1111111', 'description' => 'Bordados artesanais']);
        Cnae::factory()->create(['code' => '2222222', 'description' => 'Açougues e peixarias']);
        Cnae::factory()->create(['code' => '3333333', 'description' => 'Costura sob medida']);

        $this->actingAs($admin)
            ->get('/gestao/cnaes?sort=description&direction=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cnaes.data.0.description', 'Açougues e peixarias')
                ->where('cnaes.data.2.description', 'Costura sob medida'));

        $this->actingAs($admin)
            ->get('/gestao/cnaes?sort=description&direction=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cnaes.data.0.description', 'Costura sob medida')
                ->where('cnaes.data.2.description', 'Açougues e peixarias'));
    }

    public function test_ordenacao_invalida_cai_no_padrao_por_codigo(): void
    {
        $admin = $this->actingAsAdmin();

        Cnae::factory()->create(['code' => '9999999']);
        Cnae::factory()->create(['code' => '1111111']);

        $this->actingAs($admin)
            ->get('/gestao/cnaes?sort=id;drop&direction=sideways')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cnaes.data.0.code', '1111111')
                ->where('filters.sort', 'code')
                ->where('filters.direction', 'asc'));
    }

    public function test_itens_por_pagina_da_whitelist_e_aplicado(): void
    {
        $admin = $this->actingAsAdmin();

        Cnae::factory()->count(12)->create();

        $this->actingAs($admin)
            ->get('/gestao/cnaes?per_page=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cnaes.data', 10)
                ->where('cnaes.total', 12)
                ->where('filters.per_page', 10));
    }

    public function test_itens_por_pagina_fora_da_whitelist_usa_o_padrao(): void
    {
        $admin = $this->actingAsAdmin();

        Cnae::factory()->count(20)->create();

        $this->actingAs($admin)
            ->get('/gestao/cnaes?per_page=999')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cnaes.data', 15)
                ->where('filters.per_page', 15));
    }

    public function test_filtra_por_situacao(): void
    {
        $admin = $this->actingAsAdmin();

        Cnae::factory()->count(2)->create();
        Cnae::factory()->inactive()->create(['description' => 'Atividade desativada']);

        $this->actingAs($admin)
            ->get('/gestao/cnaes?active=0')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cnaes.data', 1)
                ->where('cnaes.data.0.description', 'Atividade desativada')
                ->where('filters.active', '0'));

        $this->actingAs($admin)
            ->get('/gestao/cnaes?active=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('cnaes.data', 2));

        $this->actingAs($admin)
            ->get('/gestao/cnaes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cnaes.data', 3)
                ->where('filters.active', ''));
    }

    public function test_busca_combina_com_filtro_e_ordenacao(): void
    {
        $admin = $this->actingAsAdmin();

        Cnae::factory()->create(['description' => 'Restaurantes e similares']);
        Cnae::factory()->create(['description' => 'Restaurantes de beira de estrada']);
        Cnae::factory()->inactive()->create(['description' => 'Restaurante desativado']);
        Cnae::factory()->create(['description' => 'Cultivo de arroz']);

        $this->actingAs($admin)
            ->get('/gestao/cnaes?search=restaurante&active=1&sort=description&direction=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cnaes.data', 2)
                ->where('cnaes.data.0.description', 'Restaurantes e similares'));
    }
}
