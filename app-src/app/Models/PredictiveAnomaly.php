<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use Database\Factories\PredictiveAnomalyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anomalia preditiva de processo expresso (Módulo 3). Ledger dos achados da
 * varredura de auditoria preditiva (score determinístico sobre deferimentos
 * automáticos). NUNCA é punição: é insumo humano (confirmar/descartar) e, acima
 * do limiar de severidade, gera encaminhamento à malha fina (ortogonal ao
 * status). A criação/baixa é auditável (HasAuditoria/RN-002).
 */
#[Fillable([
    'viability_request_id',
    'score',
    'severity',
    'status',
    'fingerprint',
    'factors',
    'window_start',
    'window_end',
    'detected_at',
    'resolved_by_user_id',
    'resolved_at',
    'justification',
    'fine_mesh_referral_id',
])]
class PredictiveAnomaly extends Model
{
    use HasAuditoria;

    /** @use HasFactory<PredictiveAnomalyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'severity' => AbuseSeverity::class,
            'status' => AbuseAlertStatus::class,
            'factors' => 'array',
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Processo de viabilidade auditado.
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * Gestor que deu baixa na anomalia (confirmou/descartou).
     *
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /**
     * Encaminhamento à malha fina gerado por esta anomalia (severidade alta).
     *
     * @return BelongsTo<FineMeshReferral, $this>
     */
    public function fineMeshReferral(): BelongsTo
    {
        return $this->belongsTo(FineMeshReferral::class);
    }
}
