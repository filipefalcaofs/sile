<?php

namespace App\Models;

use Database\Factories\FineMeshReferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encaminhamento à malha fina (HU-136) — ORTOGONAL ao status (liga a flag
 * in_fine_mesh em viability_requests): é repetível e pode ocorrer em lote.
 * reason é obrigatório; created_at marca o encaminhamento e resolved_at a baixa
 * (null = em malha fina). Pode atingir até processo deferido.
 */
#[Fillable([
    'viability_request_id',
    'referred_by_user_id',
    'reason',
    'resolved_at',
])]
class FineMeshReferral extends Model
{
    /** @use HasFactory<FineMeshReferralFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Processo encaminhado à malha fina.
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * Analista que encaminhou.
     *
     * @return BelongsTo<User, $this>
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }
}
