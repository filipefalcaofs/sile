<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mede a interseção em metros quadrados no PostGIS (geography).
 * Fora do PostgreSQL não calcula: uma área aproximada escolheria a zona errada.
 */
class PostgisZonaIntersecaoMedidor implements ZonaIntersecaoMedidor
{
    public function medir(array $lote, array $candidatas): ?array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }

        $medicoes = [];

        foreach ($candidatas as $candidata) {
            if ($candidata['linear']) {
                $medicoes[] = [
                    'zona' => $candidata['zona'],
                    'area_m2' => 0.0,
                    'linear' => true,
                ];

                continue;
            }

            try {
                $area = DB::selectOne(
                    'SELECT ST_Area(ST_Intersection(
                        ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)),
                        ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))
                    )::geography) AS area',
                    [json_encode($lote), json_encode($candidata['geometria'])],
                );
            } catch (Throwable) {
                return null;
            }

            $medicoes[] = [
                'zona' => $candidata['zona'],
                'area_m2' => (float) ($area->area ?? 0),
                'linear' => false,
            ];
        }

        return $medicoes;
    }
}
