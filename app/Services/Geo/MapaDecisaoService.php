<?php

namespace App\Services\Geo;

use App\Models\GeoServerLayer;
use App\Models\ViabilityRequest;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Camadas que justificam, no mapa do processo, a zona e a via da decisão: o lote,
 * as zonas em volta (com as porções do lote em cada uma), as linhas de
 * centralidade linear (ZCLMu e ZCLMe) e as vias com a escolhida em destaque.
 * Só leitura: reaproveita a zona e o CODLOG já gravados no processo e não
 * altera nenhuma regra de decisão. Medidas e recortes exigem PostGIS; sem ele
 * o mapa mostra as camadas inteiras, sem metragem.
 */
class MapaDecisaoService
{
    /** Folga, em graus, em volta do lote para enxergar zonas e linhas vizinhas (~165 m). */
    private const FOLGA_GRAUS = 0.0015;

    private const LIMITE_VIAS = 150;

    public function __construct(private GeoServerEntornoClient $entorno) {}

    /**
     * @return array<string, mixed>
     */
    public function montar(ViabilityRequest $request): array
    {
        $enderecamento = $this->poligono($request->address_polygon_geojson);
        $lote = $this->poligono($request->property_polygon_geojson);
        $base = $enderecamento ?? $lote;
        $ponto = $request->pontoDoImovel();
        $bbox = $this->bbox($base, $ponto);

        $resultado = [
            'lote' => $lote,
            'enderecamento' => $enderecamento,
            'ponto' => $ponto,
            'area_lote_m2' => null,
            'zona' => $request->zona_codigo === null ? null : [
                'codigo' => $request->zona_codigo,
                'fonte' => $request->zona_fonte?->label(),
            ],
            'via' => null,
            'zonas' => [],
            'porcoes' => [],
            'linhas' => [],
            'vias' => [],
            'indisponiveis' => [],
            // Nenhuma fonte confirmou o local: a ficha avisa e marca o lote do ponto.
            'localizacao' => $request->localizacaoNaoConfirmada(),
        ];

        $codLog = $this->codLogDaDecisao($request);
        $resultado['via'] = $codLog === null ? null : $this->viaDaDecisao($request, $codLog);

        if ($bbox === null || ! Settings::enabled('geoserver_zona')) {
            return $resultado;
        }

        $zonasTypeNames = $this->typeNamesDeZona();
        $viaTypeName = (string) config('viabiliza.integrations.geoserver.logradouro.type_name', 'logradouros:VM_LOGRADOURO');
        $classeTypeName = (string) config('viabiliza.integrations.geoserver.classificacao_viaria.type_name', 'louos_classificacao_viaria:VM_L_CLASSIF_VIARIA');

        $camadas = $this->entorno->consultar(array_values(array_unique([...$zonasTypeNames, $viaTypeName, $classeTypeName])), $bbox);

        foreach ($camadas as $typeName => $features) {
            if ($features === null) {
                $resultado['indisponiveis'][] = $typeName;
            }
        }

        [$zonas, $linhas] = $this->zonasELinhas($zonasTypeNames, $camadas, $request->zona_codigo);

        $postgis = DB::getDriverName() === 'pgsql';
        $recorte = $postgis ? $this->recorteDoBbox($bbox) : null;

        $resultado['zonas'] = $postgis ? $this->recortarZonas($zonas, $recorte) : $zonas;
        $resultado['linhas'] = $postgis ? $this->medirLinhas($linhas, $base, $recorte) : $linhas;
        $resultado['area_lote_m2'] = $postgis && $base !== null ? $this->areaDoLote($base) : null;
        $resultado['porcoes'] = $resultado['area_lote_m2'] !== null ? $this->porcoes($zonas, $base, $resultado['area_lote_m2'], $request->zona_codigo) : [];
        $resultado['vias'] = $this->vias($camadas[$viaTypeName] ?? [], $camadas[$classeTypeName] ?? [], $codLog);

        if ($resultado['via'] !== null && $resultado['via']['nome'] === null) {
            $resultado['via']['nome'] = collect($resultado['vias'])->firstWhere('da_decisao', true)['nome'] ?? null;
        }

        return $resultado;
    }

    /**
     * @return array{cod_log: string, nome: ?string, classe: ?string}
     */
    private function viaDaDecisao(ViabilityRequest $request, string $codLog): array
    {
        $inventario = is_array($request->camadas_incidentes) ? $request->camadas_incidentes : [];
        $nome = null;

        foreach (['logradouro', 'logradouro_tributario'] as $chave) {
            $logradouro = is_array($inventario[$chave] ?? null) ? $inventario[$chave] : [];

            if (($logradouro['cod_log'] ?? null) === $codLog && is_string($logradouro['nome'] ?? null)) {
                $nome = $logradouro['nome'];
                break;
            }
        }

        return [
            'cod_log' => $codLog,
            'nome' => $nome,
            'classe' => is_string($inventario['via'] ?? null) ? $inventario['via'] : null,
        ];
    }

    private function codLogDaDecisao(ViabilityRequest $request): ?string
    {
        $inventario = is_array($request->camadas_incidentes) ? $request->camadas_incidentes : [];
        $codLog = $request->cod_log ?: ($inventario['logradouro']['cod_log'] ?? $inventario['logradouro_tributario']['cod_log'] ?? null);

        return is_scalar($codLog) && trim((string) $codLog) !== '' ? trim((string) $codLog) : null;
    }

    /**
     * @return list<string>
     */
    private function typeNamesDeZona(): array
    {
        try {
            return GeoServerLayer::query()->ativos()->get()
                ->map(fn (GeoServerLayer $camada): string => $camada->nomeCompleto())
                ->all();
        } catch (QueryException) {
            $nomes = config('viabiliza.integrations.geoserver.type_names', []);

            return array_values(array_filter(is_array($nomes) ? $nomes : [], fn (mixed $nome): bool => is_string($nome) && $nome !== ''));
        }
    }

    /**
     * Separa as feições das camadas de zona em áreas e linhas de centralidade.
     *
     * @param  list<string>  $typeNames
     * @param  array<string, list<array<string, mixed>>|null>  $camadas
     * @return array{0: list<array{codigo: string, da_decisao: bool, geojson: array<string, mixed>}>, 1: list<array{tipo: string, nome: ?string, geojson: array<string, mixed>}>}
     */
    private function zonasELinhas(array $typeNames, array $camadas, ?string $zonaDaDecisao): array
    {
        $zonas = [];
        $linhas = [];

        foreach ($typeNames as $typeName) {
            foreach ($camadas[$typeName] ?? [] as $feature) {
                $geometria = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;
                $propriedades = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
                $tipo = $geometria['type'] ?? null;

                if (in_array($tipo, ['LineString', 'MultiLineString'], true)) {
                    $linhas[] = [
                        'tipo' => str_contains(strtoupper($typeName), 'ZCLME') ? 'ZCLMe' : 'ZCLMu',
                        'nome' => $this->nome($propriedades),
                        'geojson' => $geometria,
                    ];

                    continue;
                }

                if (! in_array($tipo, ['Polygon', 'MultiPolygon'], true)) {
                    continue;
                }

                $codigo = GeoServerWfsZonaClient::codigoZona($propriedades, $typeName);

                if ($codigo !== null) {
                    $zonas[] = ['codigo' => $codigo, 'da_decisao' => $codigo === $zonaDaDecisao, 'geojson' => $geometria];
                }
            }
        }

        return [$zonas, $linhas];
    }

    /**
     * @param  list<array<string, mixed>>  $logradouros
     * @param  list<array<string, mixed>>  $classificadas
     * @return list<array{cod_log: ?string, nome: ?string, classe: ?string, da_decisao: bool, geojson: array<string, mixed>}>
     */
    private function vias(array $logradouros, array $classificadas, ?string $codLogDaDecisao): array
    {
        $atributoClasse = (string) config('viabiliza.integrations.geoserver.classificacao_viaria.atributo', 'CLASSIFICACAO_VIARIA');
        $classes = [];

        foreach ($classificadas as $feature) {
            $propriedades = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $codLog = $this->texto($propriedades['CODLOG'] ?? null);
            $classe = $this->texto($propriedades[$atributoClasse] ?? null);

            if ($codLog !== null && $classe !== null) {
                $classes[$codLog] = $classe;
            }
        }

        $vias = [];

        foreach ($logradouros as $feature) {
            $geometria = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;

            if (! in_array($geometria['type'] ?? null, ['LineString', 'MultiLineString'], true)) {
                continue;
            }

            $propriedades = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $codLog = $this->texto($propriedades['CODLOG'] ?? null);

            $vias[] = [
                'cod_log' => $codLog,
                'nome' => $this->nome($propriedades),
                'classe' => $codLog === null ? null : ($classes[$codLog] ?? $this->texto($propriedades['HIERARQUIA'] ?? null)),
                'da_decisao' => $codLog !== null && $codLog === $codLogDaDecisao,
                'geojson' => $geometria,
            ];
        }

        usort($vias, fn (array $a, array $b): int => (int) $b['da_decisao'] <=> (int) $a['da_decisao']);

        return array_slice($vias, 0, self::LIMITE_VIAS);
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private function nome(array $propriedades): ?string
    {
        foreach (['NOME_LOGRADOURO', 'NOME_VIA', 'LOGRADOURO', 'NOME', 'DESCRICAO'] as $chave) {
            $valor = $this->texto($propriedades[$chave] ?? null);

            if ($valor !== null) {
                return $valor;
            }
        }

        return null;
    }

    private function texto(mixed $valor): ?string
    {
        return is_scalar($valor) && trim((string) $valor) !== '' ? trim((string) $valor) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function poligono(mixed $geometria): ?array
    {
        return is_array($geometria) && in_array($geometria['type'] ?? null, ['Polygon', 'MultiPolygon'], true) && is_array($geometria['coordinates'] ?? null)
            ? $geometria
            : null;
    }

    /**
     * @param  array<string, mixed>|null  $poligono
     * @param  array{lat: float, lng: float}|null  $ponto
     * @return array{0: float, 1: float, 2: float, 3: float}|null [lngMin, latMin, lngMax, latMax]
     */
    private function bbox(?array $poligono, ?array $ponto): ?array
    {
        $lngs = [];
        $lats = [];

        if ($poligono !== null) {
            array_walk_recursive($poligono['coordinates'], function (mixed $valor, mixed $chave) use (&$lngs, &$lats): void {
                if ($chave === 0) {
                    $lngs[] = (float) $valor;
                } elseif ($chave === 1) {
                    $lats[] = (float) $valor;
                }
            });
        }

        if ($lngs === [] && $ponto !== null) {
            $lngs = [$ponto['lng']];
            $lats = [$ponto['lat']];
        }

        if ($lngs === [] || $lats === []) {
            return null;
        }

        return [min($lngs) - self::FOLGA_GRAUS, min($lats) - self::FOLGA_GRAUS, max($lngs) + self::FOLGA_GRAUS, max($lats) + self::FOLGA_GRAUS];
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     * @return array<string, mixed>
     */
    private function recorteDoBbox(array $bbox): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[[$bbox[0], $bbox[1]], [$bbox[2], $bbox[1]], [$bbox[2], $bbox[3]], [$bbox[0], $bbox[3]], [$bbox[0], $bbox[1]]]],
        ];
    }

    /**
     * Recorta cada zona ao quadro do mapa (zonas inteiras chegam a megabytes).
     *
     * @param  list<array{codigo: string, da_decisao: bool, geojson: array<string, mixed>}>  $zonas
     * @param  array<string, mixed>|null  $recorte
     * @return list<array{codigo: string, da_decisao: bool, geojson: array<string, mixed>}>
     */
    private function recortarZonas(array $zonas, ?array $recorte): array
    {
        $recortadas = [];

        foreach ($zonas as $zona) {
            $geojson = $this->geojson(
                'SELECT ST_AsGeoJSON(ST_CollectionExtract(ST_Intersection(ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)), ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)), 3), 6) AS geojson',
                [json_encode($zona['geojson']), json_encode($recorte)],
            );

            if ($geojson !== null) {
                $recortadas[] = ['geojson' => $geojson] + $zona;
            }
        }

        return $recortadas;
    }

    /**
     * Distância do lote e metragem da linha dentro dele; a linha é recortada ao
     * quadro do mapa e a mais curta ligação até o lote vira o traço do mapa.
     *
     * @param  list<array{tipo: string, nome: ?string, geojson: array<string, mixed>}>  $linhas
     * @param  array<string, mixed>|null  $base
     * @param  array<string, mixed>|null  $recorte
     * @return list<array<string, mixed>>
     */
    private function medirLinhas(array $linhas, ?array $base, ?array $recorte): array
    {
        $medidas = [];

        foreach ($linhas as $linha) {
            $medida = $linha + ['passa_sobre_lote' => null, 'dentro_m' => null, 'distancia_m' => null, 'ligacao' => null];

            try {
                if ($base !== null) {
                    $registro = DB::selectOne(
                        'WITH g AS (SELECT ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) AS lote, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS linha)
                         SELECT ST_Intersects(lote, linha) AS passa,
                                ST_Length(ST_Intersection(lote, linha)::geography) AS dentro,
                                ST_Distance(lote::geography, linha::geography) AS distancia,
                                ST_AsGeoJSON(ST_ShortestLine(lote, linha), 6) AS ligacao
                         FROM g',
                        [json_encode($base), json_encode($linha['geojson'])],
                    );

                    if ($registro !== null) {
                        $medida['passa_sobre_lote'] = (bool) $registro->passa;
                        $medida['dentro_m'] = round((float) $registro->dentro, 1);
                        $medida['distancia_m'] = round((float) $registro->distancia, 1);
                        $medida['ligacao'] = json_decode((string) $registro->ligacao, true);
                    }
                }
            } catch (Throwable) {
                // Sem a medida, a linha segue no mapa sem metragem.
            }

            $recortada = $this->geojson(
                'SELECT ST_AsGeoJSON(ST_CollectionExtract(ST_Intersection(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)), 2), 6) AS geojson',
                [json_encode($linha['geojson']), json_encode($recorte)],
            );

            if ($recortada !== null) {
                $medidas[] = ['geojson' => $recortada] + $medida;
            }
        }

        return $medidas;
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function areaDoLote(array $base): ?float
    {
        try {
            $registro = DB::selectOne(
                'SELECT ST_Area(ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))::geography) AS area',
                [json_encode($base)],
            );
        } catch (Throwable) {
            return null;
        }

        return $registro === null || $registro->area === null ? null : round((float) $registro->area, 2);
    }

    /**
     * Parte do lote em cada zona, com a área e o percentual: a mesma conta da
     * maior porção (interseção com o polígono do lote), para o mapa explicar a
     * zona gravada.
     *
     * @param  list<array{codigo: string, da_decisao: bool, geojson: array<string, mixed>}>  $zonas
     * @param  array<string, mixed>  $base
     * @return list<array{zona: string, area_m2: float, percentual: float, da_decisao: bool, geojson: array<string, mixed>}>
     */
    private function porcoes(array $zonas, array $base, float $areaLote, ?string $zonaDaDecisao): array
    {
        $porZona = [];

        foreach ($zonas as $zona) {
            try {
                $registro = DB::selectOne(
                    'WITH p AS (SELECT ST_CollectionExtract(ST_Intersection(ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)), ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))), 3) AS geom)
                     SELECT ST_Area(geom::geography) AS area, ST_AsGeoJSON(geom, 6) AS geojson FROM p',
                    [json_encode($base), json_encode($zona['geojson'])],
                );
            } catch (Throwable) {
                continue;
            }

            $area = $registro === null ? 0.0 : (float) $registro->area;

            if ($area <= 0.0) {
                continue;
            }

            $porZona[$zona['codigo']]['area'] = ($porZona[$zona['codigo']]['area'] ?? 0.0) + $area;
            $porZona[$zona['codigo']]['geometrias'][] = json_decode((string) $registro->geojson, true);
        }

        uasort($porZona, fn (array $a, array $b): int => $b['area'] <=> $a['area']);

        $porcoes = [];

        foreach ($porZona as $codigo => $dados) {
            $geometrias = array_values(array_filter($dados['geometrias'], 'is_array'));

            $porcoes[] = [
                'zona' => (string) $codigo,
                'area_m2' => round($dados['area'], 2),
                'percentual' => $areaLote > 0.0 ? round($dados['area'] / $areaLote * 100, 1) : 0.0,
                'da_decisao' => (string) $codigo === $zonaDaDecisao,
                'geojson' => count($geometrias) === 1
                    ? $geometrias[0]
                    : ['type' => 'GeometryCollection', 'geometries' => $geometrias],
            ];
        }

        return $porcoes;
    }

    /**
     * @param  list<mixed>  $bindings
     * @return array<string, mixed>|null null quando o recorte é vazio ou o PostGIS falha
     */
    private function geojson(string $sql, array $bindings): ?array
    {
        try {
            $registro = DB::selectOne($sql, $bindings);
        } catch (Throwable) {
            return null;
        }

        $geometria = $registro === null || $registro->geojson === null ? null : json_decode((string) $registro->geojson, true);

        if (! is_array($geometria) || ($geometria['coordinates'] ?? []) === [] && ($geometria['geometries'] ?? []) === []) {
            return null;
        }

        return $geometria;
    }
}
