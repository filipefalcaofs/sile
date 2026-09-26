<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Services\Geo\GeoJsonLayerImporter;
use App\Services\Geo\GeoServerWfsPaginador;
use App\Services\Geo\WfsIndisponivelException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ImportarCamadaWfsCommandTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function pagina(int $inicio, int $quantidade): array
    {
        $features = [];

        for ($i = 0; $i < $quantidade; $i++) {
            $features[] = [
                'type' => 'Feature',
                'properties' => ['CODLOG' => $inicio + $i, 'NOME_LOGRADOURO' => 'Rua '.($inicio + $i)],
                'geometry' => ['type' => 'LineString', 'coordinates' => [[-38.5, -12.9], [-38.51, -12.91]]],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    public function test_paginador_percorre_todas_as_paginas_ate_a_ultima_incompleta(): void
    {
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $inicio = (int) $query['startIndex'];

            return Http::response(match ($inicio) {
                0 => $this->pagina(1, 2),
                2 => $this->pagina(3, 2),
                default => $this->pagina(5, 1),
            });
        });

        $colecao = app(GeoServerWfsPaginador::class)->baixar('logradouro:VM_LOGRADOURO', 'CODLOG', 2);

        $this->assertSame('FeatureCollection', $colecao['type']);
        $this->assertCount(5, $colecao['features']);
        $this->assertSame(5, $colecao['features'][4]['properties']['CODLOG']);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['service'] === 'WFS'
                && $query['version'] === '2.0.0'
                && $query['typeNames'] === 'logradouro:VM_LOGRADOURO'
                && $query['sortBy'] === 'CODLOG'
                && $query['srsName'] === 'EPSG:4326'
                && $query['count'] === '2';
        });
    }

    public function test_paginador_aborta_quando_uma_pagina_falha(): void
    {
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return (int) $query['startIndex'] === 0
                ? Http::response($this->pagina(1, 2))
                : Http::response('erro', 500);
        });

        $this->expectException(WfsIndisponivelException::class);

        app(GeoServerWfsPaginador::class)->baixar('logradouro:VM_LOGRADOURO', 'CODLOG', 2);
    }

    public function test_comando_importa_a_colecao_completa_como_nova_versao(): void
    {
        Http::fake(fn () => Http::response($this->pagina(1, 3)));

        $importador = Mockery::mock(GeoJsonLayerImporter::class);
        $importador->shouldReceive('import')
            ->once()
            ->withArgs(fn (GeoLayerType $tipo, string $versao, string $fonte, array $colecao): bool => $tipo === GeoLayerType::Via
                && $versao === 'sedur-logradouros-2026-09'
                && str_contains($fonte, 'logradouro:VM_LOGRADOURO')
                && count($colecao['features']) === 3)
            ->andReturn(['lidas' => 3, 'inseridas' => 3, 'invalidas' => [], 'version' => 'sedur-logradouros-2026-09', 'feature_count' => 3]);
        $this->app->instance(GeoJsonLayerImporter::class, $importador);

        $this->artisan('geo:importar-wfs', [
            'type' => 'via',
            'typeName' => 'logradouro:VM_LOGRADOURO',
            '--versao' => 'sedur-logradouros-2026-09',
            '--ordenar' => 'CODLOG',
        ])
            ->expectsOutputToContain('Inseridas: 3')
            ->assertSuccessful();
    }

    public function test_comando_nao_publica_versao_parcial_quando_o_wfs_falha(): void
    {
        Http::fake(fn () => Http::response('erro', 500));

        $importador = Mockery::mock(GeoJsonLayerImporter::class);
        $importador->shouldNotReceive('import');
        $this->app->instance(GeoJsonLayerImporter::class, $importador);

        $this->artisan('geo:importar-wfs', [
            'type' => 'via',
            'typeName' => 'logradouro:VM_LOGRADOURO',
            '--versao' => 'sedur-logradouros-2026-09',
        ])->assertFailed();
    }
}
