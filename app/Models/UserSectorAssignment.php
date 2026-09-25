<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\UserSectorAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de lotação usuário×setor com vigência (started_at/ended_at).
 * Origem discovery = sincronização no login AD; não apaga — encerra a vigência.
 */
#[Fillable([
    'user_id',
    'sector_id',
    'previous_sector_id',
    'source',
    'started_at',
    'ended_at',
    'changed_by_user_id',
])]
class UserSectorAssignment extends Model
{
    /** @use HasFactory<UserSectorAssignmentFactory> */
    use HasAuditoria, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function previousSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'previous_sector_id');
    }
}
