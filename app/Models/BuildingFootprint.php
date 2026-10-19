<?php

namespace App\Models;

use App\Enums\FonteEdificacao;
use Database\Factories\BuildingFootprintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Edificação complementar da base local (Overture, Microsoft ou IA). A
 * geometria é gravada pelo repositório via SQL espacial, fora do fillable.
 */
#[Fillable(['source', 'source_id', 'release', 'area_m2', 'confidence', 'source_datasets', 'properties', 'duplicate_of_id', 'import_id', 'retired_at', 'validated_at', 'validation_notes'])]
class BuildingFootprint extends Model
{
    /** @use HasFactory<BuildingFootprintFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => FonteEdificacao::class,
            'area_m2' => 'float',
            'confidence' => 'float',
            'source_datasets' => 'array',
            'properties' => 'array',
            'validation_notes' => 'array',
            'retired_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BuildingFootprintImport, $this>
     */
    public function importacao(): BelongsTo
    {
        return $this->belongsTo(BuildingFootprintImport::class, 'import_id');
    }
}
