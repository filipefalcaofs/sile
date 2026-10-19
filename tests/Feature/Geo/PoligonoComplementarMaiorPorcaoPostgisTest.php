<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Enums\ZonaFonte;
use App\Models\GeoServerLayer;
use App\Models\ViabilityRequest;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\Edificacoes\PoligonoComplementarService;
use App\Services\Geo\ZonaProcessoService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * O polígono da edificação complementar entra na maior porção (art. 18, § 2º)
 * igual ao lote da SEDUR, e a zona continua com a fonte "maior porção do lote".
 */
#[Group('postgis')]
class PoligonoComplementarMaiorPorcaoPostgisTest extends PostgisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.features.geoserver_zona' => true,
            'viabiliza.features.geoserver_enderecamento' => true,
            'viabiliza.features.edificacoes_overture' => true,
        ]);

        GeoServerLayer::factory()->create(['workspace' => 'louos_zpr3', 'type_name' => 'VM_L_Z_USO_ZPR_3', 'ordem' => 1]);
        GeoServerLayer::factory()->create(['workspace' => 'louos_zeis', 'type_name' => 'VM_L_Z_USO_ZEIS', 'ordem' => 2]);

        // ZPR 3 a oeste de -38.49930, ZEIS a leste.
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $faixa = match ($query['typeName'] ?? '') {
                'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.49930, -12.9810, -12.9780),
                'louos_zeis:VM_L_Z_USO_ZEIS' => $this->retangulo(-38.49930, -38.4980, -12.9810, -12.9780),
                default => null,
            };

            return Http::response([
                'type' => 'FeatureCollection',
                'features' => $faixa === null ? [] : [['type' => 'Feature', 'geometry' => $faixa, 'properties' => []]],
            ]);
        });
    }

    /**
     * @return array{type: string, coordinates: list<list<list<float>>>}
     */
    private function retangulo(float $lngMin, float $lngMax, float $latMin = -12.9800, float $latMax = -12.9790): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lngMin, $latMin], [$lngMax, $latMin], [$lngMax, $latMax], [$lngMin, $latMax], [$lngMin, $latMin],
        ]]];
    }

    /**
     * @param  array<string, mixed>  $geojson
     */
    private function predioOverture(array $geojson): void
    {
        $base = app(BuildingFootprintRepository::class);
        $normal = $base->normalizar($geojson);
        $base->upsert(FonteEdificacao::Overture, uniqid('gers', true), '2026-09-23.1', $normal['geojson'], $normal['area_m2'], null, null, [], null, null, []);
    }

    private function processo(float $lat, float $lng): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);
        $request->forceFill([
            'property_point_lat' => $lat,
            'property_point_lng' => $lng,
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
        ])->save();

        return $request->fresh();
    }

    public function test_poligono_overture_entra_na_maior_porcao(): void
    {
        // Prédio de -38.49965 a -38.49915: 70% em ZPR 3, 30% em ZEIS.
        $this->predioOverture($this->retangulo(-38.49965, -38.49915, -12.97960, -12.97930));
        $request = $this->processo(-12.97945, -38.49940);

        app(PoligonoComplementarService::class)->resolver($request);
        $resultado = app(ZonaProcessoService::class)->materializar($request->fresh());

        $request->refresh();
        $this->assertSame(PoligonoFonte::Overture, $request->address_polygon_source);
        $this->assertSame('ZPR 3', $request->zona_codigo);
        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->zona_fonte);
        $this->assertEqualsWithDelta(70.0, $resultado->porcoes[0]['percentual'], 1.0);
    }

    public function test_multipolygon_entra_na_maior_porcao(): void
    {
        $this->predioOverture(['type' => 'MultiPolygon', 'coordinates' => [
            $this->retangulo(-38.49980, -38.49950, -12.97960, -12.97930)['coordinates'],
            $this->retangulo(-38.49925, -38.49915, -12.97960, -12.97950)['coordinates'],
        ]]);
        $request = $this->processo(-12.97945, -38.49965);

        app(PoligonoComplementarService::class)->resolver($request);
        app(ZonaProcessoService::class)->materializar($request->fresh());

        $request->refresh();
        $this->assertSame('MultiPolygon', $request->address_polygon_geojson['type']);
        $this->assertSame('ZPR 3', $request->zona_codigo);
        $this->assertEqualsWithDelta(-12.97945, $request->pontoDoImovel()['lat'], 0.0001);
    }
}
