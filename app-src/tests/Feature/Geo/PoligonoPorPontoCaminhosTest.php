<?php

namespace Tests\Feature\Geo;

use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoEnderecamentoService;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * O protocolo do portal é síncrono (HTTP): não pode pedir a consulta lenta por
 * ponto. Quem a pede é o job de decisão, depois.
 */
class PoligonoPorPontoCaminhosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_completar_territorio_do_protocolo_nao_pede_a_consulta_por_ponto(): void
    {
        Http::fake();

        $chamadas = [];
        $poligono = Mockery::mock(PoligonoEnderecamentoService::class);
        $poligono->shouldReceive('aplicar')->andReturnUsing(
            function (ViabilityRequest $request, bool $consultarPorPonto = false) use (&$chamadas): bool {
                $chamadas[] = $consultarPorPonto;

                return false;
            },
        );
        $poligono->shouldNotReceive('aplicarPorPonto');
        $this->app->instance(PoligonoEnderecamentoService::class, $poligono);

        $request = ViabilityRequest::factory()->protocoled()->create([
            'property_polygon_geojson' => null,
            'address_polygon_geojson' => null,
            'property_point_lat' => -12.98,
            'property_point_lng' => -38.5,
            'zona_codigo' => 'ZCMu 2',
            'bairro_oficial' => 'Centro',
            'cod_log' => '24574',
        ]);

        app(ProtocolarSolicitacaoService::class)->completarTerritorio($request);

        $this->assertSame([false], $chamadas);
    }
}
