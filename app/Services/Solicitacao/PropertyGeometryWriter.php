<?php

namespace App\Services\Solicitacao;

use App\Models\ViabilityRequest;
use Illuminate\Support\Facades\DB;

/**
 * Geometria do imóvel da solicitação (HU-062/HU-063), driver-aware — espelha o
 * GeoJsonLayerImporter da Fase 4.
 *
 * A FONTE de verdade portável é o `property_polygon_geojson` (jsonb), gravado
 * pelo controller em qualquer driver. A coluna geometry derivada
 * `property_polygon` SÓ existe no PostgreSQL (em SQLite nem a coluna existe), e
 * é gravada via ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?),4326)) — nunca por
 * mass-assignment. Em SQLite o `write` é um no-op honesto; a área do polígono
 * cai numa aproximação planar só para o alerta orientativo (a área espacial REAL
 * é o ST_Area do Postgres).
 */
class PropertyGeometryWriter
{
    /**
     * Deriva `property_polygon` (geometry) a partir do GeoJSON fonte — SÓ no
     * pgsql. Idêntico ao GeoJsonLayerImporter: ST_MakeValid sobre a geometria em
     * 4326. No-op fora do Postgres (a coluna não existe; a fonte é o jsonb).
     */
    public function write(ViabilityRequest $request): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $geojson = $request->property_polygon_geojson;

        if (! is_array($geojson) || $geojson === []) {
            return;
        }

        DB::update(
            'UPDATE viability_requests
                SET property_polygon = ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))
              WHERE id = ?',
            [json_encode($geojson), $request->id],
        );
    }

    /**
     * Área do polígono em metros quadrados, driver-aware:
     * - pgsql: `ST_Area(::geography)` — área geodésica REAL (fonte de verdade).
     * - SQLite: aproximação planar (shoelace) sobre os vértices convertidos a
     *   metros — suficiente apenas para o alerta orientativo da suíte portável.
     *
     * Retorna null quando o GeoJSON não tem um anel com pelo menos 3 vértices.
     *
     * @param  array<string, mixed>  $geojson  GeoJSON Polygon (type + coordinates)
     */
    public function polygonAreaSquareMeters(array $geojson): ?float
    {
        $ring = $geojson['coordinates'][0] ?? null;

        if (! is_array($ring) || count($ring) < 3) {
            return null;
        }

        if (DB::getDriverName() === 'pgsql') {
            $row = DB::selectOne(
                'SELECT ST_Area(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography) AS area',
                [json_encode($geojson)],
            );

            return $row !== null && $row->area !== null ? round((float) $row->area, 2) : null;
        }

        return $this->planarAreaSquareMeters($ring);
    }

    /**
     * Aproximação planar da área (shoelace) convertendo graus em metros no
     * entorno do polígono. Usada SÓ em SQLite para o alerta orientativo — a área
     * espacial REAL é o ST_Area do Postgres (acima).
     *
     * @param  array<int, array<int, float|int>>  $ring
     */
    private function planarAreaSquareMeters(array $ring): float
    {
        $points = array_values($ring);

        // Fecha o anel se necessário para o shoelace.
        if ($points[0] !== $points[count($points) - 1]) {
            $points[] = $points[0];
        }

        $lats = array_map(static fn (array $point): float => (float) $point[1], $points);
        $latMedia = array_sum($lats) / count($lats);

        $metrosPorGrauLat = 111_320.0;
        $metrosPorGrauLng = 111_320.0 * cos(deg2rad($latMedia));

        $soma = 0.0;
        $total = count($points);

        for ($i = 0; $i < $total - 1; $i++) {
            $x1 = (float) $points[$i][0] * $metrosPorGrauLng;
            $y1 = (float) $points[$i][1] * $metrosPorGrauLat;
            $x2 = (float) $points[$i + 1][0] * $metrosPorGrauLng;
            $y2 = (float) $points[$i + 1][1] * $metrosPorGrauLat;

            $soma += ($x1 * $y2) - ($x2 * $y1);
        }

        return round(abs($soma) / 2.0, 2);
    }
}
