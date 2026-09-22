<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use Carbon\CarbonInterface;
use Database\Factories\GeoLayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Camada geográfica versionada (HU-036 RN-004). Cada (type, version) é uma
 * carga; valid_to nulo é a versão vigente. Auditoria automática via
 * HasAuditoria (RN-002 — a carga registra origem/responsável/versão).
 */
#[Fillable(['type', 'version', 'status', 'valid_from', 'valid_to', 'source', 'rules_version', 'feature_count'])]
class GeoLayer extends Model
{
    use HasAuditoria;

    /** @use HasFactory<GeoLayerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GeoLayerType::class,
            'status' => GeoLayerStatus::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'feature_count' => 'integer',
        ];
    }

    /**
     * Features (geometrias) desta carga.
     *
     * @return HasMany<GeoFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(GeoFeature::class);
    }

    /**
     * Versão vigente de um tipo de camada (valid_to nulo) — uso operacional.
     *
     * @param  Builder<GeoLayer>  $query
     * @return Builder<GeoLayer>
     */
    public function scopeVigente(Builder $query, GeoLayerType|string $type): Builder
    {
        return $query
            ->where('type', $type instanceof GeoLayerType ? $type->value : $type)
            ->whereNull('valid_to');
    }

    /**
     * Versão que estava vigente numa data específica — reprodução/auditoria
     * da decisão (mesma disciplina do versionamento de regras).
     *
     * @param  Builder<GeoLayer>  $query
     * @return Builder<GeoLayer>
     */
    public function scopeNaData(Builder $query, GeoLayerType|string $type, CarbonInterface $date): Builder
    {
        return $query
            ->where('type', $type instanceof GeoLayerType ? $type->value : $type)
            ->where('valid_from', '<=', $date)
            ->where(function (Builder $inner) use ($date) {
                $inner->whereNull('valid_to')->orWhere('valid_to', '>', $date);
            });
    }
}
