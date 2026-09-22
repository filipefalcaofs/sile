<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\ViabilityServiceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de serviço da solicitação (HU-061 RN-005) — dado administrável (CRUD
 * admin). flow_hint é uma pista textual de roteamento; active liga/desliga o
 * tipo na seleção do requerente. Auditoria automática via HasAuditoria
 * (RN-002): created/updated dos campos fillable.
 */
#[Fillable(['code', 'name', 'flow_hint', 'active'])]
class ViabilityServiceType extends Model
{
    use HasAuditoria;

    /** @use HasFactory<ViabilityServiceTypeFactory> */
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
     * @param  Builder<ViabilityServiceType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
