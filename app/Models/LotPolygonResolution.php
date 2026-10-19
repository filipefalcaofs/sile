<?php

namespace App\Models;

use App\Enums\PoligonoFonte;
use App\Enums\StatusResolucaoPoligono;
use Database\Factories\LotPolygonResolutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tentativa de obter o polígono do lote numa fonte: procedência, método de
 * associação, ponto usado e latência.
 */
#[Fillable(['viability_request_id', 'source', 'building_footprint_id', 'source_id', 'release', 'status', 'method', 'official', 'area_m2', 'confidence', 'point_lat', 'point_lng', 'point_origin', 'reason', 'latency_ms'])]
class LotPolygonResolution extends Model
{
    /** @use HasFactory<LotPolygonResolutionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PoligonoFonte::class,
            'status' => StatusResolucaoPoligono::class,
            'official' => 'boolean',
            'area_m2' => 'float',
            'confidence' => 'float',
            'point_lat' => 'float',
            'point_lng' => 'float',
            'latency_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * @return BelongsTo<BuildingFootprint, $this>
     */
    public function edificacao(): BelongsTo
    {
        return $this->belongsTo(BuildingFootprint::class, 'building_footprint_id');
    }
}
