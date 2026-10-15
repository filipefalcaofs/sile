<?php

namespace App\Services\Geo;

use App\Models\GeoServerLayer;
use App\Support\Settings;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Traz a geometria das zonas candidatas por BBOX do polígono em cada camada de
 * zona ativa (o WAF da Prefeitura bloqueia polígono na URL) e calcula a área de
 * cada interseção no PostGIS da aplicação. Uma camada sem resposta torna o
 * resultado indisponível: sem ela a maior porção não é verificável. Camada que
 * responde linha (centralidade linear) não disputa. Nada é persistido.
 */
class GeoServerZonaMaiorPorcaoResolver implements ZonaMaiorPorcaoResolver
{
    /** @var array<string, ZonaMaiorPorcao> */
    private array $memo = [];

    public function resolver(array $poligono): ZonaMaiorPorcao
    {
        return $this->memo[md5((string) json_encode($poligono))] ??= $this->calcular($poligono);
    }

    /**
     * @param  array<string, mixed>  $poligono
     */
    private function calcular(array $poligono): ZonaMaiorPorcao
    {
        if (DB::getDriverName() !== 'pgsql') {
            return ZonaMaiorPorcao::indisponivel('Cálculo da maior porção exige PostGIS.');
        }

        if (! Settings::enabled('geoserver_enderecamento') || ! Settings::enabled('geoserver_zona')) {
            return ZonaMaiorPorcao::indisponivel('Consulta de zona ou do endereçamento no GeoServer SEDUR desligada.');
        }

        $bbox = $this->bbox($poligono);

        if ($bbox === null) {
            return ZonaMaiorPorcao::indisponivel('Polígono sem coordenadas.');
        }

        $typeNames = GeoServerLayer::query()->ativos()->get()
            ->map(fn (GeoServerLayer $camada): string => $camada->nomeCompleto())
            ->all();

        if ($typeNames === []) {
            return ZonaMaiorPorcao::indisponivel('Nenhuma FeatureType de zona configurada no GeoServer.');
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($typeNames, $bbox): void {
                foreach ($typeNames as $typeName) {
                    $pool->as($typeName)
                        ->withHeaders(['User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0')])
                        ->timeout((int) config('viabiliza.integrations.geoserver.timeout', 8))
                        ->connectTimeout(3)
                        ->acceptJson()
                        ->get($this->url($typeName, $bbox));
                }
            });
        } catch (Throwable $e) {
            return ZonaMaiorPorcao::indisponivel('GeoServer SEDUR indisponível: '.$e->getMessage());
        }

        $camadas = [];
        $candidatas = [];
        $falhas = [];

        foreach ($typeNames as $typeName) {
            $response = $responses[$typeName] ?? null;
            $payload = $response instanceof Response && $response->successful() ? $response->json() : null;

            if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
                $falhas[] = $typeName;
                $camadas[] = ['type_name' => $typeName, 'geometria' => null, 'status' => 'falha'];

                continue;
            }

            $linear = false;

            foreach (is_array($payload['features'] ?? null) ? $payload['features'] : [] as $feature) {
                $geometria = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;
                $tipo = $geometria['type'] ?? null;

                if (in_array($tipo, ['LineString', 'MultiLineString'], true)) {
                    $linear = true;

                    continue;
                }

                if (! in_array($tipo, ['Polygon', 'MultiPolygon'], true)) {
                    continue;
                }

                $codigo = GeoServerWfsZonaClient::codigoZona(is_array($feature['properties'] ?? null) ? $feature['properties'] : [], $typeName);

                if ($codigo !== null) {
                    $candidatas[] = ['zona' => $codigo, 'geometria' => $geometria];
                }
            }

            $camadas[] = ['type_name' => $typeName, 'geometria' => $linear ? 'linear' : 'poligonal', 'status' => 'ok'];
        }

        if ($falhas !== []) {
            return ZonaMaiorPorcao::indisponivel('Camada de zona sem resposta do GeoServer SEDUR: '.implode(', ', $falhas).'.', $camadas);
        }

        return $this->porcoes($poligono, $candidatas, $camadas);
    }

    /**
     * @param  array<string, mixed>  $poligono
     * @param  list<array{zona: string, geometria: array<string, mixed>}>  $candidatas
     * @param  list<array{type_name: string, geometria: ?string, status: string}>  $camadas
     */
    private function porcoes(array $poligono, array $candidatas, array $camadas): ZonaMaiorPorcao
    {
        $areaLote = $this->area('SELECT ST_Area(ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))::geography) AS area', [json_encode($poligono)]);
        $areas = [];

        foreach ($candidatas as $candidata) {
            $area = $this->area(
                'SELECT ST_Area(ST_CollectionExtract(ST_Intersection(
                        ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)),
                        ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))
                    ), 3)::geography) AS area',
                [json_encode($poligono), json_encode($candidata['geometria'])],
            );

            if ($area > 0.0) {
                $areas[$candidata['zona']] = ($areas[$candidata['zona']] ?? 0.0) + $area;
            }
        }

        if ($areas === []) {
            return new ZonaMaiorPorcao(ZonaMaiorPorcao::SEM_INTERSECAO, camadas: $camadas, areaLoteM2: round($areaLote, 2));
        }

        arsort($areas);

        $porcoes = [];

        foreach ($areas as $zona => $area) {
            $porcoes[] = [
                'zona' => (string) $zona,
                'area_m2' => round($area, 2),
                'percentual' => $areaLote > 0.0 ? round($area / $areaLote * 100, 1) : 0.0,
            ];
        }

        $valores = array_values($areas);
        $zonas = array_map('strval', array_keys($areas));

        if (count($valores) > 1 && abs($valores[0] - $valores[1]) <= ZonaMaiorPorcao::TOLERANCIA_EMPATE_M2) {
            return new ZonaMaiorPorcao(ZonaMaiorPorcao::EMPATE, porcoes: $porcoes, empate: [$zonas[0], $zonas[1]], camadas: $camadas, areaLoteM2: round($areaLote, 2));
        }

        return new ZonaMaiorPorcao(ZonaMaiorPorcao::IDENTIFICADO, codigo: $zonas[0], porcoes: $porcoes, camadas: $camadas, areaLoteM2: round($areaLote, 2));
    }

    /**
     * @param  list<string|false>  $bindings
     */
    private function area(string $sql, array $bindings): float
    {
        $linha = DB::selectOne($sql, $bindings);

        return $linha !== null && $linha->area !== null ? (float) $linha->area : 0.0;
    }

    /**
     * @param  array<string, mixed>  $poligono
     * @return array{0: float, 1: float, 2: float, 3: float}|null [lngMin, latMin, lngMax, latMax]
     */
    private function bbox(array $poligono): ?array
    {
        if (! is_array($poligono['coordinates'] ?? null)) {
            return null;
        }

        $pontos = [];

        array_walk_recursive($poligono['coordinates'], function (mixed $valor) use (&$pontos): void {
            $pontos[] = (float) $valor;
        });

        if (count($pontos) < 6) {
            return null;
        }

        $lngs = array_values(array_filter($pontos, fn (int $i): bool => $i % 2 === 0, ARRAY_FILTER_USE_KEY));
        $lats = array_values(array_filter($pontos, fn (int $i): bool => $i % 2 === 1, ARRAY_FILTER_USE_KEY));

        return [min($lngs), min($lats), max($lngs), max($lats)];
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    private function url(string $typeName, array $bbox): string
    {
        $base = rtrim((string) Settings::get('integrations.geoserver.base_url', config('viabiliza.integrations.geoserver.base_url')), '/');

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'CQL_FILTER' => $this->filtroBbox($bbox),
        ]);
    }

    /**
     * Ordem lng/lat com o CRS explícito: conferida em produção (2026-10-02).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    private function filtroBbox(array $bbox): string
    {
        return sprintf("BBOX(GEOMETRY,%.7f,%.7f,%.7f,%.7f,'EPSG:4326')", $bbox[0], $bbox[1], $bbox[2], $bbox[3]);
    }
}
