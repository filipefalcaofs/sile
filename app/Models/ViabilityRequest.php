<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\AnalysisCategory;
use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use Database\Factories\ViabilityRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Solicitação de viabilidade (EP08) — aggregate root com o imóvel embutido 1:1.
 * Auditoria automática via HasAuditoria (RN-002). protocol_number e status NÃO
 * são fillable: o número vem do ProtocolNumberGenerator e o status só muda pela
 * ViabilityRequestStateMachine (que audita a transição). A geometry derivada
 * property_polygon (só pgsql) também fica fora do fillable — a fonte é o jsonb
 * property_polygon_geojson, e a derivada é gravada via ST_* no plano 08-06.
 *
 * As colunas da análise técnica (EP10 — sector_id, assigned_user_id,
 * assigned_at, analysis_category, in_fine_mesh, analysis_stage,
 * analysis_stage_started_at, analysis_due_at) também ficam FORA do fillable:
 * são escritas pelos serviços de distribuição (HU-080/081) e de SLA (HU-144),
 * nunca pelo cidadão.
 */
#[Fillable([
    'origin',
    'service_type_id', 'company_id', 'requester_user_id', 'created_by_user_id', 'assisted_attendance_id',
    'used_area_m2', 'property_registration',
    'address_street', 'address_number', 'address_complement', 'address_neighborhood', 'address_zip', 'address_reference',
    'property_polygon_geojson',
    'is_virtual_office', 'wants_virtual_office_hq', 'is_public_area', 'has_independent_access',
    'simulation_snapshot', 'simulation_rules_versions', 'simulation_resultado', 'simulated_at',
    'applicant_proceeded_despite', 'contingency_reason', 'external_reference',
])]
class ViabilityRequest extends Model
{
    use HasAuditoria;

    /** @use HasFactory<ViabilityRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ViabilityRequestStatus::class,
            'origin' => ViabilityRequestOrigin::class,
            'property_polygon_geojson' => 'array',
            'simulation_snapshot' => 'array',
            'simulation_rules_versions' => 'array',
            'used_area_m2' => 'decimal:2',
            'is_virtual_office' => 'boolean',
            'wants_virtual_office_hq' => 'boolean',
            'is_public_area' => 'boolean',
            'has_independent_access' => 'boolean',
            'applicant_proceeded_despite' => 'boolean',
            'simulated_at' => 'datetime',
            'protocoled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'bap_due_at' => 'datetime',
            'bap_linked_at' => 'datetime',
            // Colunas da análise técnica (EP10) — FORA do fillable (escritas por
            // serviços de distribuição/SLA, nunca pelo cidadão).
            'analysis_category' => AnalysisCategory::class,
            'analysis_stage' => AnalysisStage::class,
            'analysis_status' => AnalysisStatus::class,
            'in_fine_mesh' => 'boolean',
            'assigned_at' => 'datetime',
            'analysis_stage_started_at' => 'datetime',
            'analysis_due_at' => 'datetime',
        ];
    }

    /**
     * Invalida a simulação orientativa (HU-063 RN-005): mudança de área, CNAE
     * ou imóvel zera o snapshot — o consumidor (08-06/08-07) chama este método
     * para forçar nova simulação. Escrita única aqui evita conflito entre os
     * planos paralelos da fase.
     */
    public function markSimulationStale(): void
    {
        $this->forceFill([
            'simulation_snapshot' => null,
            'simulation_rules_versions' => null,
            'simulation_resultado' => null,
            'simulated_at' => null,
        ])->save();
    }

    /**
     * Tipo de serviço (HU-061 RN-005).
     *
     * @return BelongsTo<ViabilityServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ViabilityServiceType::class, 'service_type_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Beneficiário da solicitação.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * Ator real que criou a solicitação ("em nome de" — HU-150).
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * Atendimento presencial que originou a solicitação, quando aberta no balcão
     * "em nome de" o cidadão (HU-150 RN-005 — dimensão balcão dos relatórios).
     *
     * @return BelongsTo<AssistedAttendance, $this>
     */
    public function assistedAttendance(): BelongsTo
    {
        return $this->belongsTo(AssistedAttendance::class);
    }

    /**
     * CNAEs da solicitação (principal e complementares), espelhando company_cnae.
     *
     * @return BelongsToMany<Cnae, $this>
     */
    public function cnaes(): BelongsToMany
    {
        return $this->belongsToMany(Cnae::class, 'viability_request_cnaes')->withPivot('is_primary')->withTimestamps();
    }

    /**
     * CNAE principal (exatamente um, garantido na aplicação).
     *
     * @return BelongsToMany<Cnae, $this>
     */
    public function primaryCnae(): BelongsToMany
    {
        return $this->cnaes()->wherePivot('is_primary', true);
    }

    /**
     * Anexos (HU-066).
     *
     * @return HasMany<ViabilityRequestDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ViabilityRequestDocument::class);
    }

    /**
     * Transições de estado — fonte da timeline (HU-069), mais recente primeiro.
     *
     * @return HasMany<ViabilityRequestTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(ViabilityRequestTransition::class)->latest();
    }

    /**
     * Timeline interna do eixo operacional da análise (AnalysisStatus).
     *
     * @return HasMany<AnalysisStatusTransition, $this>
     */
    public function analysisStatusTransitions(): HasMany
    {
        return $this->hasMany(AnalysisStatusTransition::class, 'viability_request_id');
    }

    /**
     * Decisão do fluxo expresso (EP09) — 1:1 imutável, existe só após
     * deferir/indeferir. bap_due_at/bap_linked_at (HU-134) ficam fora do
     * fillable (como protocol_number/status): são gravados via forceFill pela
     * rotina do BAP quando o Regin entrar (Fase 13).
     *
     * @return HasOne<ViabilityDecision, $this>
     */
    public function decision(): HasOne
    {
        return $this->hasOne(ViabilityDecision::class);
    }

    /**
     * Setor (caixa) que recebeu o processo na distribuição (HU-080). sector_id
     * fica fora do fillable — gravado pelo serviço de distribuição.
     *
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * Analista que assumiu o processo (HU-081). assigned_user_id fica fora do
     * fillable — gravado ao assumir.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Fichas de análise versionadas (HU-135/140) — uma linha por revisão.
     *
     * @return HasMany<AnalysisRecord, $this>
     */
    public function analysisRecords(): HasMany
    {
        return $this->hasMany(AnalysisRecord::class);
    }

    /**
     * Ficha vigente = a de MAIOR revisão (RN-003 — versionamento). A finalizada
     * é imutável; recalcular gera nova revisão, que passa a ser a current.
     *
     * @return HasOne<AnalysisRecord, $this>
     */
    public function currentAnalysisRecord(): HasOne
    {
        return $this->hasOne(AnalysisRecord::class)->latestOfMany('revision');
    }

    /**
     * Pendências abertas/respondidas/expiradas do processo (HU-083/084).
     *
     * @return HasMany<AnalysisPendency, $this>
     */
    public function pendencies(): HasMany
    {
        return $this->hasMany(AnalysisPendency::class);
    }

    /**
     * Encaminhamentos à malha fina (HU-136) — ortogonais ao status, repetíveis.
     *
     * @return HasMany<FineMeshReferral, $this>
     */
    public function fineMeshReferrals(): HasMany
    {
        return $this->hasMany(FineMeshReferral::class);
    }
}
