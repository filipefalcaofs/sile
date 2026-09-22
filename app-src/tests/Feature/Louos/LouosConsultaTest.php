<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\User;
use Database\Seeders\LouosQuadro10Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Mantenedores dos Quadros da LOUOS no console SEDUR (backend — HU-015..018):
 * a consulta da versão vigente dos 4 Quadros (analista/gestor/admin) é separada
 * da publicação versionada por quatro olhos (admin). Gate cross-guard via
 * permission: (PADRÃO 04-03/06). Publicar gera NOVA versão e preserva a anterior,
 * nunca edição destrutiva. A página React é entregue no 05-07 — aqui validamos
 * rota, contrato das props, auditoria e estado, sem ->component().
 */
class LouosConsultaTest extends TestCase
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

    public function test_analista_consulta_quadros_vigentes(): void
    {
        $this->seed(LouosQuadro10Seeder::class);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/louos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('quadros', 2)
                ->where('quadroSelecionado', 'quadro10')
                ->where('filtros.quadro', 'quadro10')
                ->has('itens.data')
                ->has('perPageOptions'));

        // Consulta auditada (RN-002) com a versão de regras consultada.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'louos',
            'event' => 'consulta-quadros',
            'result' => 'sucesso',
        ]);
    }

    public function test_consulta_sem_permissao_e_bloqueada(): void
    {
        // Acessa a gestão mas NÃO tem consultar-louos: o gate específico barra
        // e audita (CA-04), espelhando risco/território.
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/louos')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_admin_publica_nova_versao_do_quadro(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $anterior = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first();

        $publisher = $this->administrador();
        $author = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($publisher, 'gestao')
            ->put('/gestao/louos/publicar', [
                'quadro' => 'quadro10',
                'version' => 'lei-9148-2016-quadro10-rev2',
                'author_id' => $author->id,
                'alteracoes' => [
                    ['zona' => 'ZPR-REV', 'grupo_uso' => 'nR1', 'subgrupo' => '', 'permissao' => 'permitido'],
                ],
            ])
            ->assertSessionHas('status');

        $nova = RuleVersion::query()->where('version', 'lei-9148-2016-quadro10-rev2')->first();

        $this->assertNotNull($nova);
        $this->assertSame(RuleVersionStatus::Vigente, $nova->status);
        $this->assertSame($nova->id, RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()->id);

        // A anterior foi FECHADA (substituída), nunca apagada.
        $this->assertSame(RuleVersionStatus::Substituida, $anterior->fresh()->status);
        $this->assertNotNull($anterior->fresh()->valid_to);

        // Quatro olhos: autor e publicador distintos, registrados na versão.
        $this->assertSame($author->id, $nova->created_by);
        $this->assertSame($publisher->id, $nova->published_by);
    }

    public function test_publicacao_com_autor_igual_ao_publicador_e_bloqueada(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $mesmo = $this->administrador();

        $this->actingAs($mesmo, 'gestao')
            ->put('/gestao/louos/publicar', [
                'quadro' => 'quadro10',
                'version' => 'lei-9148-2016-quadro10-rev2',
                'author_id' => $mesmo->id,
                'alteracoes' => [],
            ])
            ->assertSessionHas('error');

        // Degradação controlada e comunicada: nada publicado, vigente preservada.
        $this->assertNull(RuleVersion::query()->where('version', 'lei-9148-2016-quadro10-rev2')->first());
        $this->assertSame(
            'lei-9148-2016-quadro10',
            RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()->version,
        );
    }

    public function test_publicar_sem_permissao_manter_louos_e_bloqueado(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $author = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        // Analista tem consultar-louos mas NÃO manter-louos.
        $this->actingAs($this->analista(), 'gestao')
            ->put('/gestao/louos/publicar', [
                'quadro' => 'quadro10',
                'version' => 'lei-9148-2016-quadro10-rev2',
                'author_id' => $author->id,
                'alteracoes' => [],
            ])
            ->assertForbidden();

        $this->assertNull(RuleVersion::query()->where('version', 'lei-9148-2016-quadro10-rev2')->first());
    }
}
