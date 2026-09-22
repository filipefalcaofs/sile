<?php

namespace App\Services\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Carrega um GeoJSON (FeatureCollection) oficial para geo_features, uma feição
 * por insert (Pitfall 6 — ST_GeomFromGeoJSON aceita só UMA geometria), com
 * ST_MakeValid na carga (Pitfall 8). A origem do GeoSalvador já vem em 4326
 * (outSR=4326), então não há ST_Transform — apenas ST_SetSRID fixando 4326.
 *
 * Idempotência por (type, version): o re-import de uma versão existente apaga
 * as feições daquela versão e reinsere (carga reproduzível), nunca duplica. A
 * carga de uma nova versão fecha a anterior (GeoLayerService) sem apagá-la e
 * audita o diff (RN-005).
 */
class GeoJsonLayerImporter
{
    public function __construct(private GeoLayerService $layers) {}

    /**
     * @param  array<string, mixed>  $featureCollection
     * @return array{lidas: int, inseridas: int, invalidas: array<int, string>, version: string, feature_count: int}
     */
    public function import(
        GeoLayerType $type,
        string $version,
        string $source,
        array $featureCollection,
    ): array {
        if (($featureCollection['type'] ?? null) !== 'FeatureCollection'
            || ! isset($featureCollection['features'])
            || ! is_array($featureCollection['features'])) {
            throw new InvalidArgumentException('GeoJSON inválido: esperado um FeatureCollection com features[].');
        }

        /** @var array<int, array<string, mixed>> $features */
        $features = array_values($featureCollection['features']);

        return DB::transaction(function () use ($type, $version, $source, $features): array {
            $previousVigente = GeoLayer::vigente($type)->first();

            $layer = $this->layers->openVersion($type, $version, $source);

            // Camada substituída (para o diff): só quando a carga abre uma versão
            // NOVA — no re-import da mesma versão openVersion devolve a própria.
            $substituted = ($previousVigente !== null && ! $previousVigente->is($layer))
                ? $previousVigente
                : null;

            // Idempotência real: limpa as feições desta versão antes de reinserir.
            $layer->features()->delete();

            $invalidas = [];
            $inseridas = 0;

            foreach ($features as $i => $feature) {
                $geometry = $feature['geometry'] ?? null;

                if ($geometry === null) {
                    $invalidas[] = "feição #{$i}: geometria ausente";

                    continue;
                }

                $geoJson = DB::connection()->getPdo()->quote(json_encode($geometry));

                DB::table('geo_features')->insert([
                    'geo_layer_id' => $layer->id,
                    'geometry' => DB::raw("ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON({$geoJson}), 4326))"),
                    'properties' => json_encode($feature['properties'] ?? []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $inseridas++;
            }

            $this->layers->finalizeCount($layer);

            $diff = $this->layers->computeDiff($substituted, $layer);
            $this->layers->auditLoad($layer, $diff);

            return [
                'lidas' => count($features),
                'inseridas' => $inseridas,
                'invalidas' => $invalidas,
                'version' => $layer->version,
                'feature_count' => $layer->feature_count,
            ];
        });
    }
}
