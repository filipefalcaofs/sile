<?php

namespace App\Notifications;

/**
 * Recuperação de senha do console (guard gestao): o link aponta para
 * /gestao/reset-password, não para o fluxo do portal (Fortify). Reusa o
 * EmailLog e o congelamento de URL no disparo de ResetPasswordQueued.
 */
class GestaoResetPasswordQueued extends ResetPasswordQueued
{
    public function freezeUrlFor(object $notifiable): void
    {
        $this->resetUrl = url(route('gestao.reset-password', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
