<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\PropertyLocationSource;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\DistribuicaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
                ->where('geo.poligono.type', 'Polygon')
                ->where('geo.ponto', null));
    }

    public function test_detalhe_mostra_o_parecer_com_o_motivo_do_indeferimento(): void
    {
        $processo = $this->processo(['status' => ViabilityRequestStatus::Indeferida]);
        ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => $processo->id,
            'reason' => 'A solicitação está localizada no logradouro 212 – Avenida Lafayette Coutinho.',
            'fundamentacao' => ['art. 172-A da Lei Municipal nº 9.148/2016'],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('parecer.outcome', 'indeferida')
                ->where('parecer.outcome_label', 'Indeferida')
                ->where('parecer.motivo', 'A solicitação está localizada no logradouro 212 – Avenida Lafayette Coutinho.')
                ->where('parecer.fundamentacao.0', 'art. 172-A da Lei Municipal nº 9.148/2016'));
    }

    public function test_detalhe_mostra_quem_distribuiu_o_processo_na_tramitacao(): void
    {
        $setor = Sector::factory()->create();
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $apoio->sectors()->attach($setor);
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'Lisa Sousa']);
        $analista->sectors()->attach($setor);
        $protocolador = User::factory()->create(['name' => 'Filipe Protocolo']);

        $processo = $this->processo([
            'sector_id' => $setor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
        ], [
            'analysis_stage' => AnalysisStage::Distribuicao,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDays(2),
        ]);

        $processo->transitions()->create([
            'from_status' => ViabilityRequestStatus::Rascunho,
            'to_status' => ViabilityRequestStatus::Protocolada,
            'actor_user_id' => $protocolador->id,
        ]);
        $processo->transitions()->create([
            'from_status' => ViabilityRequestStatus::Protocolada,
            'to_status' => ViabilityRequestStatus::EmAnalise,
            'reason' => 'veredito locacional pendente',
        ]);

        $this->actingAs($apoio, 'gestao');
        app(DistribuicaoService::class)->distribuir($processo, $analista, $apoio);

        $timeline = $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->viewData('page')['props']['timeline'];

        $emAnalise = collect($timeline)->firstWhere('to', ViabilityRequestStatus::EmAnalise->value);
        $protocolada = collect($timeline)->firstWhere('to', ViabilityRequestStatus::Protocolada->value);

        $this->assertSame($apoio->name, $emAnalise['distribuidor']);
        $this->assertSame('Lisa Sousa', $emAnalise['atribuido']);
        $this->assertNull($protocolada['distribuidor']);
    }

    public function test_detalhe_mostra_o_ponto_do_cadastro_quando_nao_ha_poligono(): void
    {
        $processo = $this->processo(['property_polygon_geojson' => null]);
        $processo->forceFill([
            'property_point_lat' => -12.977769,
            'property_point_lng' => -38.516404,
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
        ])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/show')
                ->where('geo.poligono', null)
                ->where('geo.ponto.lat', -12.977769)
                ->where('geo.ponto.lng', -38.516404)
                ->where('geo.origem.value', 'cadastro_imobiliario')
                ->where('geo.origem.label', 'Cadastro Imobiliário'));
    }

    public function test_detalhe_mostra_as_camadas_do_imovel(): void
    {
        $processo = $this->processo();
        $processo->forceFill(['camadas_incidentes' => [
            'logradouro_tributario' => ['cod_log' => '212', 'nome' => 'Avenida Lafayete Coutinho'],
            'camadas' => [['regra' => 'RT-03-ABM', 'status' => 'dentro', 'elemento' => 'Trecho 5', 'incide' => true]],
        ]])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('geo.camadas.logradouro_tributario.nome', 'Avenida Lafayete Coutinho')
                ->where('geo.camadas.camadas.0.elemento', 'Trecho 5'));
    }

    public function test_ca_fc_02_detalhe_expoe_o_poligono_do_enderecamento(): void
    {
        $processo = $this->processo();
        $processo->forceFill(['address_polygon_geojson' => ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]]]])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('geo.poligono_enderecamento.type', 'Polygon'));
    }
}
