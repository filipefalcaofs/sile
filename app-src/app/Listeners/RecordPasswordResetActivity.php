<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;

class RecordPasswordResetActivity
{
    public function handle(PasswordReset $event): void
    {
        activity('seguranca')
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->event('senha-redefinida')
            ->log('Senha redefinida via link de recuperação');
    }
}
