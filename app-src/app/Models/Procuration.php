<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\ProcurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Procuração user→user (HU-008/009): outorgante autoriza procurador com
 * vigência opcional; revogação interrompe a representação de imediato.
 */
#[Fillable(['grantor_user_id', 'attorney_user_id', 'starts_at', 'expires_at', 'revoked_at', 'revoked_by_user_id'])]
class Procuration extends Model
{
    /** @use HasFactory<ProcurationFactory> */
    use HasAuditoria, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grantor_user_id');
    }

    public function attorney(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attorney_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * Procurações vigentes: não revogadas, já iniciadas e não expiradas.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->starts_at <= now()
            && ($this->expires_at === null || $this->expires_at > now());
    }
}
