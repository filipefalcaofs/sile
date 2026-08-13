<?php

namespace App\Models;

use App\Support\Settings;
use Database\Factories\AccessLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de acessos (HU-010): login, logout, falha e bloqueio.
 *
 * Não usa HasAuditoria — access_logs já É dado de auditoria.
 */
#[Fillable(['user_id', 'email', 'event', 'ip_address', 'user_agent', 'channel'])]
class AccessLog extends Model
{
    /** @use HasFactory<AccessLogFactory> */
    use HasFactory, MassPrunable;

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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
     * Janela de retenção parametrizada (HU-014). MassPrunable apaga em massa
     * sem disparar model events — adequado para dado de acesso de alto volume.
     *
     * @return Builder<AccessLog>
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'created_at',
            '<',
            now()->subDays((int) Settings::get('retencao.access_logs.dias', 365)),
        );
    }
}
