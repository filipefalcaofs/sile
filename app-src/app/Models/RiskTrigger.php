<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\TipoGatilho;
use Database\Factories\RiskTriggerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Gatilho de risco parametrizado (categoria semi-expresso) que derruba o
 * encaminhamento para análise técnica com motivo auditável (HU-049/HU-051).
 * É DADO, não código: o admin liga/desliga via `ativo` (HU-014) e o CRUD dos
 * mantenedores (06-06) é auditado por HasAuditoria. O motor (06-05) consome
 * apenas os gatilhos vigentes via scope ativos().
 */
#[Fillable(['codigo', 'titulo', 'motivo', 'ativo', 'categoria'])]
class RiskTrigger extends Model
{
    use HasAuditoria;

    /** @use HasFactory<RiskTriggerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'codigo' => TipoGatilho::class,
            'ativo' => 'boolean',
        ];
    }

    /**
     * Apenas os gatilhos ativos (administráveis) — o que o motor de
     * encaminhamento (06-05) efetivamente avalia.
     *
     * @param  Builder<RiskTrigger>  $query
     * @return Builder<RiskTrigger>
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
