<?php

namespace App\Models;

use App\Enums\DecisionOutcome;
use Database\Factories\ViabilityDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro IMUTÁVEL (append-only) da decisão do fluxo expresso (HU-076/078),
 * 1:1 com a solicitação. Gravado uma única vez pelo FluxoExpressoService (09-05)
 * dentro da transação da decisão; nunca atualizado por negócio. É a fonte do
 * PDF/TVL (Fase 10) e da explicabilidade (Fase 12): guarda o veredito
 * consolidado, o veredito por CNAE (RN-009), as versões de regras da época
 * (RN-005), a fundamentação legal e o decision_trace passo a passo (HU-099 —
 * snapshot ADITIVO e imutável que a explicabilidade projeta sem recomputar;
 * null nas decisões legadas). tvl_product_number só existe no deferimento
 * (RN-007).
 *
 * NÃO usa HasAuditoria: a auditoria da decisão é SÍNCRONA no service (HU-078),
 * não um diff automático de atributos.
 */
#[Fillable([
    'viability_request_id',
    'flow',
    'outcome',
    'consolidated_result',
    'is_virtual_office_hq',
    'tvl_product_number',
    'per_cnae',
    'rules_versions',
    'fundamentacao',
    'decision_trace',
    'reason',
    'decided_by_user_id',
    'decided_at',
])]
class ViabilityDecision extends Model
{
    /** @use HasFactory<ViabilityDecisionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => DecisionOutcome::class,
            'is_virtual_office_hq' => 'boolean',
            'per_cnae' => 'array',
            'rules_versions' => 'array',
            'fundamentacao' => 'array',
            'decision_trace' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function isDeferida(): bool
    {
        return $this->outcome === DecisionOutcome::Deferida;
    }

    /**
     * Solicitação decidida (1:1 — unique viability_request_id).
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * Autor humano da decisão; null = decisão do sistema (fluxo automático).
     *
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
