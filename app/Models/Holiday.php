<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feriado cadastrado (HU-137) — dado versionado/auditado (RN-002 via
 * HasAuditoria). O BusinessDeadlineCalculator (HU-129) desconta os feriados
 * ATIVOS ao medir duração em tempo útil. recurring_annually marca os feriados
 * fixos que valem todo ano (o provider compara mês/dia); active permite
 * inativar sem excluir. A lista municipal oficial de Salvador é pendência SEDUR
 * — degradação honesta, nunca feriado inventado.
 */
#[Fillable(['date', 'name', 'recurring_annually', 'active'])]
class Holiday extends Model
{
    use HasAuditoria;

    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'recurring_annually' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * Apenas os feriados ativos — o que o cálculo de dias úteis efetivamente
     * desconta (HU-129/HU-137).
     *
     * @param  Builder<Holiday>  $query
     * @return Builder<Holiday>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
