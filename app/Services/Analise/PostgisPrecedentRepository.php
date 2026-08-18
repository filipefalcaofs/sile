<?php

namespace App\Services\Analise;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Implementação real dos precedentes (HU-142) sobre PostGIS — espelha o
 * PostgisSpatialRepository: SQL nativo, sem cálculo geométrico em PHP. Reusa a
 * geometry derivada `property_polygon` (Polygon, 4326) gravada na Fase 8
 * (PropertyGeometryWriter). O GeoJSON atual entra em SRID 4326 explícito
 * (ST_SetSRID, como o writer) — sem isso o ST_Intersects estouraria por SRID
 * misto. LGPD (RN-004): a projeção NUNCA inclui dados pessoais do requerente.
 */
class PostgisPrecedentRepository implements PrecedentRepository
{
    public function propertyPrecedents(array $currentGeojson, int $limit, ?string $fallbackStreet = null, ?string $fallbackNumber = null): array
    {
        $query = DB::table('viability_requests as vr')
            ->join('viability_decisions as vd', 'vd.viability_request_id', '=', 'vr.id')
            ->leftJoin('viability_service_types as vst', 'vst.id', '=', 'vr.service_type_id')
            ->leftJoin('users as analyst', 'analyst.id', '=', 'vd.decided_by_user_id');

        if ($this->hasPolygon($currentGeojson)) {
            // SRID 4326 explícito nos DOIS operandos (igual ao PropertyGeometryWriter):
            // ST_GeomFromGeoJSON sem ST_SetSRID quebraria por SRID misto.
            $query->whereNotNull('vr.property_polygon')
                ->whereRaw(
                    'ST_Intersects(vr.property_polygon, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))',
                    [json_encode($currentGeojson)],
                );
        } elseif ($fallbackStreet !== null && $fallbackStreet !== '') {
            // Fallback portável (sem polígono): precedentes do imóvel por endereço.
            $query->where('vr.address_street', $fallbackStreet);

            if ($fallbackNumber !== null && $fallbackNumber !== '') {
                $query->where('vr.address_number', $fallbackNumber);
            }
        } else {
            return [];
        }

        return $query
            ->orderByDesc('vd.decided_at')
            ->limit($limit)
            ->get([
                'vr.id as viability_request_id',
                'vr.protocol_number',
                'vd.outcome',
                'vd.decided_at',
                'vst.name as service_type',
                'analyst.name as analyst',
            ])
            ->map(fn (object $row): array => [
                'viability_request_id' => (int) $row->viability_request_id,
                'protocol_number' => $row->protocol_number,
                'outcome' => (string) $row->outcome,
                'decided_at' => $row->decided_at !== null ? (string) $row->decided_at : null,
                'service_type' => $row->service_type,
                'analyst' => $row->analyst,
            ])
            ->all();
    }

    public function cnaeZoneStats(string $cnae, string $zona, DateTimeInterface $since): array
    {
        // A zona da decisão é resolvida pela FICHA VIGENTE (maior revisão) do
        // processo: engine_snapshot.por_cnae[].consulta.territorio.zona com
        // status 'identificado' e nome == :zona, para o CNAE == :cnae. Sem zona
        // identificada (caso comum: Quadro 10 pendente SEDUR) a decisão não entra
        // na contagem — o consumidor degrada honesto, esta query só roda com zona.
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE vd.outcome = 'deferida')   AS deferidos,
                COUNT(*) FILTER (WHERE vd.outcome = 'indeferida') AS indeferidos,
                COUNT(*)                                          AS total
            FROM viability_decisions vd
            WHERE vd.decided_at >= ?
              AND EXISTS (
                  SELECT 1
                  FROM analysis_records ar
                  WHERE ar.viability_request_id = vd.viability_request_id
                    AND ar.revision = (
                        SELECT MAX(ar2.revision)
                        FROM analysis_records ar2
                        WHERE ar2.viability_request_id = vd.viability_request_id
                    )
                    AND jsonb_typeof(ar.engine_snapshot -> 'por_cnae') = 'array'
                    AND EXISTS (
                        SELECT 1
                        FROM jsonb_array_elements(ar.engine_snapshot -> 'por_cnae') AS item
                        WHERE item ->> 'cnae' = ?
                          AND item -> 'consulta' -> 'territorio' -> 'zona' ->> 'status' = 'identificado'
                          AND item -> 'consulta' -> 'territorio' -> 'zona' ->> 'nome' = ?
                    )
              )
            SQL,
            [$this->sinceString($since), $cnae, $zona],
        );

        return [
            'deferidos' => (int) ($row->deferidos ?? 0),
            'indeferidos' => (int) ($row->indeferidos ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    /**
     * Há um anel de polígono utilizável no GeoJSON do imóvel atual?
     *
     * @param  array<string, mixed>  $geojson
     */
    private function hasPolygon(array $geojson): bool
    {
        $ring = $geojson['coordinates'][0] ?? null;

        return is_array($ring) && $ring !== [];
    }

    private function sinceString(DateTimeInterface $since): string
    {
        return $since->format('Y-m-d H:i:s');
    }
}
