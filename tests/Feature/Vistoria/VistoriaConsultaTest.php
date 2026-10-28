<?php

namespace Tests\Feature\Vistoria;

use App\Enums\AnalysisStatus;
use App\Models\Inspection;
use App\Models\InspectionReferral;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Consulta de vistorias (fila do vistoriador): universo = processos no eixo
 * operacional de vistoria (Vistoriar/Vistoriado). Situação derivada da ficha:
 * a designar (sem ficha), em campo (ficha em preenchimento), concluída (ficha
 * concluída). KPIs do universo, busca e abas server-driven, gated por
 * preencher-ficha-vistoria e auditado (RN-002).
 */
class VistoriaConsultaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $vistoriador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->vistoriador = User::factory()->analista()->withAcceptedLgpdTerm()->create([
            'name' => 'Carlos Alberto da Silva Santos',
        ]);
    }

    private function processoEmVistoria(AnalysisStatus $status, array $attrs = []): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create($attrs);
        $setorVistoria = Sector::factory()->create();
        $processo->forceFill(['analysis_status' => $status, 'sector_id' => $setorVistoria->id])->save();

        if ($status === AnalysisStatus::Vistoriado) {
            InspectionReferral::query()->forceCreate([
                'viability_request_id' => $processo->id,
                'encaminhado_por_user_id' => $this->vistoriador->id,
                'setor_origem_id' => Sector::factory()->create()->id,
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Conferir no local.',
            ]);
        }

        return $processo;
    }

    /**
     * Cenário: 1 a designar (sem ficha), 1 em campo (ficha em preenchimento),
     * 1 concluída no mês e 1 fora do universo (em análise, sem vistoria).
     *
     * @return array{designar: ViabilityRequest, campo: ViabilityRequest, concluida: ViabilityRequest, fora: ViabilityRequest}
     */
    private function cenario(): array
    {
        $designar = $this->processoEmVistoria(AnalysisStatus::Vistoriar, [
            'protocol_number' => 'VIA-2026-000101',
            'analysis_due_at' => now()->subDay(),
        ]);

        $campo = $this->processoEmVistoria(AnalysisStatus::Vistoriar, [
            'protocol_number' => 'VIA-2026-000102',
        ]);
        Inspection::factory()->create([
            'viability_request_id' => $campo->id,
            'vistoriador_user_id' => $this->vistoriador->id,
        ]);

        $concluida = $this->processoEmVistoria(AnalysisStatus::Vistoriado, [
            'protocol_number' => 'VIA-2026-000103',
        ]);
        Inspection::factory()->concluida()->create([
            'viability_request_id' => $concluida->id,
            'vistoriador_user_id' => $this->vistoriador->id,
        ]);

        $fora = $this->processoEmVistoria(AnalysisStatus::EmAnalise, [
            'protocol_number' => 'VIA-2026-000104',
        ]);

        return compact('designar', 'campo', 'concluida', 'fora');
    }

    private function apoio(): User
    {
        $apoio = User::factory()->withAcceptedLgpdTerm()->create();
        $apoio->assignRole('apoio');

        return $apoio;
    }

    public function test_quem_distribui_ve_todo_o_universo(): void
    {
        $this->cenario();

        $this->actingAs($this->apoio(), 'gestao')
            ->get('/gestao/vistorias')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/vistorias/index')
                ->has('vistorias.data', 3)
                ->where('kpis.encaminhadas', 3)
                ->where('kpis.a_designar', 1)
                ->where('kpis.em_campo', 1)
                ->where('kpis.concluidas', 1)
                ->where('kpis.concluidas_mes', 1)
                ->where('kpis.prazo_vencido', 1)
                ->where('abas.1.id', 'designar')
                ->has('filtros'));
    }

    public function test_vistoriador_ve_so_os_processos_dele(): void
    {
        $cenario = $this->cenario();
        $outroVistoriador = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $alheio = $this->processoEmVistoria(AnalysisStatus::Vistoriar, ['protocol_number' => 'VIA-2026-000105']);
        Inspection::factory()->create([
            'viability_request_id' => $alheio->id,
            'vistoriador_user_id' => $outroVistoriador->id,
        ]);
        $atribuido = $this->processoEmVistoria(AnalysisStatus::Vistoriar, ['protocol_number' => 'VIA-2026-000106']);
        $atribuido->forceFill(['assigned_user_id' => $this->vistoriador->id])->save();

        $resposta = $this->actingAs($this->vistoriador, 'gestao')
            ->get('/gestao/vistorias')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 3)
                ->where('kpis.encaminhadas', 3)
                ->where('kpis.a_designar', 0));

        $props = $resposta->viewData('page')['props'];
        $ids = array_column($props['vistorias']['data'], 'id');
        $this->assertEqualsCanonicalizing([$cenario['campo']->id, $cenario['concluida']->id, $atribuido->id], $ids);
        $this->assertNotContains('designar', array_column($props['abas'], 'id'));
    }

    public function test_consulta_filtra_por_aba(): void
    {
        $this->cenario();

        $this->actingAs($this->apoio(), 'gestao')
            ->get('/gestao/vistorias?situacao=designar')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.situacao', 'designar'));

        $this->actingAs($this->vistoriador, 'gestao')
            ->get('/gestao/vistorias?situacao=campo')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.situacao', 'em_campo')
                ->where('vistorias.data.0.vistoriador', 'Carlos Alberto da Silva Santos'));

        $this->actingAs($this->vistoriador, 'gestao')
            ->get('/gestao/vistorias?situacao=concluida')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.situacao', 'concluida'));
    }

    public function test_consulta_busca_por_protocolo(): void
    {
        $this->cenario();

        $this->actingAs($this->vistoriador, 'gestao')
            ->get('/gestao/vistorias?busca=VIA-2026-000102')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.protocol_number', 'VIA-2026-000102'));
    }

    public function test_consulta_busca_por_vistoriador(): void
    {
        $this->cenario();

        $this->actingAs($this->vistoriador, 'gestao')
            ->get('/gestao/vistorias?busca=Carlos')
            ->assertInertia(fn (Assert $page) => $page->has('vistorias.data', 2));
    }

    public function test_processo_devolvido_a_caixa_do_setor_sai_da_caixa_de_vistoria(): void
    {
        $cenario = $this->cenario();
        $devolvido = $cenario['concluida'];
        $devolvido->forceFill(['sector_id' => InspectionReferral::query()->where('viability_request_id', $devolvido->id)->value('setor_origem_id')])->save();

        $resposta = $this->actingAs($this->apoio(), 'gestao')->get('/gestao/vistorias')->assertOk();

        $ids = array_column($resposta->viewData('page')['props']['vistorias']['data'], 'id');
        $this->assertNotContains($devolvido->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_consulta_exige_permissao(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo(['acessar-gestao', 'consultar-solicitacoes']);

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/vistorias')
            ->assertForbidden();
    }

    public function test_apoio_que_distribui_tambem_abre_a_caixa(): void
    {
        $apoio = User::factory()->withAcceptedLgpdTerm()->create();
        $apoio->assignRole('apoio');

        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/vistorias')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('podeDistribuir', true)
                ->where('distribuiEmQualquerSetor', true));
    }
}
