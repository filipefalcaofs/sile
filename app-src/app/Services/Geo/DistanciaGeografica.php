<?php

namespace App\Services\Geo;

/**
 * Distâncias em metros sobre coordenadas WGS84 (lat/lng em graus). As linhas
 * seguem o GeoJSON: cada vértice é [lng, lat].
 */
final class DistanciaGeografica
{
    private const RAIO_TERRA_M = 6_371_008.8;

    public static function entrePontos(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::RAIO_TERRA_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * Distância do ponto até a linha mais próxima, medida até o segmento (não
     * só até os vértices). Null quando não há linha com dois ou mais vértices.
     *
     * @param  list<list<array{0: float|int, 1: float|int}>>  $linhas
     */
    public static function ateLinhas(float $lat, float $lng, array $linhas): ?float
    {
        $menor = null;

        foreach ($linhas as $linha) {
            for ($i = 0; $i < count($linha) - 1; $i++) {
                $distancia = self::ateSegmento($lat, $lng, $linha[$i], $linha[$i + 1]);
                $menor = $menor === null ? $distancia : min($menor, $distancia);
            }
        }

        return $menor;
    }

    /**
     * Centroide (média dos vértices do anel exterior) de um Polygon ou do
     * primeiro polígono de um MultiPolygon.
     *
     * @param  array<string, mixed>  $geojson
     * @return array{lat: float, lng: float}|null
     */
    public static function centroideDoPoligono(array $geojson): ?array
    {
        $coordenadas = $geojson['coordinates'] ?? null;

        if (! is_array($coordenadas)) {
            return null;
        }

        $anel = ($geojson['type'] ?? null) === 'MultiPolygon'
            ? ($coordenadas[0][0] ?? null)
            : ($coordenadas[0] ?? null);

        if (! is_array($anel) || $anel === []) {
            return null;
        }

        if (count($anel) > 1 && $anel[0] === $anel[count($anel) - 1]) {
            array_pop($anel);
        }

        return [
            'lat' => array_sum(array_map(static fn (array $p): float => (float) $p[1], $anel)) / count($anel),
            'lng' => array_sum(array_map(static fn (array $p): float => (float) $p[0], $anel)) / count($anel),
        ];
    }

    /**
     * @param  array{0: float|int, 1: float|int}  $a
     * @param  array{0: float|int, 1: float|int}  $b
     */
    private static function ateSegmento(float $lat, float $lng, array $a, array $b): float
    {
        $metrosPorGrauLat = deg2rad(1) * self::RAIO_TERRA_M;
        $metrosPorGrauLng = $metrosPorGrauLat * cos(deg2rad($lat));

        $ax = ((float) $a[0] - $lng) * $metrosPorGrauLng;
        $ay = ((float) $a[1] - $lat) * $metrosPorGrauLat;
        $bx = ((float) $b[0] - $lng) * $metrosPorGrauLng;
        $by = ((float) $b[1] - $lat) * $metrosPorGrauLat;

        $dx = $bx - $ax;
        $dy = $by - $ay;
        $comprimento2 = $dx * $dx + $dy * $dy;

        $t = $comprimento2 > 0 ? max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $comprimento2)) : 0.0;

        return hypot($ax + $t * $dx, $ay + $t * $dy);
    }
}
