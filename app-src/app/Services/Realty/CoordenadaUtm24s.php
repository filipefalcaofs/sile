<?php

namespace App\Services\Realty;

/**
 * Conversão UTM zona 24 Sul, SIRGAS 2000 (EPSG:31984) → WGS84 (EPSG:4326).
 * A coordenada do BFF SEDUR/SEFAZ é do lote, não do endereço de correspondência.
 */
final class CoordenadaUtm24s
{
    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public static function paraWgs84(string|float|int|null $easting, string|float|int|null $northing): ?array
    {
        if ($easting === null || $northing === null || $easting === '' || $northing === '') {
            return null;
        }

        $x = (float) $easting;
        $y = (float) $northing;

        if ($x == 0.0 || $y == 0.0) {
            return null;
        }

        return self::converter($x, $y);
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    private static function converter(float $easting, float $northing): array
    {
        $a = 6378137.0;
        $f = 1 / 298.257222101;
        $k0 = 0.9996;
        $zone = 24;
        $e2 = (2 * $f) - ($f * $f);
        $ep2 = $e2 / (1 - $e2);
        $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));

        $x = $easting - 500000.0;
        $y = $northing - 10000000.0;

        $m = $y / $k0;
        $mu = $m / ($a * (1 - ($e2 / 4) - (3 * $e2 * $e2 / 64) - (5 * $e2 * $e2 * $e2 / 256)));

        $phi1 = $mu
            + ((3 * $e1 / 2) - (27 * ($e1 ** 3) / 32)) * sin(2 * $mu)
            + ((21 * ($e1 ** 2) / 16) - (55 * ($e1 ** 4) / 32)) * sin(4 * $mu)
            + (151 * ($e1 ** 3) / 96) * sin(6 * $mu);

        $sinPhi = sin($phi1);
        $cosPhi = cos($phi1);
        $tanPhi = tan($phi1);

        $n1 = $a / sqrt(1 - ($e2 * $sinPhi * $sinPhi));
        $t1 = $tanPhi * $tanPhi;
        $c1 = $ep2 * $cosPhi * $cosPhi;
        $r1 = $a * (1 - $e2) / ((1 - ($e2 * $sinPhi * $sinPhi)) ** 1.5);
        $d = $x / ($n1 * $k0);

        $lat = $phi1 - (($n1 * $tanPhi) / $r1) * (
            ($d * $d / 2)
            - ((5 + (3 * $t1) + (10 * $c1) - (4 * $c1 * $c1) - (9 * $ep2)) * ($d ** 4) / 24)
            + ((61 + (90 * $t1) + (298 * $c1) + (45 * $t1 * $t1) - (252 * $ep2) - (3 * $c1 * $c1)) * ($d ** 6) / 720)
        );

        $lon0 = deg2rad((($zone - 1) * 6) - 180 + 3);

        $lon = $lon0 + (
            $d
            - ((1 + (2 * $t1) + $c1) * ($d ** 3) / 6)
            + ((5 - (2 * $c1) + (28 * $t1) - (3 * $c1 * $c1) + (8 * $ep2) + (24 * $t1 * $t1)) * ($d ** 5) / 120)
        ) / $cosPhi;

        return [
            'latitude' => round(rad2deg($lat), 6),
            'longitude' => round(rad2deg($lon), 6),
        ];
    }
}
