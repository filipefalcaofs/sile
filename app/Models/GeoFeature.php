<?php

namespace App\Models;

use Database\Factories\GeoFeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Geometria de uma camada (HU-036). A coluna `geometry` é PostGIS bruto: NÃO é
 * fillable nem castada — é gravada via DB::raw/ST_* e lida com ST_AsGeoJSON
 * quando necessário. Só geo_layer_id e properties são mass-assignable.
 */
#[Fillable(['geo_layer_id', 'properties'])]
class GeoFeature extends Model
{
    /** @use HasFactory<GeoFeatureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * Camada (carga versionada) à qual esta geometria pertence.
     *
     * @return BelongsTo<GeoLayer, $this>
     */
    public function layer(): BelongsTo
    {
        return $this->belongsTo(GeoLayer::class, 'geo_layer_id');
    }
}
