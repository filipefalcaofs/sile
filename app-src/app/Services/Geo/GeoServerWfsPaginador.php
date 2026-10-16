<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Baixa uma FeatureType inteira do GeoServer SEDUR em páginas (WFS 2.0,
 * count/startIndex com sortBy — sem ordenação o GeoServer recusa a paginação
 * natural) e devolve uma única FeatureCollection em EPSG:4326. Uma página com
 * erro aborta tudo.
 */
class GeoServerWfsPaginador
{
    private const TIMEOUT_SEGUNDOS = 120;

    /**
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function baixar(string $typeName, string $ordenarPor, int $porPagina = 2000): array
    {
        $features = [];
        $inicio = 0;

        do {
            $pagina = $this->pagina($typeName, $ordenarPor, $porPagina, $inicio);
            $features = [...$features, ...$pagina];
            $inicio += $porPagina;
        } while (count($pagina) === $porPagina);

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pagina(string $typeName, string $ordenarPor, int $porPagina, int $inicio): array
    {
        $base = rtrim((string) Settings::get(
            'integrations.geoserver.base_url',
            config('viabiliza.integrations.geoserver.base_url'),
        ), '/');

        try {
            $resposta = Http::withHeaders([
                'User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                ->timeout(self::TIMEOUT_SEGUNDOS)
                ->acceptJson()
                ->get($base.'/wfs?'.http_build_query([
                    'service' => 'WFS',
                    'version' => '2.0.0',
                    'request' => 'GetFeature',
                    'typeNames' => $typeName,
                    'outputFormat' => 'application/json',
                    'srsName' => 'EPSG:4326',
                    'sortBy' => $ordenarPor,
                    'count' => $porPagina,
                    'startIndex' => $inicio,
                ]));
        } catch (Throwable $e) {
            throw new WfsIndisponivelException("GeoServer SEDUR indisponível ao baixar {$typeName} (início {$inicio}): {$e->getMessage()}");
        }

        $corpo = $resposta->json();

        if (! $resposta->successful() || ! is_array($corpo) || ($corpo['type'] ?? null) !== 'FeatureCollection') {
            throw new WfsIndisponivelException("GeoServer SEDUR respondeu HTTP {$resposta->status()} fora do contrato ao baixar {$typeName} (início {$inicio}).");
        }

        return array_values(is_array($corpo['features'] ?? null) ? $corpo['features'] : []);
    }
}
