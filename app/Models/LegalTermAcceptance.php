<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\LegalTermAcceptanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['accepted_by_type', 'accepted_by_id', 'legal_term_id', 'ip_address', 'user_agent', 'accepted_at'])]
class LegalTermAcceptance extends Model
{
    /** @use HasFactory<LegalTermAcceptanceFactory> */
    use HasAuditoria, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function acceptedBy(): MorphTo
    {
        return $this->morphTo('acceptedBy', 'accepted_by_type', 'accepted_by_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(LegalTerm::class, 'legal_term_id');
    }
}
