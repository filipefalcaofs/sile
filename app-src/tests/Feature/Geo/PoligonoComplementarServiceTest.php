<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationPrecision;
use App\Enums\PropertyLocationSource;
use App\Enums\StatusResolucaoPoligono;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\Edificacoes\EdificacaoCandidata;
use App\Services\Geo\Edificacoes\PoligonoComplementarService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeBuildingFootprintRepository;
use Tests\TestCase;

class PoligonoComplementarServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FakeBuildingFootprintRepository $base;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.features.edificacoes_overture' => true,
            'viabiliza.features.edificacoes_microsoft' => true,
            'viabiliza.features.edificacoes_ia' => false,
        ]);

        $this->base = new FakeBuildingFootprintRepository;
        $this->app->instance(BuildingFootprintRepository::class, $this->base);
    }

    /**
     * @return array<string, mixed>
     */
    private function predio(): array
    {
        return ['type' => 'MultiPolygon', 'coordinates' => [[[
            [-38.4601, -12.9801], [-38.4599, -12.9801], [-38.4599, -12.9799], [-38.4601, -12.9799], [-38.4601, -12.9801],
        ]]]];
    }

    private function processo(PropertyLocationSource $origem = PropertyLocationSource::CadastroImobiliario, ?PropertyLocationPrecision $precisao = null): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);
        $request->forceFill([
            'property_point_lat' => -12.98,
            'property_point_lng' => -38.46,
            'property_location_source' => $origem,
            'property_location_precision' => $precisao,
        ])->save();

        return $request->fresh();
    }

    private function servico(): PoligonoComplementarService
    {
        return app(PoligonoComplementarService::class);
    }

    public function test_overture_grava_poligono_e_procedencia(): void
    {
        $this->base->comCandidata(FonteEdificacao::Overture, $this->predio(), 135.5, 0.91);
        $request = $this->processo();

        $resultado = $this->servico()->resolver($request);

        $this->assertSame(StatusResolucaoPoligono::Encontrado, $resultado->status);
        $this->assertSame(PoligonoFonte::Overture, $resultado->fonte);
        $request->refresh();
        $this->assertSame($this->predio(), $request->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Overture, $request->address_polygon_source);
        $this->assertNotNull($request->address_polygon_consulted_at);
        $this->assertSame(['overture'], $this->base->consultas);
    }

    public function test_procedencia_tem_fonte_id_release_area_metodo_e_oficial_false(): void
    {
        $this->base->comCandidata(FonteEdificacao::Overture, $this->predio(), 135.5, 0.91, EdificacaoCandidata::MENOR_AREA);
        $candidata = $this->base->candidatas['overture'];
        $request = $this->processo();

        $this->servico()->resolver($request);

        $resolucao = LotPolygonResolution::query()->where('status', StatusResolucaoPoligono::Encontrado)->sole();
        $this->assertSame($request->id, $resolucao->viability_request_id);
        $this->assertSame(PoligonoFonte::Overture, $resolucao->source);
        $this->assertSame($candidata->id, $resolucao->building_footprint_id);
        $this->assertSame($candidata->sourceId, $resolucao->source_id);
        $this->assertSame('release-teste', $resolucao->release);
        $this->assertSame(135.5, $resolucao->area_m2);
        $this->assertSame(0.91, $resolucao->confidence);
        $this->assertSame('menor_area_entre_sobrepostos', $resolucao->method);
        $this->assertFalse($resolucao->official);
        $this->assertSame(-12.98, $resolucao->point_lat);
        $this->assertSame('cadastro_imobiliario', $resolucao->point_origin);
        $this->assertNotNull($resolucao->latency_ms);
    }

    public function test_microsoft_so_quando_overture_nao_acha(): void
    {
        $this->base->comCandidata(FonteEdificacao::Microsoft, $this->predio());
        $request = $this->processo();

        $resultado = $this->servico()->resolver($request);

        $this->assertSame(PoligonoFonte::Microsoft, $resultado->fonte);
        $this->assertSame(['overture', 'microsoft'], $this->base->consultas);
        $this->assertSame(StatusResolucaoPoligono::NaoEncontrado, LotPolygonResolution::query()->where('source', PoligonoFonte::Overture)->sole()->status);
    }

    public function test_ponto_fora_de_predio_nao_grava_e_registra_nao_encontrado(): void
    {
        $request = $this->processo();

        $resultado = $this->servico()->resolver($request);

        $this->assertSame(StatusResolucaoPoligono::NaoEncontrado, $resultado->status);
        $this->assertFalse($resultado->precisaIa);
        $this->assertNull($request->fresh()->address_polygon_geojson);
        $this->assertSame(2, LotPolygonResolution::query()->where('status', StatusResolucaoPoligono::NaoEncontrado)->count());
    }

    public function test_nominatim_sem_numero_pula_as_fontes(): void
    {
        $this->base->comCandidata(FonteEdificacao::Overture, $this->predio());
        $request = $this->processo(PropertyLocationSource::Geocodificacao, PropertyLocationPrecision::Via);

        $resultado = $this->servico()->resolver($request);

        $this->assertSame(StatusResolucaoPoligono::NaoEncontrado, $resultado->status);
        $this->assertSame([], $this->base->consultas);
        $this->assertNull($request->fresh()->address_polygon_geojson);
        $this->assertSame('ponto_inelegivel', LotPolygonResolution::query()->where('source', PoligonoFonte::Overture)->sole()->reason);
    }

    public function test_sem_ponto_nao_consulta_nada(): void
    {
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);

        $resultado = $this->servico()->resolver($request);

        $this->assertSame(StatusResolucaoPoligono::NaoEncontrado, $resultado->status);
        $this->assertSame([], $this->base->consultas);
    }

    public function test_nao_sobrescreve_poligono_existente(): void
    {
        $this->base->comCandidata(FonteEdificacao::Overture, $this->predio());
        $request = $this->processo();
        $lote = ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]]];
        $request->forceFill(['address_polygon_geojson' => $lote, 'address_polygon_source' => PoligonoFonte::Chave])->save();

        $resultado = $this->servico()->resolver($request->fresh());

        $this->assertSame(StatusResolucaoPoligono::Encontrado, $resultado->status);
        $this->assertSame(PoligonoFonte::Chave, $resultado->fonte);
        $this->assertSame($lote, $request->fresh()->address_polygon_geojson);
        $this->assertSame([], $this->base->consultas);
    }

    public function test_fonte_desligada_registra_desligado(): void
    {
        config(['viabiliza.features.edificacoes_overture' => false]);
        $this->base->comCandidata(FonteEdificacao::Overture, $this->predio());
        $request = $this->processo();

        $this->servico()->resolver($request);

        $this->assertSame(['microsoft'], $this->base->consultas);
        $this->assertSame(StatusResolucaoPoligono::Desligado, LotPolygonResolution::query()->where('source', PoligonoFonte::Overture)->sole()->status);
    }

    public function test_todas_desligadas_nao_registra_nada(): void
    {
        config(['viabiliza.features.edificacoes_overture' => false, 'viabiliza.features.edificacoes_microsoft' => false]);

        $resultado = $this->servico()->resolver($this->processo());

        $this->assertSame(StatusResolucaoPoligono::Desligado, $resultado->status);
        $this->assertSame(0, LotPolygonResolution::query()->count());
    }

    public function test_base_indisponivel_registra_e_segue_para_a_proxima(): void
    {
        $this->base->falhando(FonteEdificacao::Overture)->comCandidata(FonteEdificacao::Microsoft, $this->predio());
        $request = $this->processo();

        $resultado = $this->servico()->resolver($request);

        $this->assertSame(PoligonoFonte::Microsoft, $resultado->fonte);
        $this->assertSame(StatusResolucaoPoligono::Indisponivel, LotPolygonResolution::query()->where('source', PoligonoFonte::Overture)->sole()->status);
    }

    public function test_ia_reaproveita_poligono_ja_gerado(): void
    {
        config(['viabiliza.features.edificacoes_ia' => true]);
        $this->base->comCandidata(FonteEdificacao::Ia, $this->predio());
        $request = $this->processo();

        $resultado = $this->servico()->resolver($request);

        $this->assertSame(PoligonoFonte::Ia, $resultado->fonte);
        $this->assertFalse($resultado->precisaIa);
        $this->assertSame(PoligonoFonte::Ia, $request->fresh()->address_polygon_source);
    }

    public function test_ia_sem_poligono_pede_inferencia(): void
    {
        config(['viabiliza.features.edificacoes_ia' => true]);
        $request = $this->processo();

        $resultado = $this->servico()->resolver($request);

        $this->assertSame(StatusResolucaoPoligono::Pendente, $resultado->status);
        $this->assertTrue($resultado->precisaIa);
        $this->assertSame(StatusResolucaoPoligono::Pendente, LotPolygonResolution::query()->where('source', PoligonoFonte::Ia)->sole()->status);
    }

    public function test_duas_resolucoes_simultaneas_gravam_um_poligono(): void
    {
        $request = $this->processo();
        $overture = $this->base->candidata(FonteEdificacao::Overture, $this->predio());
        $outro = ['type' => 'MultiPolygon', 'coordinates' => [[[[-38.47, -12.99], [-38.469, -12.99], [-38.469, -12.989], [-38.47, -12.99]]]]];
        $microsoft = $this->base->candidata(FonteEdificacao::Microsoft, $outro);

        $this->assertTrue($this->servico()->gravar($request, $overture));
        $this->assertFalse($this->servico()->gravar($request, $microsoft));

        $this->assertSame($this->predio(), $request->fresh()->address_polygon_geojson);
        $this->assertSame(1, LotPolygonResolution::query()->where('status', StatusResolucaoPoligono::Encontrado)->count());
    }
}
