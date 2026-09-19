<?php

namespace Tests\Feature\Louos;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
    use LazilyRefreshDatabase;

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
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->has('quadros', 2)
                ->where('quadroSelecionado', 'quadro10')
                ->where('quadros.0.quadro', 'quadro10')
                ->where('filtros.quadro', 'quadro10')
                ->has('perPageOptions'));
    }

    public function test_admin_ve_acao_de_publicar_e_analista_nao(): void
    {
        // O administrador tem manter-louos: a página renderiza com a permissão
        // que habilita as ações de atualização via rascunho (auth.permissions partilhado).
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('manter-louos')));

        // O analista consulta mas NÃO mantém: sem manter-louos, sem as ações.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/louos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->where('auth.permissions', fn ($permissions) => ! collect($permissions)->contains('manter-louos')));
    }

    public function test_listagem_aponta_publicacao_para_o_rascunho_nao_para_alteracao_manual(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos?quadro=quadro10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->where('urlRascunho', '/gestao/louos/rascunho?quadro=quadro10')
                ->missing('publishForm'));
    }

    public function test_botao_editar_quadro_acessivel_a_mantenedor_e_inacessivel_a_consultor(): void
    {
        // Verificação via rota: o mantenedor (manter-louos) acessa a página de rascunho;
        // o consultor (consultar-louos) recebe 403.
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos/rascunho?quadro=quadro10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/rascunho')
                ->where('quadro', 'quadro10')
                ->has('draft')
                ->has('canPublish'));

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/louos/rascunho?quadro=quadro10')
            ->assertForbidden();
    }

    public function test_mantenedor_acessa_manual_de_csv_do_quadro_selecionado(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos/manual?quadro=quadro10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/manual')
                ->where('quadro', 'quadro10')
                ->where('urlModeloCsv', '/gestao/louos/modelo-csv?quadro=quadro10')
                ->where('urlRascunho', '/gestao/louos/rascunho?quadro=quadro10'));
    }

    public function test_consultor_nao_acessa_manual_de_csv(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/louos/manual')
            ->assertForbidden();
    }

    public function test_listagem_e_rascunho_apontam_para_o_manual_de_csv(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos?quadro=quadro10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->where('urlManual', '/gestao/louos/manual?quadro=quadro10'));

        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos/rascunho?quadro=quadro11a')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/rascunho')
                ->where('urlManual', '/gestao/louos/manual?quadro=quadro11a'));
    }
}
