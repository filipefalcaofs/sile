<?php

namespace App\Services\Geo;

use App\Models\GeoServerLayer;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Traz as geometrias de zona que cruzam a caixa do lote. BBOX, não polígono
 * na URL: o WAF da Prefeitura bloqueia INTERSECTS com POLYGON.
 */
class ZonaGeometriaColetor
{
    /**
     * @param  array<string, mixed>  $poligono
     * @return list<array{zona: string, geometria: array<string, mixed>, linear: bool}>|null
     */
    public function noPoligono(array $poligono): ?array
    {
        if (! Settings::enabled('geoserver_zona')) {
            return null;
        }

        $bbox = $this->bbox($poligono);
        $typeNames = $this->typeNames();

        if ($bbox === null || $typeNames === []) {
            return null;
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($typeNames, $bbox): void {
                foreach ($typeNames as $typeName) {
                    $pool->as($typeName)
                        ->withHeaders([
                            'User-Agent' => (string) config(
                                'sile.integrations.geoserver.user_agent',
                                'Viabiliza-SEDUR-Salvador/1.0',
                            ),
                        ])
                        ->timeout((int) config('sile.integrations.geoserver.timeout', 8))
                        ->connectTimeout(3)
                        ->acceptJson()
                        ->get($this->url($typeName, $bbox));
                }
            });
        } catch (Throwable) {
            return null;
        }

        $candidatas = [];

        foreach ($typeNames as $typeName) {
            $response = $responses[$typeName] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                return null;
            }

            $payload = $response->json();

            if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
                return null;
            }

            foreach (is_array($payload['features'] ?? null) ? $payload['features'] : [] as $feature) {
                if (! is_array($feature)) {
                    continue;
                }

                $geometria = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;
                $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];

                if ($geometria === null) {
                    continue;
                }

                $tipo = (string) ($geometria['type'] ?? '');
                $linear = in_array($tipo, ['LineString', 'MultiLineString'], true);
                $zona = GeoServerWfsZonaClient::codigoZona($properties, $typeName);

                if ($zona === null) {
                    continue;
                }

                $candidatas[] = [
                    'zona' => $zona,
                    'geometria' => $geometria,
                    'linear' => $linear,
                ];
            }
        }

        return $candidatas;
    }

    /**
     * @param  array<string, mixed>  $poligono
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    private function bbox(array $poligono): ?array
    {
        $pontos = [];
        $this->pontos($poligono['coordinates'] ?? [], $pontos);

        if ($pontos === []) {
            return null;
        }

        $lngs = array_column($pontos, 0);
        $lats = array_column($pontos, 1);

        return [min($lngs), min($lats), max($lngs), max($lats)];
    }

    /**
     * @param  list<float>  $pontos
     */
    private function pontos(mixed $coordinates, array &$pontos): void
    {
        if (! is_array($coordinates) || $coordinates === []) {
            return;
        }

        if (is_float($coordinates[0]) || is_int($coordinates[0])) {
            $pontos[] = [(float) $coordinates[0], (float) $coordinates[1]];

            return;
        }

        foreach ($coordinates as $item) {
            $this->pontos($item, $pontos);
        }
    }

    /**
     * @return list<string>
     */
    private function typeNames(): array
    {
        try {
            return GeoServerLayer::query()->ativos()->get()
                ->map(fn (GeoServerLayer $camada): string => $camada->nomeCompleto())
                ->all();
        } catch (QueryException) {
            $names = config('sile.integrations.geoserver.type_names', []);

            return array_values(array_filter(
                is_array($names) ? $names : [],
                fn (mixed $name): bool => is_string($name) && $name !== '',
            ));
        }
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    private function url(string $typeName, array $bbox): string
    {
        $base = rtrim((string) Settings::get(
            'integrations.geoserver.base_url',
            config('sile.integrations.geoserver.base_url'),
        ), '/');

        $filtro = sprintf(
            'BBOX(GEOMETRY,%.7f,%.7f,%.7f,%.7f)',
            $bbox[0],
            $bbox[1],
            $bbox[2],
            $bbox[3],
        );

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'maxFeatures' => 20,
            'CQL_FILTER' => $filtro,
        ]);
    }
}
