<?php

namespace App\Models;

use Database\Factories\GovBrAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo do cidadão local com a conta GOV.BR (HU-151).
 *
 * Sem trait de auditoria automática: cada autenticação atualiza
 * last_authenticated_at e geraria ruído; a trilha fica nos registros
 * explícitos do fluxo de login (access_logs + AuditService).
 */
#[Fillable(['cidadao_id', 'reliability_level', 'reliability_levels', 'linked_at', 'last_authenticated_at'])]
class GovBrAccount extends Model
{
    /** @use HasFactory<GovBrAccountFactory> */
    use HasFactory;

    protected $table = 'gov_br_accounts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reliability_levels' => 'array',
            'linked_at' => 'datetime',
            'last_authenticated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Cidadao, $this>
     */
    public function cidadao(): BelongsTo
    {
        return $this->belongsTo(Cidadao::class, 'cidadao_id');
    }
}
