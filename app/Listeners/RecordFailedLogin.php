<?php

namespace App\Listeners;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

class RecordFailedLogin
{
    public function handle(Failed $event): void
    {
        // Com Fortify::authenticateUsing, o evento Failed chega sem o usuário.
        // O portal identifica por CPF e a gestão por e-mail — a trilha
        // resolve o titular pelo identificador presente nas credenciais.
        $email = (string) ($event->credentials['email'] ?? '');
        $cpf = preg_replace('/\D/', '', (string) ($event->credentials['cpf'] ?? ''));

        $user = $event->user;

        if ($user === null && $email !== '') {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user === null && $cpf !== '') {
            $user = User::query()->where('cpf', $cpf)->first();
        }

        AccessLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            // Tentativa sem conta correspondente permanece rastreável pelo
            // identificador digitado (e-mail ou CPF em dígitos).
            'email' => $user->email ?? ($email !== '' ? $email : $cpf),
            'event' => 'falha',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'channel' => request()->routeIs('gestao.*') ? 'gestao' : 'portal',
        ]);
    }
}
