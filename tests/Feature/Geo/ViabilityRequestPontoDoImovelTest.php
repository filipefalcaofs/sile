<?php

namespace Tests\Feature\Geo;

use App\Enums\LotePoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoLoteHit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ViabilityRequestPontoDoImovelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ponto_do_imovel_e_o_centroide_do_poligono_quando_existe(): void
    {
        $request = ViabilityRequest::factory()->create();

        $ponto = $request->pontoDoImovel();

        $this->assertEqualsWithDelta(-12.9710, $ponto['lat'], 0.0000001);
        $this->assertEqualsWithDelta(-38.5107, $ponto['lng'], 0.0000001);
    }

    public function test_ponto_do_imovel_e_o_ponto_gravado_quando_nao_ha_poligono(): void
    {
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);
        $request->forceFill([
            'property_point_lat' => -12.977769,
            'property_point_lng' => -38.516404,
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
        ])->save();

        $ponto = $request->fresh()->pontoDoImovel();

        $this->assertEqualsWithDelta(-12.977769, $ponto['lat'], 0.0000001);
        $this->assertEqualsWithDelta(-38.516404, $ponto['lng'], 0.0000001);
        $this->assertSame(PropertyLocationSource::CadastroImobiliario, $request->fresh()->property_location_source);
    }

    public function test_sem_poligono_e_sem_ponto_nao_ha_ponto_do_imovel(): void
    {
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);

        $this->assertNull($request->pontoDoImovel());
    }

    /**
     * O ponto tem que sair da MESMA geometria que zoneia (poligonoDoLote), ou
     * o processo acaba com a zona decidida pelo lote oficial e as poligonais
     * territoriais avaliadas pelo centroide do desenho do cidadão.
     */
    public function test_ponto_sai_do_lote_oficial_e_nao_do_desenho(): void
    {
        $request = ViabilityRequest::factory()->create();

        $request->forceFill([
            'lote_poligonos' => [
                LotePoligonoFonte::Semap->value => [
                    'status' => PoligonoLoteHit::ENCONTRADO,
                    'geojson' => [
                        'type' => 'Polygon',
                        'coordinates' => [[
                            [-38.400000, -12.900000],
                            [-38.400000, -12.800000],
                            [-38.300000, -12.800000],
                            [-38.300000, -12.900000],
                            [-38.400000, -12.900000],
                        ]],
                    ],
                ],
            ],
            'lote_poligono_fonte' => LotePoligonoFonte::Semap,
        ])->save();

        $ponto = $request->fresh()->pontoDoImovel();

        $this->assertEqualsWithDelta(-12.85, $ponto['lat'], 0.0000001);
        $this->assertEqualsWithDelta(-38.35, $ponto['lng'], 0.0000001);
    }

    public function test_multipolygon_usa_o_anel_externo_do_primeiro_poligono(): void
    {
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);

        $request->forceFill([
            'lote_poligonos' => [
                LotePoligonoFonte::Semap->value => [
                    'status' => PoligonoLoteHit::ENCONTRADO,
                    'geojson' => [
                        'type' => 'MultiPolygon',
                        'coordinates' => [
                            [[
                                [-38.400000, -12.900000],
                                [-38.400000, -12.800000],
                                [-38.300000, -12.800000],
                                [-38.300000, -12.900000],
                                [-38.400000, -12.900000],
                            ]],
                        ],
                    ],
                ],
            ],
            'lote_poligono_fonte' => LotePoligonoFonte::Semap,
        ])->save();

        $ponto = $request->fresh()->pontoDoImovel();

        $this->assertEqualsWithDelta(-12.85, $ponto['lat'], 0.0000001);
        $this->assertEqualsWithDelta(-38.35, $ponto['lng'], 0.0000001);
    }
}
