<?php

namespace App\Notifications;

use App\Models\EmailLog;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class VerifyEmailQueued extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public ?int $emailLogId = null;

    /**
     * URL assinada capturada no DISPARO (contexto da request, com host e
     * porta corretos) e serializada com o job. Sem isso, o link seria
     * gerado no worker de fila — que pode rodar com APP_URL defasado em
     * memória (o queue:listen não relê o .env) e produzir link quebrado.
     */
    public ?string $verificationUrl = null;

    public function freezeUrlFor(object $notifiable): void
    {
        $this->verificationUrl = parent::verificationUrl($notifiable);
    }

    protected function verificationUrl($notifiable): string
    {
        return $this->verificationUrl ?? parent::verificationUrl($notifiable);
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->emailLogId) {
            EmailLog::find($this->emailLogId)?->markAsFailed($exception->getMessage());
        }
    }
}
