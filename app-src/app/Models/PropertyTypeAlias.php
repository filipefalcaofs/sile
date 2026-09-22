<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Services\Risco\TipoImovel;
use Database\Factories\PropertyTypeAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Grafia alternativa (normalizada) que o REGIN pode enviar para um tipo de
 * imóvel. A normalização é a mesma do motor (TipoImovel::normalize) aplicada
 * na gravação — o match nunca depende de acento/caixa.
 */
#[Fillable(['property_type_id', 'alias'])]
class PropertyTypeAlias extends Model
{
    use HasAuditoria;

    /** @use HasFactory<PropertyTypeAliasFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(PropertyType::CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @return BelongsTo<PropertyType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    protected function alias(): Attribute
    {
        return Attribute::make(set: fn (string $value) => TipoImovel::normalize($value));
    }
}
