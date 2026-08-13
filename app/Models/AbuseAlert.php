<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use Database\Factories\AbuseAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Alerta de abuso/fraude (HU-149) — ledger dos achados dos detectores
 * determinísticos. NUNCA é punição: o alerta é insumo humano (gestor confirma/
 * descarta) e, acima do limiar, gera encaminhamento à malha fina (ortogonal ao
 * status). A própria criação/baixa é auditável (HasAuditoria/RN-002).
 */
#[Fillable([
    'rule_key',
    'severity',
    'status',
    'fingerprint',
    'evidence',
    'viability_request_id',
    'subject_type',
    'subject_id',
    'window_start',
    'window_end',
    'detected_at',
    'resolved_by_user_id',
    'resolved_at',
    'justification',
    'fine_mesh_referral_id',
])]
class AbuseAlert extends Model
{
    use HasAuditoria;

    /** @use HasFactory<AbuseAlertFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => AbuseSeverity::class,
            'status' => AbuseAlertStatus::class,
            'evidence' => 'array',
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Processo de viabilidade relacionado ao alerta (opcional).
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * Gestor que deu baixa no alerta (confirmou/descartou).
     *
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /**
     * Encaminhamento à malha fina gerado por este alerta (acima do limiar).
     *
     * @return BelongsTo<FineMeshReferral, $this>
     */
    public function fineMeshReferral(): BelongsTo
    {
        return $this->belongsTo(FineMeshReferral::class);
    }

    /**
     * Alvo opcional do alerta (empresa, usuário, contador…).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
