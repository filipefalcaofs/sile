<?php

namespace Tests\Feature\Geo;

use App\Enums\ZonaFonte;
use App\Models\GeoServerLayer;
use App\Models\ViabilityRequest;
use App\Services\Geo\MapaDecisaoService;
use Illuminate\Support\Facades\Http;
use Tests\PostgisTestCase;

/**
 * Recortes e medidas do mapa que justificam a zona (porção do lote em cada
 * zona, linha de centralidade por cima ou em frente ao lote). Roda no PostGIS.
 */
class MapaDecisaoPostgisTest extends PostgisTestCase
{
    /**
     * @param  list<list<float>>  $anel
     * @return array<string, mixed>
     */
    private function poligono(array $anel): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[...$anel, $anel[0]]]];
    }

    /**
     * @param  array<string, mixed>  $geometria
     * @param  array<string, mixed>  $propriedades
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private function colecao(array $geometria, array $propriedades): array
    {
        return ['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'geometry' => $geometria, 'properties' => $propriedades]]];
    }

    public function test_porcoes_do_lote_e_linhas_por_cima_e_em_frente(): void
    {
        config(['viabiliza.features.geoserver_zona' => true]);

        $lote = $this->poligono([[-38.5000, -12.9800], [-38.4995, -12.9800], [-38.4995, -12.9795], [-38.5000, -12.9795]]);
        $zonaEsquerda = $this->poligono([[-38.5010, -12.9810], [-38.499685, -12.9810], [-38.499685, -12.9790], [-38.5010, -12.9790]]);
        $zonaDireita = $this->poligono([[-38.499685, -12.9810], [-38.4990, -12.9810], [-38.4990, -12.9790], [-38.499685, -12.9790]]);

        GeoServerLayer::factory()->create(['workspace' => 'louos_zcmu_municipal_2', 'type_name' => 'VM_L_Z_USO_ZCMU_2', 'ordem' => 1]);
        GeoServerLayer::factory()->create(['workspace' => 'louos_zpr3', 'type_name' => 'VM_L_Z_USO_ZPR_3', 'ordem' => 2]);
        GeoServerLayer::factory()->create(['workspace' => 'louos_zona_uso_zclmu', 'type_name' => 'VM_L_Z_USO_ZCLMU', 'ordem' => 3]);
        GeoServerLayer::factory()->create(['workspace' => 'louos_zona_uso_zclme', 'type_name' => 'VM_L_Z_USO_ZCLME', 'ordem' => 4]);

        Http::fake([
            '*VM_L_Z_USO_ZCMU_2*' => Http::response($this->colecao($zonaEsquerda, ['ZONA' => 'ZCMu 2'])),
            '*VM_L_Z_USO_ZPR_3*' => Http::response($this->colecao($zonaDireita, ['ZONA' => 'ZPR 3'])),
            '*VM_L_Z_USO_ZCLMU*' => Http::response($this->colecao(['type' => 'LineString', 'coordinates' => [[-38.5010, -12.97975], [-38.4990, -12.97975]]], ['NOME' => 'Av. Por Cima'])),
            '*VM_L_Z_USO_ZCLME*' => Http::response($this->colecao(['type' => 'LineString', 'coordinates' => [[-38.5010, -12.9812], [-38.4990, -12.9812]]], ['NOME' => 'Av. Em Frente'])),
            '*' => Http::response(['type' => 'FeatureCollection', 'features' => []]),
        ]);

        $processo = ViabilityRequest::factory()->create([
            'address_polygon_geojson' => $lote,
            'zona_codigo' => 'ZCMu 2',
            'zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao,
        ]);

        $mapa = app(MapaDecisaoService::class)->montar($processo);

        $porcoes = collect($mapa['porcoes'])->keyBy('zona');
        $this->assertEqualsWithDelta(63.0, $porcoes['ZCMu 2']['percentual'], 0.5);
        $this->assertEqualsWithDelta(37.0, $porcoes['ZPR 3']['percentual'], 0.5);
        $this->assertTrue($porcoes['ZCMu 2']['da_decisao']);
        $this->assertFalse($porcoes['ZPR 3']['da_decisao']);

        $this->assertCount(2, $mapa['zonas']);

        $linhas = collect($mapa['linhas'])->keyBy('nome');
        $this->assertTrue($linhas['Av. Por Cima']['passa_sobre_lote']);
        $this->assertEqualsWithDelta(54.0, $linhas['Av. Por Cima']['dentro_m'], 2.0);
        $this->assertSame(0.0, $linhas['Av. Por Cima']['distancia_m']);
        $this->assertFalse($linhas['Av. Em Frente']['passa_sobre_lote']);
        $this->assertSame(0.0, $linhas['Av. Em Frente']['dentro_m']);
        $this->assertEqualsWithDelta(133.0, $linhas['Av. Em Frente']['distancia_m'], 5.0);
        $this->assertSame('LineString', $linhas['Av. Em Frente']['ligacao']['type']);
    }
}
