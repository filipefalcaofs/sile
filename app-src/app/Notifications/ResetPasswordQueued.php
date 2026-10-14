<?php

namespace App\Notifications;

use App\Models\EmailLog;
use App\Notifications\Messages\EmailViabiliza;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

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

    protected function buildMailMessage($url): MailMessage
    {
        $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new EmailViabiliza)
            ->subject(Lang::get('Reset Password Notification'))
            ->selo('Sua conta')
            ->titulo('Redefina sua senha')
            ->greeting('Olá!')
            ->line('Recebemos um pedido para redefinir a senha da sua conta no Viabiliza.')
            ->action('Redefinir senha', $url)
            ->destaque("O link é válido por {$minutos} minutos. Se você não pediu a redefinição, ignore esta mensagem: sua senha continua a mesma.", 'alerta');
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->emailLogId) {
            EmailLog::find($this->emailLogId)?->markAsFailed($exception->getMessage());
        }
    }
}
