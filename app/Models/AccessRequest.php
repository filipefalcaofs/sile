<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use Database\Factories\AccessRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Pedido de perfil feito pelo servidor no primeiro acesso AD. O administrador
 * aprova (com o perfil pedido ou outro), ou recusa com motivo.
 */
class AccessRequest extends Model
{
    /** @use HasFactory<AccessRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_id',
        'justificativa',
        'status',
        'decided_by_user_id',
        'decided_at',
        'decision_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccessRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', AccessRequestStatus::Pendente->value);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
