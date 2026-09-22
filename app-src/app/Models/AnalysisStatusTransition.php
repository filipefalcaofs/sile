<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transição do eixo operacional da análise (timeline interna). Espelha
 * ViabilityRequestTransition, mas sem public_label — não vai à timeline do
 * cidadão. Escrita pela AnalysisStatusStateMachine.
 */
#[Fillable(['from_status', 'to_status', 'reason', 'actor_user_id'])]
class AnalysisStatusTransition extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => AnalysisStatus::class,
            'to_status' => AnalysisStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class, 'viability_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
