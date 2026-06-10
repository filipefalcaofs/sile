<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'legal_term_id', 'ip_address', 'user_agent', 'accepted_at'])]
class LegalTermAcceptance extends Model
{
    use HasAuditoria;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(LegalTerm::class, 'legal_term_id');
    }
}
