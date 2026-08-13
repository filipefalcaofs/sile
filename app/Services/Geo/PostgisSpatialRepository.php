<?php

namespace App\Services\Geo;

use App\Models\GeoLayer;
use Illuminate\Support\Facades\DB;

/**
 * Implementação real do SQL espacial sobre PostGIS (HU-031 a HU-035). Todo
 * acesso filtra por `geo_layer_id` (a carga já resolvida pela vigência) e usa
 * as funções nativas do PostGIS — sem cálculo geométrico em PHP (Don't
 * Hand-Roll). O ponto entra sempre como (longitude, latitude): ST_MakePoint
 * usa [lng, lat] (Pitfall 3).
 */
class PostgisSpatialRepository implements SpatialRepository
{
    public function containingFeature(GeoLayer $layer, float $lng, float $lat): ?array
    {
        $row = DB::table('geo_features')
            ->where('geo_layer_id', $layer->id)
            ->whereRaw('ST_Contains(geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326))', [$lng, $lat])
            ->selectRaw('id, properties')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'properties' => $this->decodeProperties($row->properties),
        ];
    }

    public function nearestFeature(GeoLayer $layer, float $lng, float $lat, int $maxMeters): ?array
    {
        // Distância em METROS exige o cast ::geography (Pitfall 4): em
        // geometry(4326) ST_Distance devolveria graus.
        $row = DB::table('geo_features')
            ->where('geo_layer_id', $layer->id)
            ->whereRaw('ST_DWithin(geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $maxMeters])
            ->orderByRaw('ST_Distance(geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)', [$lng, $lat])
            ->selectRaw('id, properties, ST_Distance(geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS distancia_m', [$lng, $lat])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'properties' => $this->decodeProperties($row->properties),
            'distancia_m' => (float) $row->distancia_m,
        ];
    }

    public function intersectingFeatures(GeoLayer $layer, float $lng, float $lat): array
    {
        return DB::table('geo_features')
            ->where('geo_layer_id', $layer->id)
            ->whereRaw('ST_Intersects(geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326))', [$lng, $lat])
            ->selectRaw('id, properties')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'properties' => $this->decodeProperties($row->properties),
            ])
            ->all();
    }

    /**
     * A coluna `properties` (jsonb) volta como string em consulta via query
     * builder — decodifica para array; nunca quebra em valor inesperado.
     *
     * @return array<string, mixed>
     */
    private function decodeProperties(mixed $properties): array
    {
        if (is_array($properties)) {
            return $properties;
        }

        if (! is_string($properties) || $properties === '') {
            return [];
        }

        $decoded = json_decode($properties, true);

        return is_array($decoded) ? $decoded : [];
    }
}
