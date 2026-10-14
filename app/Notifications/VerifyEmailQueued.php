<?php

namespace App\Notifications;

use App\Models\EmailLog;
use App\Notifications\Messages\EmailViabiliza;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

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

    protected function buildMailMessage($url): MailMessage
    {
        $minutos = (int) config('auth.verification.expire', 60);

        return (new EmailViabiliza)
            ->subject(Lang::get('Verify Email Address'))
            ->selo('Sua conta')
            ->titulo('Confirme seu endereço de e-mail')
            ->greeting('Olá!')
            ->line('Para concluir seu cadastro no Viabiliza, confirme que este endereço de e-mail é seu.')
            ->action('Confirmar e-mail', $url)
            ->line("O link é válido por {$minutos} minutos. Se você não criou uma conta no Viabiliza, ignore esta mensagem.");
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->emailLogId) {
            EmailLog::find($this->emailLogId)?->markAsFailed($exception->getMessage());
        }
    }
}
