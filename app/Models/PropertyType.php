<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\PropertyTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Tipo de imóvel reconhecido do REGIN (SEDUR 2026-08-26) — dado administrável.
 * drives_rule=true injeta o gatilho dados_do_processo e derruba o processo do
 * expresso para análise; por isso a edição é auditada (HasAuditoria, RN-002)
 * e a desativação degrada para "desconhecido" (vai à análise), nunca para
 * decisão automática.
 */
#[Fillable(['code', 'label', 'drives_rule', 'active'])]
class PropertyType extends Model
{
    use HasAuditoria;

    /** @use HasFactory<PropertyTypeFactory> */
    use HasFactory;

    public const CACHE_KEY = 'sile.property_types.catalogo';

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'drives_rule' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PropertyTypeAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(PropertyTypeAlias::class);
    }

    /**
     * @param  Builder<PropertyType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
