<?php

namespace Tests\Feature\Louos;

use App\Models\User;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * UI dos mantenedores dos Quadros da LOUOS no console SEDUR (HU-015..018): a
 * página React já existe em disco (05-07), então o componente é assertado contra
 * o contrato de props do LouosController (05-06). Complementa o LouosConsultaTest
 * (que valida rota/props/auditoria sem ->component()): aqui validamos o
 * componente renderizado e a affordance de publicação gateada por permissão
 * (manter-louos) via prop compartilhada auth.permissions.
 */
class LouosUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_pagina_de_quadros_renderiza_componente_e_props(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->has('quadros', 4)
                ->where('quadroSelecionado', 'quadro7')
                ->where('quadros.0.quadro', 'quadro7')
                ->where('quadros.0.version', 'lei-9148-2016-quadro7')
                ->where('filtros.quadro', 'quadro7')
                ->has('itens.data.0', fn (Assert $item) => $item
                    ->has('id')
                    ->has('cnae_code')
                    ->has('formatted_code')
                    ->has('grupo')
                    ->has('area_min')
                    ->etc())
                ->has('perPageOptions'));
    }

    public function test_admin_ve_acao_de_publicar_e_analista_nao(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        // O administrador tem manter-louos: a página renderiza com a permissão
        // que habilita a ação "Publicar nova versão" (auth.permissions partilhado).
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('manter-louos')));

        // O analista consulta mas NÃO mantém: sem manter-louos, sem a ação.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/louos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->where('auth.permissions', fn ($permissions) => ! collect($permissions)->contains('manter-louos')));
    }
}
