<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use Database\Factories\CommunicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha do ledger imutável de comunicações de PROCESSO (HU-096). Espelha o
 * EmailLog (que segue sendo a fonte das comunicações de CONTA — verificação de
 * e-mail/senha): cada marcador muda status + carimbo, NUNCA inventando
 * "enviado". É a fonte de verdade do histórico por processo E o controle de
 * idempotência das rotinas (Wave 5).
 */
class Communication extends Model
{
    /** @use HasFactory<CommunicationFactory> */
    use HasFactory;

    protected $fillable = [
        'viability_request_id',
        'recipient_user_id',
        'channel',
        'type',
        'status',
        'title',
        'summary',
        'error_message',
        'meta',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'type' => CommunicationType::class,
            'status' => CommunicationStatus::class,
            'meta' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * Confirma a entrega (NotificationSent). GUARDA anti-fachada (defesa em
     * profundidade): um NotificationSent tardio NÃO pode transformar um status
     * terminal honesto (bloqueado/desativado) em "enviado" fictício — nesse caso
     * é no-op.
     */
    public function markAsSent(): void
    {
        if (in_array($this->status, [CommunicationStatus::Bloqueado, CommunicationStatus::Desativado], true)) {
            return;
        }

        $this->update([
            'status' => CommunicationStatus::Enviado,
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => CommunicationStatus::Falhou,
            'error_message' => $error,
            'failed_at' => now(),
        ]);
    }

    /**
     * Canal habilitado, mas indisponível no disparo (ex.: gateway de WhatsApp
     * fora do ar). Não houve entrega — registra o bloqueio auditado.
     */
    public function markAsBlocked(?string $motivo = null): void
    {
        $this->update([
            'status' => CommunicationStatus::Bloqueado,
            'error_message' => $motivo,
        ]);
    }

    /**
     * Canal desligado por toggle (HU-014) — degradação controlada, sem fingir
     * envio.
     */
    public function markAsDisabled(): void
    {
        $this->update(['status' => CommunicationStatus::Desativado]);
    }
}
