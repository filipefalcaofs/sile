<?php

namespace App\Models;

use App\Enums\DecisionOutcome;
use App\Enums\TvlKind;
use App\Services\Relatorios\TempoViabilidadeRegistrar;
use Database\Factories\ViabilityDecisionFactory;
use DomainException;
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
    'is_virtual_office_tenant',
    'virtual_office_hq_tvl_number',
    'tvl_product_number',
    'tvl_kind',
    'tvl_valid_until',
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
     * Todo indeferimento carrega a justificativa legível (reason) que quem
     * consulta o parecer lê: gestão, portal, PDF e REGIN.
     */
    protected static function booted(): void
    {
        static::saving(function (ViabilityDecision $decision): void {
            if ($decision->outcome === DecisionOutcome::Indeferida && trim((string) $decision->reason) === '') {
                throw new DomainException('Indeferimento sem justificativa: informe o motivo (reason) da decisão.');
            }
        });

        // O tempo de viabilidade é materializado aqui, e não em cada serviço
        // que decide, porque seis caminhos distintos registram decisão
        // (expresso, análise técnica, conclusão, prazo de convite, ausência de
        // BAP e resultado do expresso). O gancho no model é o único ponto que
        // cobre todos sem repetir a regra.
        static::created(function (ViabilityDecision $decision): void {
            app(TempoViabilidadeRegistrar::class)->registrar($decision);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => DecisionOutcome::class,
            'tvl_kind' => TvlKind::class,
            'tvl_valid_until' => 'date',
            'is_virtual_office_hq' => 'boolean',
            'is_virtual_office_tenant' => 'boolean',
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

    public function isIndeferida(): bool
    {
        return $this->outcome === DecisionOutcome::Indeferida;
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
