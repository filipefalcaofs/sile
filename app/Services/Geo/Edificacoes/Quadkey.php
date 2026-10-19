<?php

namespace App\Services\Geo\Edificacoes;

/**
 * Quadkeys (convenção Bing Maps) dos tiles que cruzam um retângulo. A base da
 * Microsoft é publicada por quadkey de nível 9.
 */
final class Quadkey
{
    /**
     * @return list<string>
     */
    public static function cobrindo(float $oeste, float $sul, float $leste, float $norte, int $nivel = 9): array
    {
        [$xMin, $yMin] = self::tile($oeste, $norte, $nivel);
        [$xMax, $yMax] = self::tile($leste, $sul, $nivel);

        $quadkeys = [];

        for ($y = $yMin; $y <= $yMax; $y++) {
            for ($x = $xMin; $x <= $xMax; $x++) {
                $quadkeys[] = self::chave($x, $y, $nivel);
            }
        }

        return $quadkeys;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function tile(float $lng, float $lat, int $nivel): array
    {
        $tiles = 2 ** $nivel;
        $lat = max(-85.05112878, min(85.05112878, $lat));
        $seno = sin(deg2rad($lat));

        $x = (int) floor(($lng + 180) / 360 * $tiles);
        $y = (int) floor((0.5 - log((1 + $seno) / (1 - $seno)) / (4 * M_PI)) * $tiles);

        return [min($tiles - 1, max(0, $x)), min($tiles - 1, max(0, $y))];
    }

    private static function chave(int $x, int $y, int $nivel): string
    {
        $chave = '';

        for ($i = $nivel; $i > 0; $i--) {
            $digito = 0;
            $mascara = 1 << ($i - 1);

            if (($x & $mascara) !== 0) {
                $digito += 1;
            }

            if (($y & $mascara) !== 0) {
                $digito += 2;
            }

            $chave .= $digito;
        }

        return $chave;
    }
}
