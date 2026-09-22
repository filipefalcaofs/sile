<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisCategory;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Http\Resources\ProcessoResource;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Services\Analise\ProcessoQueryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Consulta de processos da retaguarda (HU-082): a SEDUR busca processos com os
 * filtros completos do SAPS (status, número do processo, BAP, produto TVL,
 * serviço, setor, inscrição, nome, CNPJ, CEP, logradouro, bairro, datas) MAIS o
 * filtro por analista responsável e por categoria derivada (Expresso,
 * Semi-Expresso, Malha Fina, Sede de Escritório). Server-driven (espelha o
 * ResultadoExpressoController): paginação no servidor, whereLike
 * caseSensitive:false (PostgreSQL) e CSV simples do conjunto filtrado. Gated por
 * consultar-solicitacoes (reuso — sem permissão nova); 403 auditado no ponto
 * único (CA-04) e a consulta auditada (CA-02). A tela é construída em 10-16.
 */
class ProcessoConsultaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Cria um processo (em análise por padrão) com protocolo único e os atributos
     * de análise (fora do fillable) gravados via forceFill.
     *
     * @param  array<string, mixed>  $attrs
     * @param  array<string, mixed>  $analysis
     */
    private function processo(array $attrs = [], array $analysis = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $request = ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ], $attrs));

        if ($analysis !== []) {
            $request->forceFill($analysis)->save();
        }

        return $request;
    }

    public function test_sem_permissao_consultar_solicitacoes_recebe_403_auditado(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/processos')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_com_permissao_lista_processos_e_renderiza_a_pagina(): void
    {
        $this->processo();
        $this->processo();

        // Inspeciona as props sem exigir o componente .tsx (a tela é 10-16),
        // como o CaixaSetorTest faz para os endpoints que precedem a UI.
        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/processos/index', $page['component']);
        $this->assertCount(2, $page['props']['processos']['data']);
        $this->assertSame(2, $page['props']['processos']['total']);
        $this->assertArrayHasKey('perPageOptions', $page['props']);
        $this->assertArrayHasKey('categoriaOptions', $page['props']);

        // A consulta é auditada (CA-02 / RN-002).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'consulta-processos',
            'result' => 'sucesso',
        ]);
    }

    public function test_filtra_por_status(): void
    {
        $emAnalise = $this->processo(['status' => ViabilityRequestStatus::EmAnalise]);
        $this->processo(['status' => ViabilityRequestStatus::Deferida]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?status=em_analise')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $emAnalise->id));
    }

    public function test_filtra_por_analista_responsavel(): void
    {
        $analistaA = $this->analista();
        $analistaB = $this->analista();

        $doA = $this->processo(analysis: ['assigned_user_id' => $analistaA->id]);
        $this->processo(analysis: ['assigned_user_id' => $analistaB->id]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?analista='.$analistaA->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $doA->id));
    }

    public function test_filtra_por_categoria_malha_fina_derivada(): void
    {
        $malhaFina = $this->processo(analysis: ['in_fine_mesh' => true]);
        $this->processo(analysis: ['in_fine_mesh' => false, 'analysis_category' => AnalysisCategory::Expresso]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?categoria=malha_fina')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $malhaFina->id));
    }

    public function test_filtra_por_categoria_expresso(): void
    {
        $expresso = $this->processo(analysis: ['analysis_category' => AnalysisCategory::Expresso]);
        $this->processo(analysis: ['analysis_category' => AnalysisCategory::SemiExpresso]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?categoria=expresso')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $expresso->id));
    }

    public function test_busca_por_protocolo_e_case_insensitive(): void
    {
        $alvo = $this->processo(['protocol_number' => 'VIA-'.now()->year.'-009123']);
        $this->processo(['protocol_number' => 'VIA-'.now()->year.'-009999']);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?protocolo=via-'.now()->year.'-009123')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $alvo->id));
    }

    public function test_filtra_por_cnpj_da_empresa(): void
    {
        $empresa = Company::factory()->create(['cnpj' => '11222333000181']);
        $alvo = $this->processo(['company_id' => $empresa->id]);
        $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?cnpj=11222333')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $alvo->id));
    }

    public function test_paginacao_server_side_expoe_meta(): void
    {
        $this->processo();
        $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?per_page=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processos.total', 2)
                ->where('processos.per_page', 10)
                ->where('processos.from', 1)
                ->where('processos.to', 2)
                ->where('filtros.per_page', 10));
    }

    public function test_exporta_csv_do_conjunto_filtrado(): void
    {
        $alvo = $this->processo(['status' => ViabilityRequestStatus::EmAnalise]);
        $this->processo(['status' => ViabilityRequestStatus::Deferida]);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?status=em_analise&formato=csv')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $conteudo = $response->streamedContent();
        $this->assertStringContainsString('Processo', $conteudo);
        $this->assertStringContainsString($alvo->protocol_number, $conteudo);

        // A exportação também é auditada.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'exporta-processos-csv',
            'result' => 'sucesso',
        ]);
    }

    public function test_show_detalha_o_processo(): void
    {
        $processo = $this->processo();

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/processos/show', $page['component']);
        $this->assertSame($processo->id, $page['props']['processo']['id']);
        $this->assertSame($processo->protocol_number, $page['props']['processo']['protocol_number']);
        $this->assertArrayHasKey('categorias', $page['props']['processo']);
    }

    public function test_resource_expoe_analysis_status_e_label(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::EmAnalise])->save();

        $payload = (new ProcessoResource($request->fresh()))->resolve();

        $this->assertSame('em_analise', $payload['analysis_status']);
        $this->assertSame('Em análise', $payload['analysis_status_label']);
    }

    public function test_resource_em_analise_sem_decisao_expoe_motivo_do_encaminhamento(): void
    {
        $request = $this->processo();
        ViabilityRequestTransition::factory()->create([
            'viability_request_id' => $request->id,
            'from_status' => ViabilityRequestStatus::Protocolada,
            'to_status' => ViabilityRequestStatus::EmAnalise,
            'reason' => 'veredito locacional pendente — zona urbanística pendente SEDUR',
        ]);

        $payload = (new ProcessoResource($request->fresh(['decision', 'encaminhamentoAnalise'])))->resolve();

        $this->assertTrue($payload['sem_decisao_automatica']);
        $this->assertSame(
            'veredito locacional pendente — zona urbanística pendente SEDUR',
            $payload['motivo_encaminhamento'],
        );
    }

    public function test_resource_com_decisao_nao_marca_sem_decisao_automatica(): void
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
        ]);

        $payload = (new ProcessoResource($request->fresh(['decision', 'encaminhamentoAnalise'])))->resolve();

        $this->assertFalse($payload['sem_decisao_automatica']);
        $this->assertNull($payload['motivo_encaminhamento']);
    }

    public function test_lista_e_detalhe_expoem_aviso_quando_em_analise_sem_decisao(): void
    {
        $processo = $this->processo();
        ViabilityRequestTransition::factory()->create([
            'viability_request_id' => $processo->id,
            'from_status' => ViabilityRequestStatus::Protocolada,
            'to_status' => ViabilityRequestStatus::EmAnalise,
            'reason' => 'veredito locacional pendente — zona urbanística pendente SEDUR',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processos.data.0.sem_decisao_automatica', true)
                ->where(
                    'processos.data.0.motivo_encaminhamento',
                    'veredito locacional pendente — zona urbanística pendente SEDUR',
                ));

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processo.sem_decisao_automatica', true)
                ->where(
                    'processo.motivo_encaminhamento',
                    'veredito locacional pendente — zona urbanística pendente SEDUR',
                ));
    }

    public function test_filtra_por_analysis_status(): void
    {
        $comStatus = ViabilityRequest::factory()->create();
        $comStatus->forceFill(['analysis_status' => AnalysisStatus::EmConvite])->save();
        $outro = ViabilityRequest::factory()->create();
        $outro->forceFill(['analysis_status' => AnalysisStatus::EmAnalise])->save();

        $resultado = app(ProcessoQueryService::class)
            ->filtered(['analysis_status' => 'em_convite'])
            ->pluck('id');

        $this->assertTrue($resultado->contains($comStatus->id));
        $this->assertFalse($resultado->contains($outro->id));
    }

    public function test_busca_unica_encontra_empresa_ou_bap(): void
    {
        $empresa = Company::factory()->create([
            'legal_name' => 'Padaria Boa Vista LTDA',
            'trade_name' => 'Padaria Boa Vista',
        ]);
        $porEmpresa = $this->processo(['company_id' => $empresa->id]);
        $porBap = $this->processo(['external_reference' => '5921000030-00990019/2026']);
        $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?busca=Boa%20Vista')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $porEmpresa->id));

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?busca=00990019')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $porBap->id));
    }

    public function test_abas_contam_o_recorte_sem_aplicar_a_propria_aba(): void
    {
        $this->processo(['status' => ViabilityRequestStatus::EmAnalise]);
        $paraDistribuir = $this->processo(['status' => ViabilityRequestStatus::EmAnalise]);
        $paraDistribuir->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();
        $this->processo(['status' => ViabilityRequestStatus::EmPendencia]);
        $this->processo(['status' => ViabilityRequestStatus::Deferida]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?status=em_pendencia')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('abas.todos', 4)
                ->where('abas.em_analise', 2)
                ->where('abas.distribuir', 1)
                ->where('abas.pendencia', 1)
                ->where('abas.concluido', 1));
    }

    public function test_ordena_pelo_prazo_mais_curto(): void
    {
        $tarde = $this->processo();
        $tarde->forceFill(['analysis_due_at' => now()->addDays(10)])->save();
        $cedo = $this->processo();
        $cedo->forceFill(['analysis_due_at' => now()->addDay()])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?ordem=prazo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processos.data.0.id', $cedo->id)
                ->where('filtros.ordem', 'prazo'));
    }

    public function test_filtra_pelo_fluxo_gravado_na_decisao_ou_pelo_status_em_analise(): void
    {
        $expresso = $this->processo(['status' => ViabilityRequestStatus::Deferida]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $expresso->id,
            'flow' => 'expresso',
            'tvl_product_number' => 'TVL-'.now()->year.'-900001',
        ]);

        $naFila = $this->processo(['status' => ViabilityRequestStatus::EmAnalise]);

        $tecnica = $this->processo(['status' => ViabilityRequestStatus::Deferida]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $tecnica->id,
            'flow' => 'analise_tecnica',
            'tvl_product_number' => 'TVL-'.now()->year.'-900002',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?fluxo=expresso')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $expresso->id)
                ->where('processos.data.0.fluxo', 'expresso'));

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?fluxo=em_analise')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $naFila->id)
                ->where('processos.data.0.fluxo', 'em_analise'));

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?fluxo=analise_tecnica')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $tecnica->id)
                ->where('processos.data.0.fluxo', 'analise_tecnica'));
    }
}
