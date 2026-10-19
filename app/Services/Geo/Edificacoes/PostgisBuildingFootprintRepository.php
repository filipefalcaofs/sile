<?php

namespace App\Services\Geo\Edificacoes;

use App\Enums\FonteEdificacao;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use Illuminate\Support\Facades\DB;

/**
 * SQL espacial da base de edificações. Ponto sempre como (lng, lat) no
 * ST_MakePoint; área em metros pelo cast ::geography.
 */
class PostgisBuildingFootprintRepository implements BuildingFootprintRepository
{
    public function contendoPonto(FonteEdificacao $fonte, float $lat, float $lng): ?EdificacaoCandidata
    {
        $linhas = DB::table('building_footprints')
            ->where('source', $fonte->value)
            ->whereNull('retired_at')
            ->whereNull('duplicate_of_id')
            ->whereRaw('ST_Covers(geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326))', [$lng, $lat])
            ->orderBy('area_m2')
            ->selectRaw('id, source_id, release, area_m2, confidence, ST_AsGeoJSON(geometry) AS geojson')
            ->limit(2)
            ->get();

        $primeira = $linhas->first();

        if ($primeira === null) {
            return null;
        }

        return new EdificacaoCandidata(
            id: (int) $primeira->id,
            fonte: $fonte,
            sourceId: (string) $primeira->source_id,
            release: (string) $primeira->release,
            geojson: (array) json_decode((string) $primeira->geojson, true),
            areaM2: (float) $primeira->area_m2,
            confidence: $primeira->confidence === null ? null : (float) $primeira->confidence,
            metodo: $linhas->count() > 1 ? EdificacaoCandidata::MENOR_AREA : EdificacaoCandidata::CONTEM_PONTO,
        );
    }

    public function normalizar(array $geojson): array
    {
        $texto = json_encode($geojson);

        $linha = DB::selectOne(<<<'SQL'
            WITH entrada AS (
                SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS g
            ), corrigida AS (
                SELECT g, ST_IsValid(g) AS valida_original,
                       ST_Multi(ST_CollectionExtract(ST_MakeValid(g), 3)) AS m
                FROM entrada
            )
            SELECT valida_original,
                   ST_IsEmpty(m) AS vazia,
                   ST_AsGeoJSON(m) AS geojson,
                   ST_Area(m::geography) AS area
            FROM corrigida
            SQL, [$texto]);

        $notas = [];

        if (! $linha->valida_original) {
            $notas[] = 'geometria_corrigida';
        }

        if ($linha->vazia) {
            return ['valida' => false, 'geojson' => null, 'area_m2' => null, 'notas' => [...$notas, 'sem_poligono']];
        }

        $area = round((float) $linha->area, 2);
        $minimo = (float) config('viabiliza.edificacoes.area_min_m2', 10);
        $maximo = (float) config('viabiliza.edificacoes.area_max_m2', 20000);

        if ($area < $minimo || $area > $maximo) {
            return ['valida' => false, 'geojson' => null, 'area_m2' => $area, 'notas' => [...$notas, 'area_fora_dos_limites']];
        }

        return [
            'valida' => true,
            'geojson' => (array) json_decode((string) $linha->geojson, true),
            'area_m2' => $area,
            'notas' => $notas,
        ];
    }

    public function dentroDoLimite(array $geojson): bool
    {
        $limite = $this->limiteVigente();

        if ($limite === null) {
            return false;
        }

        return DB::table('geo_features')
            ->where('geo_layer_id', $limite->id)
            ->whereRaw('ST_Covers(geometry, ST_PointOnSurface(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)))', [json_encode($geojson)])
            ->exists();
    }

    public function limiteDisponivel(): bool
    {
        $limite = $this->limiteVigente();

        return $limite !== null && DB::table('geo_features')->where('geo_layer_id', $limite->id)->exists();
    }

    public function duplicadoOverture(array $geojson, float $limiar = 0.5): ?int
    {
        $linha = DB::selectOne(<<<'SQL'
            WITH alvo AS (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS g)
            SELECT b.id,
                   ST_Area(ST_Intersection(b.geometry, alvo.g)::geography)
                   / NULLIF(ST_Area(ST_Union(b.geometry, alvo.g)::geography), 0) AS iou
            FROM building_footprints b, alvo
            WHERE b.source = ?
              AND b.retired_at IS NULL
              AND b.duplicate_of_id IS NULL
              AND ST_Intersects(b.geometry, alvo.g)
            ORDER BY iou DESC
            LIMIT 1
            SQL, [json_encode($geojson), FonteEdificacao::Overture->value]);

        return $linha !== null && (float) $linha->iou > $limiar ? (int) $linha->id : null;
    }

    public function upsert(
        FonteEdificacao $fonte,
        string $sourceId,
        string $release,
        array $geojson,
        float $areaM2,
        ?float $confidence,
        ?array $sourceDatasets,
        array $properties,
        ?int $importId,
        ?int $duplicateOfId,
        array $notas,
    ): array {
        $linha = DB::selectOne(<<<'SQL'
            INSERT INTO building_footprints
                (source, source_id, release, geometry, area_m2, confidence, source_datasets, properties,
                 import_id, duplicate_of_id, retired_at, validated_at, validation_notes, created_at, updated_at)
            VALUES (?, ?, ?, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), ?, ?, ?::jsonb, ?::jsonb,
                    ?, ?, NULL, now(), ?::jsonb, now(), now())
            ON CONFLICT (source, source_id) DO UPDATE SET
                release = EXCLUDED.release,
                geometry = EXCLUDED.geometry,
                area_m2 = EXCLUDED.area_m2,
                confidence = EXCLUDED.confidence,
                source_datasets = EXCLUDED.source_datasets,
                properties = EXCLUDED.properties,
                import_id = EXCLUDED.import_id,
                duplicate_of_id = EXCLUDED.duplicate_of_id,
                retired_at = NULL,
                validated_at = now(),
                validation_notes = EXCLUDED.validation_notes,
                updated_at = now()
            RETURNING id, (xmax = 0) AS inserido
            SQL, [
            $fonte->value,
            $sourceId,
            $release,
            json_encode($geojson),
            $areaM2,
            $confidence,
            $sourceDatasets === null ? null : json_encode($sourceDatasets),
            json_encode($properties === [] ? new \stdClass : $properties),
            $importId,
            $duplicateOfId,
            json_encode($notas),
        ]);

        return ['id' => (int) $linha->id, 'inserido' => (bool) $linha->inserido];
    }

    public function aposentarAusentes(FonteEdificacao $fonte, int $importId): int
    {
        return DB::table('building_footprints')
            ->where('source', $fonte->value)
            ->whereNull('retired_at')
            ->where(fn ($query) => $query->whereNull('import_id')->orWhere('import_id', '!=', $importId))
            ->update(['retired_at' => now(), 'updated_at' => now()]);
    }

    private function limiteVigente(): ?GeoLayer
    {
        return GeoLayer::query()->vigente(GeoLayerType::LimiteMunicipio)->first();
    }
}
