<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\CompanyLinkRole;
use Database\Factories\CompanyUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo usuário-empresa com ciclo de vida (HU-023/HU-028). Encerramento
 * via ended_at/ended_reason (nunca delete físico — histórico preservado).
 * Auditoria automática via HasAuditoria.
 */
#[Fillable(['company_id', 'user_id', 'role', 'started_at', 'ended_at', 'ended_reason'])]
class CompanyUser extends Model
{
    use HasAuditoria;

    /** @use HasFactory<CompanyUserFactory> */
    use HasFactory;

    protected $table = 'company_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CompanyLinkRole::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
