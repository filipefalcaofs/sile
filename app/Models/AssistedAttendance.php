<?php

namespace App\Models;

use Database\Factories\AssistedAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atendimento presencial assistido (HU-150): vínculo leve entre o atendente
 * (attendant) e o cidadão atendido (citizen), com janela de expiração curta.
 * NÃO é procuração jurídica — é atendimento de balcão; reusa apenas o
 * mecanismo de usuário efetivo/policy/auditoria "em nome de" da Fase 1.
 */
#[Fillable(['attendant_user_id', 'citizen_user_id', 'started_at', 'expires_at', 'ended_at'])]
class AssistedAttendance extends Model
{
    /** @use HasFactory<AssistedAttendanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Atendente que conduz o atendimento (ator real das ações).
     *
     * @return BelongsTo<User, $this>
     */
    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendant_user_id');
    }

    /**
     * Cidadão atendido (beneficiário "em nome de").
     *
     * @return BelongsTo<User, $this>
     */
    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'citizen_user_id');
    }

    /**
     * Atendimentos vigentes: não encerrados e ainda dentro da janela.
     *
     * @param  Builder<AssistedAttendance>  $query
     * @return Builder<AssistedAttendance>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at > now();
    }
}
