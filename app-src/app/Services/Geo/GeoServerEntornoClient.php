<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Traz as feições (com geometria) de cada camada do GeoServer SEDUR dentro de
 * um BBOX, em paralelo. É só leitura para desenhar o mapa do processo: não
 * decide zona nem via. Segue o mesmo contorno do WAF da Prefeitura que o
 * cálculo da maior porção usa: BBOX no CQL_FILTER e, se recusado, no
 * parâmetro bbox.
 */
class GeoServerEntornoClient
{
    /**
     * @param  list<string>  $typeNames
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox  [lngMin, latMin, lngMax, latMax]
     * @return array<string, list<array<string, mixed>>|null> null = camada sem resposta
     */
    public function consultar(array $typeNames, array $bbox): array
    {
        if ($typeNames === []) {
            return [];
        }

        try {
            $payloads = $this->pool($typeNames, fn (string $typeName): string => $this->url($typeName, [
                'CQL_FILTER' => sprintf("BBOX(GEOMETRY,%.7f,%.7f,%.7f,%.7f,'EPSG:4326')", ...$bbox),
            ]));

            $recusadas = array_keys(array_filter($payloads, fn (?array $payload): bool => $payload === null));

            if ($recusadas !== []) {
                $payloads = array_merge($payloads, $this->pool($recusadas, fn (string $typeName): string => $this->url($typeName, [
                    'bbox' => sprintf('%.7f,%.7f,%.7f,%.7f,EPSG:4326', ...$bbox),
                ])));
            }
        } catch (Throwable) {
            return array_fill_keys($typeNames, null);
        }

        return array_map(
            fn (?array $payload): ?array => $payload === null
                ? null
                : array_values(array_filter(is_array($payload['features'] ?? null) ? $payload['features'] : [], 'is_array')),
            $payloads,
        );
    }

    /**
     * @param  list<string>  $typeNames
     * @param  callable(string): string  $urlDe
     * @return array<string, array<string, mixed>|null>
     */
    private function pool(array $typeNames, callable $urlDe): array
    {
        $responses = Http::pool(function (Pool $pool) use ($typeNames, $urlDe): void {
            foreach ($typeNames as $typeName) {
                $pool->as($typeName)
                    ->withHeaders(['User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0')])
                    ->timeout((int) config('viabiliza.integrations.geoserver.timeout', 8))
                    ->connectTimeout(3)
                    ->acceptJson()
                    ->get($urlDe($typeName));
            }
        });

        $payloads = [];

        foreach ($typeNames as $typeName) {
            $response = $responses[$typeName] ?? null;
            $payload = $response instanceof Response && $response->successful() ? $response->json() : null;

            $payloads[$typeName] = is_array($payload) && ($payload['type'] ?? null) === 'FeatureCollection' ? $payload : null;
        }

        return $payloads;
    }

    /**
     * @param  array<string, string>  $filtro
     */
    private function url(string $typeName, array $filtro): string
    {
        $base = rtrim((string) Settings::get('integrations.geoserver.base_url', config('viabiliza.integrations.geoserver.base_url')), '/');

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
        ] + $filtro);
    }
}
