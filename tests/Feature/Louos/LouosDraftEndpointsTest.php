<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\Zona;
use Database\Seeders\LouosQuadro10Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Endpoints HTTP do rascunho editável dos Quadros da LOUOS (HU-046):
 * controle de acesso por permissão, ciclo abrir/editar/importar/publicar/descartar,
 * modelo CSV por Quadro e remoção do Quadro 11 da consulta.
 */
class LouosDraftEndpointsTest extends TestCase
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

    public function test_consultar_louos_nao_acessa_rotas_de_rascunho(): void
    {
        $analista = $this->analista();

        // GET show rascunho
        $this->actingAs($analista, 'gestao')
            ->get('/gestao/louos/rascunho')
            ->assertForbidden();

        // POST abrir rascunho
        $this->actingAs($analista, 'gestao')
            ->post('/gestao/louos/rascunho', [
                'quadro' => 'quadro10',
                'version' => 'rascunho-v1',
            ])
            ->assertForbidden();

        // GET modelo CSV
        $this->actingAs($analista, 'gestao')
            ->get('/gestao/louos/modelo-csv?quadro=quadro10')
            ->assertForbidden();
    }

    public function test_manter_louos_abre_rascunho_e_ve_pagina(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho', [
                'quadro' => 'quadro10',
                'version' => '2026-rascunho-v1',
            ])
            ->assertRedirect(route('gestao.louos.rascunho.show', ['quadro' => 'quadro10']));

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/louos/rascunho?quadro=quadro10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/rascunho')
                ->whereNot('draft', null)
                ->has('draft.version')
                ->has('draft.id')
                ->has('itens')
                ->has('diff')
                ->has('canPublish'));
    }

    public function test_store_update_destroy_linha_via_http(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho', [
                'quadro' => 'quadro10',
                'version' => '2026-rascunho-v1',
            ]);

        $draft = RuleVersion::query()
            ->where('domain', RuleDomain::LouosQuadro10->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->firstOrFail();

        // Inserir nova linha
        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho/linhas', [
                'quadro' => 'quadro10',
                'zona' => 'ZPR-LIN',
                'grupo_uso' => 'nR1',
                'subgrupo' => '',
                'permissao' => 'permitido',
            ])
            ->assertSessionHas('status');

        $linha = LouosQuadro10Permissao::query()
            ->where('rule_version_id', $draft->id)
            ->where('zona', 'ZPR-LIN')
            ->first();

        $this->assertNotNull($linha);

        // A vigente não foi alterada
        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first();
        $this->assertNull(
            LouosQuadro10Permissao::query()
                ->where('rule_version_id', $vigente->id)
                ->where('zona', 'ZPR-LIN')
                ->first()
        );

        // Alterar linha no rascunho
        $this->actingAs($admin, 'gestao')
            ->put("/gestao/louos/rascunho/linhas/{$linha->id}", [
                'quadro' => 'quadro10',
                'zona' => 'ZPR-LIN',
                'grupo_uso' => 'nR1',
                'subgrupo' => '',
                'permissao' => 'proibido',
            ])
            ->assertSessionHas('status');

        $this->assertSame('proibido', $linha->fresh()->permissao->value);

        // Excluir linha do rascunho
        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/louos/rascunho/linhas/{$linha->id}", [
                'quadro' => 'quadro10',
            ])
            ->assertSessionHas('status');

        $this->assertNull($linha->fresh());

        // 422 em chave natural duplicada
        $primeiraFaixa = LouosQuadro10Permissao::query()
            ->where('rule_version_id', $draft->id)
            ->first();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho/linhas', [
                'quadro' => 'quadro10',
                'zona' => $primeiraFaixa->zona,
                'grupo_uso' => $primeiraFaixa->grupo_uso,
                'subgrupo' => $primeiraFaixa->subgrupo,
                'permissao' => $primeiraFaixa->permissao->value,
            ])
            ->assertSessionHasErrors(['linha']);
    }

    public function test_publicar_com_mesmo_autor_retorna_erro(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $admin = $this->administrador();

        // Admin abre o rascunho (created_by = admin)
        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho', [
                'quadro' => 'quadro10',
                'version' => '2026-rascunho-v1',
            ]);

        $draft = RuleVersion::query()
            ->where('domain', RuleDomain::LouosQuadro10->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->firstOrFail();

        LouosQuadro10Permissao::query()
            ->where('rule_version_id', $draft->id)
            ->pluck('zona')
            ->unique()
            ->each(function (string $zona): void {
                if (Zona::query()->where('codigo', $zona)->doesntExist()) {
                    Zona::factory()->create(['codigo' => $zona]);
                }
            });

        // Mesmo admin tenta publicar → quatro olhos bloqueado
        $this->actingAs($admin, 'gestao')
            ->put('/gestao/louos/rascunho/publicar', ['quadro' => 'quadro10'])
            ->assertSessionHas('error');

        // Outro admin publica → sucesso e redireciona para louos.index
        $publisher = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($publisher, 'gestao')
            ->put('/gestao/louos/rascunho/publicar', ['quadro' => 'quadro10'])
            ->assertRedirect(route('gestao.louos.index'));
    }

    public function test_importar_csv_retorna_relatorio_em_flash(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho', [
                'quadro' => 'quadro10',
                'version' => '2026-rascunho-import',
            ]);

        $csvContent = "zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal\nZPR-IMP,nR1,,permitido,,\n";
        $csvFile = UploadedFile::fake()->createWithContent('quadro10.csv', $csvContent);

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho/importar', [
                'quadro' => 'quadro10',
                'arquivo' => $csvFile,
            ])
            ->assertRedirect()
            ->assertSessionHas('importacao');

        $flash = session('importacao');
        $this->assertArrayHasKey('importados', $flash);
    }

    public function test_ativar_versao_substituida_torna_vigente(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $admin = $this->administrador();

        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro10)->firstOrFail();

        $anterior = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'lei-9148-2016-quadro10-anterior',
            'status' => RuleVersionStatus::Substituida,
            'valid_from' => now()->subYears(2)->toDateString(),
            'valid_to' => now()->subYear()->toDateString(),
        ]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/louos/versoes/{$anterior->id}/ativar", ['quadro' => 'quadro10'])
            ->assertRedirect(route('gestao.louos.index', ['quadro' => 'quadro10']))
            ->assertSessionHas('status');

        $this->assertSame(RuleVersionStatus::Vigente, $anterior->fresh()->status);
        $this->assertSame(RuleVersionStatus::Substituida, $vigente->fresh()->status);
        $this->assertTrue(RuleVersion::vigente(RuleDomain::LouosQuadro10)->sole()->is($anterior));
    }

    public function test_consultar_louos_lista_historico_de_versoes(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/louos?quadro=quadro10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('versoes')
                ->has('versoes.0.version')
                ->where('versoes.0.status', RuleVersionStatus::Vigente->value));
    }

    public function test_modelo_csv_baixa_cabecalho_do_quadro(): void
    {
        $admin = $this->administrador();

        $response = $this->actingAs($admin, 'gestao')
            ->get('/gestao/louos/modelo-csv?quadro=quadro10');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $primeiraLinha = strtok($content, "\n");
        $this->assertSame('zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal', $primeiraLinha);
    }

    public function test_consulta_nao_lista_mais_quadro11(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/louos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('quadros', 2));
    }
}
