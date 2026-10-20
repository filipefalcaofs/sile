<?php

namespace Tests\Feature\Vistoria;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\InspectionReferral;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Vistoria\ConsultaVistoriaPdfService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Capa de consulta do processo aberta pela caixa de vistoria: a mesma tela do
 * detalhe, com o despacho do analista, liberada a quem preenche a ficha ou
 * distribui sem ampliar consultar-solicitacoes.
 */
class VistoriaCapaConsultaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function vistoriadorSemConsulta(): User
    {
        $usuario = User::factory()->withAcceptedLgpdTerm()->create();
        $usuario->givePermissionTo(['acessar-gestao', 'preencher-ficha-vistoria']);

        return $usuario;
    }

    private function atribuir(ViabilityRequest $processo, User $vistoriador): void
    {
        $processo->forceFill(['assigned_user_id' => $vistoriador->id])->save();
    }

    private function processo(AnalysisStatus $status): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => $status,
        ])->save();

        return $processo;
    }

    private function despachar(ViabilityRequest $processo, string $orientacoes): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'Marina Analista']);
        $origem = Sector::factory()->create(['name' => 'Setor de Análise']);
        $vistoria = Sector::factory()->create(['name' => 'Setor de Vistoria']);

        InspectionReferral::create([
            'viability_request_id' => $processo->id,
            'encaminhado_por_user_id' => $analista->id,
            'setor_origem_id' => $origem->id,
            'analista_origem_user_id' => $analista->id,
            'setor_vistoria_id' => $vistoria->id,
            'motivo' => $orientacoes,
        ]);
    }

    public function test_vistoriador_abre_a_capa_com_o_despacho_sem_consultar_solicitacoes(): void
    {
        $vistoriador = $this->vistoriadorSemConsulta();
        $processo = $this->processo(AnalysisStatus::Vistoriar);
        $this->despachar($processo, 'Conferir o depósito de GLP nos fundos.');
        $this->atribuir($processo, $vistoriador);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/vistorias/{$processo->id}/consulta")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('gestao/processos/show')
                ->where('consultaVistoria', true)
                ->where('despachoVistoria.orientacoes', 'Conferir o depósito de GLP nos fundos.')
                ->where('despachoVistoria.analista', 'Marina Analista')
                ->where('despachoVistoria.setor_origem', 'Setor de Análise')
                ->where('despachoVistoria.setor_vistoria', 'Setor de Vistoria'));

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertForbidden();
    }

    public function test_apoio_que_distribui_abre_a_capa(): void
    {
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $processo = $this->processo(AnalysisStatus::Vistoriado);

        $this->actingAs($apoio, 'gestao')
            ->get("/gestao/vistorias/{$processo->id}/consulta")
            ->assertOk();
    }

    public function test_capa_sem_encaminhamento_registrado_abre_sem_despacho(): void
    {
        $processo = $this->processo(AnalysisStatus::Vistoriar);
        $vistoriador = $this->vistoriadorSemConsulta();
        $this->atribuir($processo, $vistoriador);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/vistorias/{$processo->id}/consulta")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('despachoVistoria', null));
    }

    public function test_capa_exige_permissao_de_vistoria_ou_distribuicao(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo(['acessar-gestao', 'consultar-solicitacoes']);
        $processo = $this->processo(AnalysisStatus::Vistoriar);

        $this->actingAs($semPermissao, 'gestao')
            ->get("/gestao/vistorias/{$processo->id}/consulta")
            ->assertForbidden();
    }

    public function test_capa_recusa_processo_fora_do_eixo_de_vistoria(): void
    {
        $processo = $this->processo(AnalysisStatus::EmAnalise);

        $this->actingAs($this->vistoriadorSemConsulta(), 'gestao')
            ->get("/gestao/vistorias/{$processo->id}/consulta")
            ->assertNotFound();
    }

    public function test_abrir_a_capa_nao_altera_o_processo(): void
    {
        $processo = $this->processo(AnalysisStatus::Vistoriar);
        $vistoriador = $this->vistoriadorSemConsulta();
        $this->atribuir($processo, $vistoriador);
        $antes = $processo->refresh()->only(['status', 'analysis_status', 'sector_id', 'assigned_user_id']);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/vistorias/{$processo->id}/consulta")
            ->assertOk();

        $this->assertSame($antes, $processo->refresh()->only(['status', 'analysis_status', 'sector_id', 'assigned_user_id']));
        $this->assertDatabaseCount('analysis_status_transitions', 0);
    }

    public function test_pdf_da_consulta_baixa_com_despacho_no_layout_de_relatorio(): void
    {
        $processo = $this->processo(AnalysisStatus::Vistoriar);
        $this->despachar($processo, 'Conferir o depósito de GLP nos fundos.');
        $vistoriador = $this->vistoriadorSemConsulta();
        $this->atribuir($processo, $vistoriador);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/vistorias/{$processo->id}/consulta/imprimir")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload(app(ConsultaVistoriaPdfService::class)->nomeDoArquivo($processo));

        $dados = app(ConsultaVistoriaPdfService::class)->dados($processo, null);
        $html = view('documentos.consulta-vistoria', $dados)->render();

        $this->assertStringContainsString('Orientações do analista para a vistoria', $html);
        $this->assertStringContainsString('Conferir o depósito de GLP nos fundos.', $html);
        $this->assertStringContainsString('Marina Analista', $html);
        $this->assertStringContainsString((string) $processo->protocol_number, $html);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vistoria', 'event' => 'consulta-imprimir']);
    }

    public function test_pdf_sem_encaminhamento_informa_que_nao_ha_orientacoes(): void
    {
        $processo = $this->processo(AnalysisStatus::Vistoriar);

        $html = view('documentos.consulta-vistoria', app(ConsultaVistoriaPdfService::class)->dados($processo, null))->render();

        $this->assertStringContainsString('Nenhum encaminhamento à vistoria registrado', $html);
    }

    public function test_pdf_exige_permissao_e_eixo_de_vistoria_e_nao_altera_o_processo(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo(['acessar-gestao', 'consultar-solicitacoes']);
        $naVistoria = $this->processo(AnalysisStatus::Vistoriar);
        $fora = $this->processo(AnalysisStatus::EmAnalise);

        $this->actingAs($semPermissao, 'gestao')
            ->get("/gestao/vistorias/{$naVistoria->id}/consulta/imprimir")
            ->assertForbidden();

        $vistoriador = $this->vistoriadorSemConsulta();
        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/vistorias/{$fora->id}/consulta/imprimir")
            ->assertNotFound();

        $this->atribuir($naVistoria, $vistoriador);
        $antes = $naVistoria->refresh()->only(['status', 'analysis_status', 'sector_id', 'assigned_user_id']);
        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/vistorias/{$naVistoria->id}/consulta/imprimir")
            ->assertOk();
        $this->assertSame($antes, $naVistoria->refresh()->only(['status', 'analysis_status', 'sector_id', 'assigned_user_id']));
    }

    public function test_pdf_traz_os_campos_da_tela_camadas_parecer_e_vistoriador_nao_distribuido(): void
    {
        $processo = $this->processo(AnalysisStatus::Vistoriar);
        $processo->forceFill([
            'contingency_reason' => 'Sistema da JUCEB fora do ar.',
            'camadas_incidentes' => [
                'zona' => ['status' => 'identificado', 'nome' => 'ZM-2', 'fonte' => 'ponto', 'porcoes' => []],
                'bairro' => ['status' => 'identificado', 'nome' => 'Pituba'],
                'via' => 'Via local',
                'camadas' => [['regra' => 'borda', 'titulo' => 'Borda marítima', 'status' => 'dentro', 'elemento' => 'Trecho 3', 'incide' => true]],
            ],
        ])->save();
        ViabilityDecision::factory()->indeferida()->create(['viability_request_id' => $processo->id]);

        $dados = app(ConsultaVistoriaPdfService::class)->dados($processo->fresh(), null);
        $html = view('documentos.consulta-vistoria', $dados)->render();

        foreach (['Nº da viabilidade', 'Origem', 'Analista responsável', 'Prazo (SLA)', 'Parecer da decisão', 'Indeferida',
            'Camadas do imóvel', 'ZM-2 — ponto da inscrição', 'Pituba', 'Borda marítima', 'Trecho 3 — regra incide',
            'Motivo da contingência', 'Sistema da JUCEB fora do ar.', 'Não distribuído'] as $texto) {
            $this->assertStringContainsString($texto, $html, $texto);
        }
    }
}
