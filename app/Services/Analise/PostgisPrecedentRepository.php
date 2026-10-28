<?php

namespace App\Services\Analise;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Implementação real dos precedentes (HU-132) sobre PostGIS — espelha o
 * PostgisSpatialRepository: SQL nativo, sem cálculo geométrico em PHP. Reusa a
 * geometry derivada `property_polygon` (Polygon, 4326) gravada na Fase 8
 * (PropertyGeometryWriter). O GeoJSON atual entra em SRID 4326 explícito
 * (ST_SetSRID, como o writer) — sem isso o ST_Intersects estouraria por SRID
 * misto. LGPD (RN-004): a projeção NUNCA inclui dados pessoais do requerente.
 */
class PostgisPrecedentRepository implements PrecedentRepository
{
    public function propertyPrecedents(array $currentGeojson, int $limit, ?string $fallbackStreet = null, ?string $fallbackNumber = null, ?int $exceptRequestId = null): array
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

        if ($exceptRequestId !== null) {
            $query->where('vr.id', '!=', $exceptRequestId);
        }

        return $query
            ->orderByDesc('vd.decided_at')
            ->limit($limit)
            ->get([
                'vr.id as viability_request_id',
                'vr.protocol_number',
                'vr.external_reference as bap',
                'vd.outcome',
                'vd.decided_at',
                'vst.name as service_type',
                'analyst.name as analyst',
            ])
            ->map(fn (object $row): array => [
                'viability_request_id' => (int) $row->viability_request_id,
                'protocol_number' => $row->protocol_number,
                'bap' => $row->bap,
                'outcome' => (string) $row->outcome,
                'decided_at' => $row->decided_at !== null ? (string) $row->decided_at : null,
                'service_type' => $row->service_type,
                'analyst' => $row->analyst,
            ])
            ->all();
    }

    public function cnaeZoneStats(string $cnae, string $zona, DateTimeInterface $since): array
    {
        // A zona da decisão é a GRAVADA no processo (zona_manual do analista, senão
        // o zona_codigo materializado na identificação do imóvel) e o CNAE é um dos
        // CNAEs da solicitação. Não depende do engine_snapshot da ficha: a maioria
        // das decisões não tem snapshot e ficava fora da contagem.
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE vd.outcome = 'deferida')   AS deferidos,
                COUNT(*) FILTER (WHERE vd.outcome = 'indeferida') AS indeferidos,
                COUNT(*)                                          AS total
            FROM viability_decisions vd
            JOIN viability_requests vr ON vr.id = vd.viability_request_id
            WHERE vd.decided_at >= ?
              AND COALESCE(NULLIF(vr.zona_manual, ''), vr.zona_codigo) = ?
              AND EXISTS (
                  SELECT 1
                  FROM viability_request_cnaes vrc
                  JOIN cnaes c ON c.id = vrc.cnae_id
                  WHERE vrc.viability_request_id = vr.id
                    AND c.code = ?
              )
            SQL,
            [$this->sinceString($since), $zona, $cnae],
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
