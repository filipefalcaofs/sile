<?php

namespace Tests\Feature\Vistoria;

use App\Enums\AnalysisStatus;
use App\Models\Inspection;
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
        $processo->forceFill(['analysis_status' => $status])->save();

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

    public function test_consulta_renderiza_universo_kpis_e_situacao(): void
    {
        $this->cenario();

        $this->actingAs($this->vistoriador, 'gestao')
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
                ->has('abas')
                ->has('filtros'));
    }

    public function test_consulta_filtra_por_aba(): void
    {
        $this->cenario();

        $this->actingAs($this->vistoriador, 'gestao')
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

    public function test_consulta_exige_permissao(): void
    {
        $apoio = User::factory()->withAcceptedLgpdTerm()->create();
        $apoio->assignRole('apoio');

        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/vistorias')
            ->assertForbidden();
    }
}
