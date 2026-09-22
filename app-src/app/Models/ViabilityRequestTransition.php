<?php

namespace App\Models;

use App\Enums\ViabilityRequestStatus;
use Database\Factories\ViabilityRequestTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transição de estado registrada pela ViabilityRequestStateMachine — fonte da
 * timeline (HU-069). public_label é o rótulo amigável ao cidadão (RN-004).
 */
#[Fillable(['from_status', 'to_status', 'reason', 'public_label', 'actor_user_id'])]
class ViabilityRequestTransition extends Model
{
    /** @use HasFactory<ViabilityRequestTransitionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ViabilityRequestStatus::class,
            'to_status' => ViabilityRequestStatus::class,
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
