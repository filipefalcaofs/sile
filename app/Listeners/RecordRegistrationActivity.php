<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;

class RecordRegistrationActivity
{
    public function handle(Registered $event): void
    {
        // Causer explícito: no evento Registered o usuário ainda não está
        // autenticado (auth()->user() é null) — não usar AuditService aqui.
        activity('cadastro')
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->event('cadastro')
            ->log('Usuário cadastrado');
    }
}
