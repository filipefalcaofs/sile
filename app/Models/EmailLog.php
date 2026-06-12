<?php

namespace App\Models;

use Database\Factories\EmailLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    /** @use HasFactory<EmailLogFactory> */
    use HasFactory;

    protected $fillable = [
        'recipient_email',
        'recipient_name',
        'notification_class',
        'status',
        'error_message',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function markAsSent(): void
    {
        $this->update(['status' => 'enviado', 'sent_at' => now()]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'falhou',
            'error_message' => $error,
            'failed_at' => now(),
        ]);
    }

    public static function notificationLabel(string $class): string
    {
        return match (true) {
            str_contains($class, 'VerifyEmail') => 'Verificação de e-mail',
            str_contains($class, 'ResetPassword') => 'Redefinição de senha',
            default => class_basename($class),
        };
    }
}
