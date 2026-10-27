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
 * zona ativa (o WAF da Prefeitura bloqueia polígono na URL; se também recusar o
 * BBOX do CQL_FILTER, a camada é repetida pelo parâmetro bbox) e calcula a área de
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
        $tolerancia = $this->toleranciaLinearM();

        return $this->memo[md5((string) json_encode($poligono).'|'.$tolerancia)] ??= $this->calcular($poligono, $tolerancia);
    }

    /**
     * @param  array<string, mixed>  $poligono
     */
    private function calcular(array $poligono, float $tolerancia): ZonaMaiorPorcao
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

        if ($tolerancia > 0) {
            $bbox = $this->ampliar($bbox, $tolerancia);
        }

        $typeNames = GeoServerLayer::query()->ativos()->get()
            ->map(fn (GeoServerLayer $camada): string => $camada->nomeCompleto())
            ->all();

        if ($typeNames === []) {
            return ZonaMaiorPorcao::indisponivel('Nenhuma FeatureType de zona configurada no GeoServer.');
        }

        try {
            $payloads = $this->consultarCamadas($typeNames, fn (string $typeName): string => $this->url($typeName, $bbox));

            $recusadas = array_keys(array_filter($payloads, fn (?array $payload): bool => $payload === null));

            if ($recusadas !== []) {
                $payloads = array_merge(
                    $payloads,
                    $this->consultarCamadas($recusadas, fn (string $typeName): string => $this->urlBboxParametro($typeName, $bbox)),
                );
            }
        } catch (Throwable $e) {
            return ZonaMaiorPorcao::indisponivel('GeoServer SEDUR indisponível: '.$e->getMessage());
        }

        $camadas = [];
        $candidatas = [];
        $lineares = [];
        $falhas = [];

        foreach ($typeNames as $typeName) {
            $payload = $payloads[$typeName] ?? null;

            if ($payload === null) {
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
                    $zonaLinear = $this->zonaLinear($typeName);

                    if ($zonaLinear !== null) {
                        $lineares[] = ['zona' => $zonaLinear, 'geometria' => $geometria];
                    }

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

        return $this->centralidadeLinear($poligono, $lineares, $this->porcoes($poligono, $candidatas, $camadas), $tolerancia);
    }

    /**
     * Centralidade linear (HU.182): a linha da ZCLMu ou da ZCLMe que passa por
     * cima do lote, em qualquer parte, define a zona, sem olhar o percentual em
     * outras zonas. Linha em frente ao lote, sem tocá-lo, não conta, a menos que a
     * SEDUR configure uma tolerância em metros (parâmetro
     * `territorio.zona_linear.tolerancia_m`): então a linha a até essa distância do
     * lote também conta. Duas zonas lineares sobre o mesmo lote não têm vencedora.
     *
     * @param  array<string, mixed>  $poligono
     * @param  list<array{zona: string, geometria: array<string, mixed>}>  $lineares
     */
    private function centralidadeLinear(array $poligono, array $lineares, ZonaMaiorPorcao $base, float $tolerancia = 0.0): ZonaMaiorPorcao
    {
        $cruzadas = [];

        foreach ($lineares as $linear) {
            $linha = $tolerancia > 0
                ? DB::selectOne(
                    'SELECT ST_DWithin(
                        ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))::geography,
                        ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography,
                        CAST(? AS double precision)
                    )::int AS cruza',
                    [json_encode($poligono), json_encode($linear['geometria']), $tolerancia],
                )
                : DB::selectOne(
                    'SELECT ST_Intersects(
                        ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)),
                        ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)
                    )::int AS cruza',
                    [json_encode($poligono), json_encode($linear['geometria'])],
                );

            if ($linha !== null && (int) $linha->cruza === 1) {
                $cruzadas[] = $linear['zona'];
            }
        }

        $zonas = array_values(array_intersect(['ZCLMu', 'ZCLMe'], $cruzadas));

        if ($zonas === []) {
            return $base;
        }

        return new ZonaMaiorPorcao(
            count($zonas) > 1 ? ZonaMaiorPorcao::CONFLITO_LINEAR : ZonaMaiorPorcao::CENTRALIDADE_LINEAR,
            codigo: count($zonas) > 1 ? null : $zonas[0],
            porcoes: $base->porcoes,
            empate: count($zonas) > 1 ? $zonas : [],
            camadas: $base->camadas,
            areaLoteM2: $base->areaLoteM2,
        );
    }

    /**
     * Tolerância, em metros, para a linha da centralidade linear contar quando
     * passa perto do lote. Zero (padrão) mantém a regra de passar por cima.
     */
    private function toleranciaLinearM(): float
    {
        return max(0.0, min(500.0, (float) Settings::get('territorio.zona_linear.tolerancia_m', 0)));
    }

    /**
     * Aumenta o recorte pedido ao GeoServer para a linha próxima, que fica fora
     * do retângulo do lote, vir na resposta.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox  [lngMin, latMin, lngMax, latMax]
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function ampliar(array $bbox, float $metros): array
    {
        $graus = $metros / 111320.0;
        $latitudeMedia = ($bbox[1] + $bbox[3]) / 2;
        $grausLng = $graus / max(0.2, cos(deg2rad($latitudeMedia)));

        return [$bbox[0] - $grausLng, $bbox[1] - $graus, $bbox[2] + $grausLng, $bbox[3] + $graus];
    }

    private function zonaLinear(string $typeName): ?string
    {
        $nome = strtoupper($typeName);

        return match (true) {
            str_contains($nome, 'ZCLME') => 'ZCLMe',
            str_contains($nome, 'ZCLMU') => 'ZCLMu',
            default => null,
        };
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
     * Consulta as camadas em paralelo. Resposta que não seja uma
     * FeatureCollection (erro HTTP, página de bloqueio do WAF) vira null.
     *
     * @param  list<string>  $typeNames
     * @param  callable(string): string  $urlDe
     * @return array<string, array<string, mixed>|null>
     */
    private function consultarCamadas(array $typeNames, callable $urlDe): array
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
     * Mesmo recorte do BBOX do CQL_FILTER, pelo parâmetro bbox do WFS: sem
     * expressão no valor, usado quando o WAF recusa o CQL_FILTER.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    private function urlBboxParametro(string $typeName, array $bbox): string
    {
        $base = rtrim((string) Settings::get('integrations.geoserver.base_url', config('viabiliza.integrations.geoserver.base_url')), '/');

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'bbox' => sprintf('%.7f,%.7f,%.7f,%.7f,EPSG:4326', $bbox[0], $bbox[1], $bbox[2], $bbox[3]),
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
