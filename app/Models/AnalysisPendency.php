<?php

namespace App\Models;

use App\Enums\AnalysisPendencyStatus;
use Database\Factories\AnalysisPendencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pendência da análise (HU-083/084 — ciclo interno): o analista solicita
 * informação/documento ao requerente. status (aberta/respondida/expirada),
 * due_at é o prazo (parâmetro analise.pendencia.prazo_resposta_dias) e response
 * guarda a resposta. Convite via Simplifica/Regin + multicanal → EP11.
 */
#[Fillable([
    'viability_request_id',
    'requested_by_user_id',
    'description',
    'status',
    'due_at',
    'responded_at',
    'response',
    'parecer',
    'cancelled_at',
    'cancelled_by_user_id',
])]
class AnalysisPendency extends Model
{
    /** @use HasFactory<AnalysisPendencyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnalysisPendencyStatus::class,
            'due_at' => 'datetime',
            'responded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Processo da pendência.
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * Analista que abriu a pendência.
     *
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * Analista que cancelou o convite (com parecer).
     *
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
