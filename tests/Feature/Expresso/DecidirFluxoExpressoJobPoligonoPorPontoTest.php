<?php

namespace Tests\Feature\Expresso;

use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\ViabilityRequest;
use App\Services\Expresso\DecisionResult;
use App\Services\Expresso\FluxoExpressoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * O job de decisão busca o polígono do lote pelo ponto ANTES de decidir, para a
 * zona sair pela maior porção. Falha da consulta não impede a decisão.
 */
class DecidirFluxoExpressoJobPoligonoPorPontoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sile.features.geoserver_enderecamento' => true]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function protocolada(array $atributos = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->protocoled()->create($atributos + [
            'property_polygon_geojson' => null,
            'address_polygon_geojson' => null,
            'property_point_lat' => -12.98,
            'property_point_lng' => -38.5,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function colecaoComUmLote(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[
                    [-38.5, -12.98], [-38.4995, -12.98], [-38.4995, -12.9795], [-38.5, -12.9795], [-38.5, -12.98],
                ]]],
                'properties' => ['CODLOG' => 24574, 'NUMERACAO_METRICA' => 70, 'COMPLEMENTO' => null],
            ]],
        ];
    }

    private function consultasPorPonto(): int
    {
        return Http::recorded(
            fn (Request $request): bool => str_contains(urldecode($request->url()), 'INTERSECTS'),
        )->count();
    }

    public function test_job_grava_o_poligono_pelo_ponto_antes_de_decidir(): void
    {
        Http::fake(['*' => Http::response($this->colecaoComUmLote())]);
        $request = $this->protocolada();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturnUsing(function (ViabilityRequest $r): DecisionResult {
            $this->assertSame('Polygon', $r->fresh()->address_polygon_geojson['type']);

            return DecisionResult::paraAnalise('teste');
        });

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        $this->assertSame(1, $this->consultasPorPonto());
    }

    public function test_falha_na_consulta_por_ponto_nao_impede_a_decisao(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);
        $request = $this->protocolada();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        $this->assertNull($request->fresh()->address_polygon_geojson);
    }

    public function test_job_nao_consulta_quando_o_poligono_ja_esta_gravado(): void
    {
        Http::fake(['*' => Http::response($this->colecaoComUmLote())]);
        $existente = ['type' => 'Polygon', 'coordinates' => [[
            [-38.6, -12.9], [-38.5995, -12.9], [-38.5995, -12.8995], [-38.6, -12.8995], [-38.6, -12.9],
        ]]];
        $request = $this->protocolada(['address_polygon_geojson' => $existente]);

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        $this->assertSame(0, $this->consultasPorPonto());
        $this->assertSame($existente, $request->fresh()->address_polygon_geojson);
    }
}
