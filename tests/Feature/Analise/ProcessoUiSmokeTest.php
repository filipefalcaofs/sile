<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Smoke do contrato página↔props da UI de retaguarda (10-16): garante que a
 * consulta (HU-082), a fila (HU-144) e o detalhe renderizam o componente Inertia
 * correto com as props essenciais que as telas React consomem. As asserções de
 * comportamento (filtros, semáforo, segurança) já estão em 10-14
 * (ProcessoConsultaTest/ProcessoFilaTest); aqui validamos só o casamento entre as
 * páginas e o backend — incluindo a geometria do imóvel exposta no detalhe para
 * o mini-mapa Leaflet (renderização real, não fachada).
 */
class ProcessoUiSmokeTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_consulta_renderiza_o_componente_com_as_props_essenciais(): void
    {
        $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/index')
                ->has('processos.data', 1)
                ->has('processos.links')
                ->has('filtros.per_page')
                ->has('perPageOptions')
                ->has('statusOptions')
                ->has('categoriaOptions'));
    }

    public function test_fila_renderiza_o_componente_com_contadores_e_modo(): void
    {
        $this->processo(analysis: [
            'analysis_stage' => AnalysisStage::Analise,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDays(3),
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos/fila?modo=meus')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/fila')
                ->where('modo', 'meus')
                ->has('processos')
                ->has('contadores.aguardando_analise')
                ->has('contadores.em_analise')
                ->has('contadores.em_pendencia')
                ->has('contadores.vencendo_hoje'));
    }

    public function test_detalhe_renderiza_o_componente_com_identificadores_timeline_e_geometria(): void
    {
        $poligono = [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5014, -12.9714],
                [-38.5014, -12.9710],
                [-38.5010, -12.9710],
                [-38.5010, -12.9714],
                [-38.5014, -12.9714],
            ]],
        ];

        $processo = $this->processo(['property_polygon_geojson' => $poligono]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/show')
                ->where('processo.id', $processo->id)
                ->where('processo.protocol_number', $processo->protocol_number)
                ->has('processo.bap')
                ->has('processo.tvl_product_number')
                ->has('timeline')
                ->where('geo.poligono.type', 'Polygon'));
    }
}
