<?php

namespace Tests\Feature\Geo;

use App\Enums\PoligonoFonte;
use App\Models\Activity;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoEnderecamentoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Terceira fonte do polígono do lote: o endereçamento do GeoServer pelo ponto
 * do imóvel, só quando a chave e o SEMAP não acham e o chamador pede.
 */
class PoligonoPorPontoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sile.features.geoserver_enderecamento' => true,
            'sile.features.arcgis_semap' => true,
        ]);
    }

    /**
     * @return array{type: string, coordinates: list<list<list<float>>>}
     */
    private function quadrado(float $lng, float $lat): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng, $lat], [$lng + 0.0005, $lat], [$lng + 0.0005, $lat + 0.0005], [$lng, $lat + 0.0005], [$lng, $lat],
        ]]];
    }

    /**
     * @param  array<string, mixed>  ...$geometrias
     * @return array<string, mixed>
     */
    private function colecao(array ...$geometrias): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => array_map(fn (array $geometria): array => [
                'type' => 'Feature',
                'geometry' => $geometria,
                'properties' => ['CODLOG' => 24574, 'NUMERACAO_METRICA' => 70, 'COMPLEMENTO' => null],
            ], $geometrias),
        ];
    }

    /**
     * SEMAP sempre vazio; a chave e o ponto respondem o que o teste informar.
     *
     * @param  array<string, mixed>  $porPonto
     * @param  array<string, mixed>|null  $porChave
     */
    private function fakeGeoServer(array $porPonto, ?array $porChave = null): void
    {
        Http::fake(function (Request $request) use ($porPonto, $porChave) {
            if (str_contains($request->url(), 'arcgis')) {
                return Http::response($this->colecao());
            }

            $ehPonto = str_contains(urldecode($request->url()), 'INTERSECTS');

            return Http::response($ehPonto ? $porPonto : ($porChave ?? $this->colecao()));
        });
    }

    private function consultasPorPonto(): int
    {
        return Http::recorded(
            fn (Request $request): bool => str_contains(urldecode($request->url()), 'INTERSECTS'),
        )->count();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processo(array $atributos = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create($atributos + [
            'property_polygon_geojson' => null,
            'address_polygon_geojson' => null,
            'property_point_lat' => -12.98,
            'property_point_lng' => -38.5,
            'property_registration' => '344672',
        ]);
    }

    private function auditou(string $status): bool
    {
        return Activity::query()->where('event', 'enderecamento-poligono')
            ->get()
            ->contains(fn (Activity $a): bool => ($a->properties['fonte'] ?? null) === 'ponto'
                && ($a->properties['status'] ?? null) === $status);
    }

    public function test_sem_poligono_na_chave_nem_no_semap_o_ponto_grava_o_lote(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo();

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $request->refresh();

        $this->assertSame('Polygon', $request->address_polygon_geojson['type']);
        $this->assertSame(PoligonoFonte::Ponto, $request->address_polygon_source);
        $this->assertNotNull($request->address_polygon_consulted_at);
        $this->assertTrue($this->auditou('encontrado'));
    }

    public function test_sem_o_parametro_o_ponto_nao_e_consultado(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request));

        $this->assertSame(0, $this->consultasPorPonto());
        $this->assertNull($request->fresh()->address_polygon_geojson);
    }

    public function test_dois_poligonos_distintos_no_ponto_sao_ambiguos_e_nao_gravam(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98), $this->quadrado(-38.6, -12.9)));
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $request->refresh();

        $this->assertNull($request->address_polygon_geojson);
        $this->assertNull($request->address_polygon_source);
        $this->assertTrue($this->auditou('ambiguo'));
    }

    public function test_resposta_vazia_no_ponto_nao_grava(): void
    {
        $this->fakeGeoServer($this->colecao());
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $this->assertNull($request->fresh()->address_polygon_geojson);
        $this->assertTrue($this->auditou('nao_encontrado'));
    }

    public function test_http_500_no_ponto_nao_grava(): void
    {
        Http::fake(function (Request $request) {
            return str_contains(urldecode($request->url()), 'INTERSECTS')
                ? Http::response('erro', 500)
                : Http::response($this->colecao());
        });
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $this->assertNull($request->fresh()->address_polygon_geojson);
        $this->assertTrue($this->auditou('indisponivel'));
    }

    public function test_chave_que_acha_o_lote_dispensa_o_ponto(): void
    {
        $this->fakeGeoServer($this->colecao(), $this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo(['cod_log' => '24574', 'address_number' => '70']);

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $this->assertSame(PoligonoFonte::Chave, $request->fresh()->address_polygon_source);
        $this->assertSame(0, $this->consultasPorPonto());
    }

    public function test_aplicar_por_ponto_nao_consulta_com_poligono_ja_gravado(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.6, -12.9)));
        $existente = $this->quadrado(-38.5, -12.98);
        $request = $this->processo(['address_polygon_geojson' => $existente]);

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicarPorPonto($request));

        $this->assertSame(0, $this->consultasPorPonto());
        $this->assertSame($existente, $request->fresh()->address_polygon_geojson);
    }

    public function test_aplicar_por_ponto_sem_ponto_nao_consulta(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo(['property_point_lat' => null, 'property_point_lng' => null]);

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicarPorPonto($request));

        $this->assertSame(0, $this->consultasPorPonto());
    }

    public function test_aplicar_por_ponto_grava_a_fonte_ponto(): void
    {
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo();

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicarPorPonto($request));

        $this->assertSame(PoligonoFonte::Ponto, $request->fresh()->address_polygon_source);
    }

    public function test_interruptor_desligado_nao_consulta_o_ponto(): void
    {
        config(['sile.features.geoserver_enderecamento' => false]);
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request, consultarPorPonto: true));

        $this->assertSame(0, $this->consultasPorPonto());
    }

    public function test_busca_em_andamento_no_mesmo_processo_nao_repete_a_consulta(): void
    {
        config(['sile.integrations.geoserver.enderecamento.espera_trava_ponto' => 0]);
        $this->fakeGeoServer($this->colecao($this->quadrado(-38.5, -12.98)));
        $request = $this->processo();
        $emAndamento = Cache::lock("poligono-ponto:{$request->id}", 60);
        $emAndamento->get();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicarPorPonto($request));

        $this->assertSame(0, $this->consultasPorPonto());
        $emAndamento->release();
    }
}
