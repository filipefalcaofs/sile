<?php

namespace Tests\Feature\Geo;

use App\Models\GeoServerLayer;
use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Maior porção (art. 18, § 2º, da Lei 9.148/2016) calculada no PostGIS:
 * lote de 0,001° × 0,001° em Salvador, dividido por faixas verticais de zona.
 */
#[Group('postgis')]
class ZonaMaiorPorcaoResolverPostgisTest extends PostgisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['sile.features.geoserver_zona' => true, 'sile.features.geoserver_enderecamento' => true]);

        GeoServerLayer::factory()->create(['workspace' => 'louos_zpr3', 'type_name' => 'VM_L_Z_USO_ZPR_3', 'ordem' => 1]);
        GeoServerLayer::factory()->create(['workspace' => 'louos_zeis', 'type_name' => 'VM_L_Z_USO_ZEIS', 'ordem' => 2]);
    }

    /**
     * @return array<string, mixed>
     */
    private function retangulo(float $lngMin, float $lngMax, float $latMin = -12.9800, float $latMax = -12.9790): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lngMin, $latMin], [$lngMax, $latMin], [$lngMax, $latMax], [$lngMin, $latMax], [$lngMin, $latMin],
        ]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function lote(): array
    {
        return $this->retangulo(-38.5000, -38.4990);
    }

    /**
     * @param  array<string, array<string, mixed>|int>  $porCamada  geometria da feição, ou status HTTP de falha
     */
    private function geoserver(array $porCamada): void
    {
        Http::fake(function (Request $request) use ($porCamada) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $resposta = $porCamada[$query['typeName'] ?? ''] ?? null;

            if (is_int($resposta)) {
                return Http::response('URL Bloqueada', $resposta);
            }

            return Http::response([
                'type' => 'FeatureCollection',
                'features' => $resposta === null ? [] : [['type' => 'Feature', 'geometry' => $resposta, 'properties' => []]],
            ]);
        });
    }

    public function test_ca_zn_01_78_por_cento_em_zpr3_vence(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.49922, -12.9810, -12.9780),
            'louos_zeis:VM_L_Z_USO_ZEIS' => $this->retangulo(-38.49922, -38.4980, -12.9810, -12.9780),
        ]);

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote());

        $this->assertSame(ZonaMaiorPorcao::IDENTIFICADO, $resultado->status);
        $this->assertSame('ZPR 3', $resultado->codigo);
        $this->assertSame(['ZPR 3', 'ZEIS'], array_column($resultado->porcoes, 'zona'));
        $this->assertEqualsWithDelta(78.0, $resultado->porcoes[0]['percentual'], 0.2);
        $this->assertEqualsWithDelta(22.0, $resultado->porcoes[1]['percentual'], 0.2);
        $this->assertGreaterThan($resultado->porcoes[1]['area_m2'], $resultado->porcoes[0]['area_m2']);
    }

    public function test_ca_zn_03_uma_camada_falhando_torna_o_resultado_indisponivel(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.4980, -12.9810, -12.9780),
            'louos_zeis:VM_L_Z_USO_ZEIS' => 500,
        ]);

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote());

        $this->assertSame(ZonaMaiorPorcao::INDISPONIVEL, $resultado->status);
        $this->assertStringContainsString('louos_zeis:VM_L_Z_USO_ZEIS', (string) $resultado->motivo);
    }

    public function test_ca_zn_04_areas_iguais_sao_empate(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.4995, -12.9810, -12.9780),
            'louos_zeis:VM_L_Z_USO_ZEIS' => $this->retangulo(-38.4995, -38.4980, -12.9810, -12.9780),
        ]);

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote());

        $this->assertSame(ZonaMaiorPorcao::EMPATE, $resultado->status);
        $this->assertNull($resultado->codigo);
        $this->assertEqualsCanonicalizing(['ZPR 3', 'ZEIS'], $resultado->empate);
    }

    public function test_ca_zn_05_sem_intersecao(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.40, -38.39),
        ]);

        $this->assertSame(ZonaMaiorPorcao::SEM_INTERSECAO, app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote())->status);
    }

    public function test_ca_zn_06_camada_linear_nao_disputa_a_maior_porcao(): void
    {
        GeoServerLayer::factory()->create(['workspace' => 'louos_zona_uso_zclmu', 'type_name' => 'VM_L_Z_USO_ZCLMU', 'ordem' => 3]);
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.4980, -12.9810, -12.9780),
            'louos_zona_uso_zclmu:VM_L_Z_USO_ZCLMU' => ['type' => 'LineString', 'coordinates' => [[-38.5005, -12.9795], [-38.4985, -12.9795]]],
        ]);

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver($this->lote());

        $this->assertSame(ZonaMaiorPorcao::IDENTIFICADO, $resultado->status);
        $this->assertSame(['ZPR 3'], array_column($resultado->porcoes, 'zona'));
        $linear = collect($resultado->camadas)->firstWhere('type_name', 'louos_zona_uso_zclmu:VM_L_Z_USO_ZCLMU');
        $this->assertSame('linear', $linear['geometria']);
    }

    public function test_ca_zn_08_uma_consulta_por_poligono_e_por_camada(): void
    {
        $this->geoserver([
            'louos_zpr3:VM_L_Z_USO_ZPR_3' => $this->retangulo(-38.5010, -38.4980, -12.9810, -12.9780),
        ]);
        $resolver = app(ZonaMaiorPorcaoResolver::class);

        $resolver->resolver($this->lote());
        $resolver->resolver($this->lote());

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with((string) ($query['CQL_FILTER'] ?? ''), 'BBOX(GEOMETRY,');
        });
    }
}
