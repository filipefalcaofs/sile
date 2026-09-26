<?php

namespace Tests\Feature\Geo;

use App\Enums\PropertyLocationSource;
use App\Models\ViabilityRequest;
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
}
