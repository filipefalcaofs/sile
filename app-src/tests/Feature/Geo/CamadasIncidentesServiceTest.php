<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Models\ViabilityRequest;
use App\Services\Geo\CamadasIncidentesService;
use App\Services\Geo\SpatialRepository;
use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\Geo\FakeZonaMaiorPorcaoResolver;
use Tests\TestCase;

class CamadasIncidentesServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-teste']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'sedur-logradouros-2026-09']);

        $espacial = new FakeSpatialRepository;
        $espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCMe 1/01']]);
        $espacial->setWithin(GeoLayerType::Via, [
            ['id' => 10, 'properties' => ['CODLOG' => 958, 'NOME_LOGRADOURO' => 'Rua Luiz Murat'], 'distancia_m' => 25.9],
            ['id' => 11, 'properties' => ['CODLOG' => 212, 'NOME_LOGRADOURO' => 'Avenida Lafayete Coutinho'], 'distancia_m' => 26.6],
            ['id' => 12, 'properties' => ['CODLOG' => 10888, 'NOME_LOGRADOURO' => 'Avenida Sodré'], 'distancia_m' => 55.9],
        ]);
        $this->app->instance(SpatialRepository::class, $espacial);

        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.977769,
                    longitude: -38.516404,
                    inscricao: $inscricao,
                    raw: ['CodigoLogradouroTributario' => '212', 'LogradouroTributario' => 'Avenida Lafayete Coutinho'],
                );
            }
        });
    }

    private function wfsComTrecho(?string $trecho, bool $falhaAbm = false): void
    {
        Http::fake(function (Request $request) use ($trecho, $falhaAbm) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $typeName = $query['typeName'] ?? '';
            $filtro = $query['CQL_FILTER'] ?? '';

            if (! str_starts_with($typeName, 'borda_maritima')) {
                return Http::response(['type' => 'FeatureCollection', 'features' => []]);
            }

            if ($falhaAbm) {
                return Http::response('erro', 500);
            }

            $dentroDoFiltro = ! str_contains($filtro, 'TRECHOS IN') || in_array($trecho, ['5', '6'], true);

            return Http::response([
                'type' => 'FeatureCollection',
                'features' => $trecho !== null && $dentroDoFiltro
                    ? [['type' => 'Feature', 'properties' => ['TRECHOS' => $trecho], 'geometry' => null]]
                    : [],
            ]);
        });
    }

    private function processo(): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create([
            'property_polygon_geojson' => null,
            'property_registration' => '344672',
        ]);
        $request->forceFill(['property_point_lat' => -12.977769, 'property_point_lng' => -38.516404])->save();

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>  $inventario
     * @return array<string, mixed>|null
     */
    private function camada(array $inventario, string $regra): ?array
    {
        foreach ($inventario['camadas'] as $camada) {
            if ($camada['regra'] === $regra) {
                return $camada;
            }
        }

        return null;
    }

    public function test_grava_logradouro_tributario_logradouros_proximos_bairro_e_zona(): void
    {
        $this->wfsComTrecho(null);
        $request = $this->processo();

        app(CamadasIncidentesService::class)->materializar($request);

        $inventario = $request->fresh()->camadas_incidentes;

        $this->assertSame(['cod_log' => '212', 'nome' => 'Avenida Lafayete Coutinho'], $inventario['logradouro_tributario']);
        $this->assertSame(['958', '212'], array_column($inventario['logradouros_proximos'], 'cod_log'));
        $this->assertSame('Rua Luiz Murat', $inventario['logradouros_proximos'][0]['nome']);
        $this->assertSame('Comércio', $inventario['bairro']['nome']);
        $this->assertSame('ZCMe 1/01', $inventario['zona']['nome']);
        $this->assertSame(-12.977769, $inventario['ponto']['lat']);
        $this->assertNotNull($inventario['identificado_em']);
    }

    public function test_trecho_5_da_borda_maritima_e_reconhecido_e_a_regra_incide(): void
    {
        $this->wfsComTrecho('5');
        $request = $this->processo();

        $inventario = app(CamadasIncidentesService::class)->materializar($request);
        $abm = $this->camada($inventario, 'RT-03-ABM');

        $this->assertSame('dentro', $abm['status']);
        $this->assertSame('Trecho 5', $abm['elemento']);
        $this->assertSame('5', $abm['atributos']['TRECHOS']);
        $this->assertTrue($abm['incide']);
        $this->assertSame('fora', $this->camada($inventario, 'RT-03-ZEM')['status']);
    }

    public function test_trecho_3_e_reconhecido_sem_incidencia_da_regra(): void
    {
        $this->wfsComTrecho('3');
        $request = $this->processo();

        $abm = $this->camada(app(CamadasIncidentesService::class)->materializar($request), 'RT-03-ABM');

        $this->assertSame('dentro', $abm['status']);
        $this->assertSame('Trecho 3', $abm['elemento']);
        $this->assertFalse($abm['incide']);
    }

    public function test_camada_indisponivel_fica_registrada_como_indisponivel(): void
    {
        $this->wfsComTrecho('5', falhaAbm: true);
        $request = $this->processo();

        $abm = $this->camada(app(CamadasIncidentesService::class)->materializar($request), 'RT-03-ABM');

        $this->assertSame('indisponivel', $abm['status']);
        $this->assertNull($abm['incide']);
    }

    public function test_sem_ponto_do_imovel_nao_ha_inventario_inventado(): void
    {
        $this->wfsComTrecho('5');
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null, 'property_registration' => null]);

        $inventario = app(CamadasIncidentesService::class)->materializar($request);

        $this->assertNull($inventario['ponto']);
        $this->assertSame([], $inventario['camadas']);
        $this->assertSame([], $inventario['logradouros_proximos']);
    }

    private const LOTE = ['type' => 'Polygon', 'coordinates' => [[[-38.5165, -12.9778], [-38.5160, -12.9778], [-38.5160, -12.9773], [-38.5165, -12.9773], [-38.5165, -12.9778]]]];

    public function test_ca_zn_02_porcoes_ficam_no_inventario_da_maior_para_a_menor(): void
    {
        $this->wfsComTrecho(null);
        $this->app->instance(ZonaMaiorPorcaoResolver::class, (new FakeZonaMaiorPorcaoResolver)->para(self::LOTE, FakeZonaMaiorPorcaoResolver::identificado('ZPR 3', [
            ['zona' => 'ZPR 3', 'area_m2' => 780.0, 'percentual' => 78.0],
            ['zona' => 'ZEIS', 'area_m2' => 220.0, 'percentual' => 22.0],
        ])));
        $request = $this->processo();
        $request->forceFill(['address_polygon_geojson' => self::LOTE])->save();

        $zona = app(CamadasIncidentesService::class)->materializar($request->fresh())['zona'];

        $this->assertSame('ZPR 3', $zona['nome']);
        $this->assertSame('enderecamento_maior_porcao', $zona['fonte']);
        $this->assertSame(['ZPR 3', 'ZEIS'], array_column($zona['porcoes'], 'zona'));
        $this->assertSame(78.0, $zona['porcoes'][0]['percentual']);
    }

    public function test_sem_poligono_do_enderecamento_a_zona_e_do_ponto(): void
    {
        $this->wfsComTrecho(null);

        $zona = app(CamadasIncidentesService::class)->materializar($this->processo())['zona'];

        $this->assertSame('ZCMe 1/01', $zona['nome']);
        $this->assertSame('ponto', $zona['fonte']);
        $this->assertSame([], $zona['porcoes']);
        $this->assertNull($zona['maior_porcao']);
    }

    public function test_maior_porcao_indisponivel_mostra_a_zona_do_ponto_com_o_motivo(): void
    {
        $this->wfsComTrecho(null);
        $this->app->instance(ZonaMaiorPorcaoResolver::class, (new FakeZonaMaiorPorcaoResolver)->para(self::LOTE, ZonaMaiorPorcao::indisponivel('GeoServer fora.')));
        $request = $this->processo();
        $request->forceFill(['address_polygon_geojson' => self::LOTE])->save();

        $zona = app(CamadasIncidentesService::class)->materializar($request->fresh())['zona'];

        $this->assertSame('ZCMe 1/01', $zona['nome']);
        $this->assertSame('ponto', $zona['fonte']);
        $this->assertSame('indisponivel', $zona['maior_porcao']['status']);
        $this->assertSame('GeoServer fora.', $zona['maior_porcao']['motivo']);
    }
}
