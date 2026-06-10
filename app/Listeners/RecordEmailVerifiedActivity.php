<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Verified;

class RecordEmailVerifiedActivity
{
    public function handle(Verified $event): void
    {
        activity('seguranca')
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->event('email-confirmado')
            ->log('E-mail confirmado pelo usuário');
    }
}
