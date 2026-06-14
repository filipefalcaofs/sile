<?php

namespace App\Models;

use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Setor da SEDUR (HU-138) — a caixa de distribuição da análise técnica. Agrupa
 * os analistas (N:N via sector_user) que recebem os processos encaminhados.
 * active permite inativar sem excluir (setor com pendência não some, só deixa
 * de receber novas distribuições).
 */
#[Fillable(['name', 'active'])]
class Sector extends Model
{
    /** @use HasFactory<SectorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Analistas vinculados ao setor (HU-138 RN-005).
     *
     * @return BelongsToMany<User, $this>
     */
    public function analysts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sector_user')->withTimestamps();
    }

    /**
     * Solicitações distribuídas a este setor.
     *
     * @return HasMany<ViabilityRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(ViabilityRequest::class);
    }
}
