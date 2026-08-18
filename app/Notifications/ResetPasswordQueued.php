<?php

namespace App\Notifications;

use App\Models\EmailLog;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetPasswordQueued extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public ?int $emailLogId = null;

    /**
     * URL capturada no DISPARO (contexto da request) e serializada com o
     * job — o worker de fila pode rodar com APP_URL defasado em memória
     * (o queue:listen não relê o .env) e produziria link quebrado.
     */
    public ?string $resetUrl = null;

    public function freezeUrlFor(object $notifiable): void
    {
        $this->resetUrl = parent::resetUrl($notifiable);
    }

    protected function resetUrl($notifiable): string
    {
        return $this->resetUrl ?? parent::resetUrl($notifiable);
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->emailLogId) {
            EmailLog::find($this->emailLogId)?->markAsFailed($exception->getMessage());
        }
    }
}
