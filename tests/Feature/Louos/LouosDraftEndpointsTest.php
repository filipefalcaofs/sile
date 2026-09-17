<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Models\User;
use Database\Seeders\LouosQuadro7Seeder;
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
                'quadro' => 'quadro7',
                'version' => 'rascunho-v1',
            ])
            ->assertForbidden();

        // GET modelo CSV
        $this->actingAs($analista, 'gestao')
            ->get('/gestao/louos/modelo-csv?quadro=quadro7')
            ->assertForbidden();
    }

    public function test_manter_louos_abre_rascunho_e_ve_pagina(): void
    {
        $this->seed(LouosQuadro7Seeder::class);
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho', [
                'quadro' => 'quadro7',
                'version' => '2026-rascunho-v1',
            ])
            ->assertRedirect(route('gestao.louos.rascunho.show', ['quadro' => 'quadro7']));

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/louos/rascunho?quadro=quadro7')
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
        $this->seed(LouosQuadro7Seeder::class);
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho', [
                'quadro' => 'quadro7',
                'version' => '2026-rascunho-v1',
            ]);

        $draft = RuleVersion::query()
            ->where('domain', RuleDomain::LouosQuadro7->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->firstOrFail();

        // Inserir nova linha
        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho/linhas', [
                'quadro' => 'quadro7',
                'cnae_code' => '9999-9/99',
                'area_min' => 0,
                'area_max' => 500,
                'grupo' => 'nR1',
                'subgrupo' => 'nR1-99',
            ])
            ->assertSessionHas('status');

        $linha = LouosQuadro7Faixa::query()
            ->where('rule_version_id', $draft->id)
            ->where('cnae_code', '9999999')
            ->first();

        $this->assertNotNull($linha);

        // A vigente não foi alterada
        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $this->assertNull(
            LouosQuadro7Faixa::query()
                ->where('rule_version_id', $vigente->id)
                ->where('cnae_code', '9999999')
                ->first()
        );

        // Alterar linha no rascunho
        $this->actingAs($admin, 'gestao')
            ->put("/gestao/louos/rascunho/linhas/{$linha->id}", [
                'quadro' => 'quadro7',
                'cnae_code' => '9999-9/99',
                'area_min' => 0,
                'area_max' => 600,
                'grupo' => 'nR1',
                'subgrupo' => 'nR1-99',
            ])
            ->assertSessionHas('status');

        $this->assertSame(600.0, (float) $linha->fresh()->area_max);

        // Excluir linha do rascunho
        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/louos/rascunho/linhas/{$linha->id}", [
                'quadro' => 'quadro7',
            ])
            ->assertSessionHas('status');

        $this->assertNull($linha->fresh());

        // 422 em chave natural duplicada
        $primeiraFaixa = LouosQuadro7Faixa::query()
            ->where('rule_version_id', $draft->id)
            ->first();

        $cnaeFormatado = (string) preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $primeiraFaixa->cnae_code);

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho/linhas', [
                'quadro' => 'quadro7',
                'cnae_code' => $cnaeFormatado,
                'area_min' => (float) $primeiraFaixa->area_min,
                'area_max' => $primeiraFaixa->area_max !== null ? (float) $primeiraFaixa->area_max : null,
                'grupo' => $primeiraFaixa->grupo,
                'subgrupo' => $primeiraFaixa->subgrupo,
            ])
            ->assertSessionHasErrors(['linha']);
    }

    public function test_publicar_com_mesmo_autor_retorna_erro(): void
    {
        $this->seed(LouosQuadro7Seeder::class);
        $admin = $this->administrador();

        // Admin abre o rascunho (created_by = admin)
        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho', [
                'quadro' => 'quadro7',
                'version' => '2026-rascunho-v1',
            ]);

        // Mesmo admin tenta publicar → quatro olhos bloqueado
        $this->actingAs($admin, 'gestao')
            ->put('/gestao/louos/rascunho/publicar', ['quadro' => 'quadro7'])
            ->assertSessionHas('error');

        // Outro admin publica → sucesso e redireciona para louos.index
        $publisher = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($publisher, 'gestao')
            ->put('/gestao/louos/rascunho/publicar', ['quadro' => 'quadro7'])
            ->assertRedirect(route('gestao.louos.index'));
    }

    public function test_importar_csv_retorna_relatorio_em_flash(): void
    {
        $this->seed(LouosQuadro7Seeder::class);
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho', [
                'quadro' => 'quadro7',
                'version' => '2026-rascunho-import',
            ]);

        $csvContent = "cnae,grupo,subgrupo,area_min,area_max,observacao\n4712-1/00,nR1,nR1-02,0,350,Minimercado\n";
        $csvFile = UploadedFile::fake()->createWithContent('quadro7.csv', $csvContent);

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/louos/rascunho/importar', [
                'quadro' => 'quadro7',
                'arquivo' => $csvFile,
            ])
            ->assertRedirect()
            ->assertSessionHas('importacao');

        $flash = session('importacao');
        $this->assertArrayHasKey('importados', $flash);
    }

    public function test_ativar_versao_substituida_torna_vigente(): void
    {
        $this->seed(LouosQuadro7Seeder::class);
        $admin = $this->administrador();

        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro7)->firstOrFail();

        $anterior = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro7,
            'version' => 'lei-9148-2016-quadro7-anterior',
            'status' => RuleVersionStatus::Substituida,
            'valid_from' => now()->subYears(2)->toDateString(),
            'valid_to' => now()->subYear()->toDateString(),
        ]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/louos/versoes/{$anterior->id}/ativar", ['quadro' => 'quadro7'])
            ->assertRedirect(route('gestao.louos.index', ['quadro' => 'quadro7']))
            ->assertSessionHas('status');

        $this->assertSame(RuleVersionStatus::Vigente, $anterior->fresh()->status);
        $this->assertSame(RuleVersionStatus::Substituida, $vigente->fresh()->status);
        $this->assertTrue(RuleVersion::vigente(RuleDomain::LouosQuadro7)->sole()->is($anterior));
    }

    public function test_consultar_louos_lista_historico_de_versoes(): void
    {
        $this->seed(LouosQuadro7Seeder::class);
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/louos?quadro=quadro7')
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
            ->get('/gestao/louos/modelo-csv?quadro=quadro7');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $primeiraLinha = strtok($content, "\n");
        $this->assertSame('cnae,grupo,subgrupo,area_min,area_max,observacao', $primeiraLinha);
    }

    public function test_consulta_nao_lista_mais_quadro11(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/louos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('quadros', 3));
    }
}
