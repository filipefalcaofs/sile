<?php

namespace App\Listeners;

use App\Models\AccessLog;
use App\Models\Cidadao;
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
        $modelo = request()->routeIs('gestao.*') ? User::class : Cidadao::class;

        if ($user === null && $email !== '') {
            $user = $modelo::query()->where('email', $email)->first();
        }

        if ($user === null && $cpf !== '') {
            $user = $modelo::query()->where('cpf', $cpf)->first();
        }

        AccessLog::create([
            'actor_type' => $user?->getMorphClass(),
            'actor_id' => $user?->getAuthIdentifier(),
            // Tentativa sem conta correspondente permanece rastreável pelo
            // identificador digitado (e-mail ou CPF em dígitos).
            'email' => $user->email ?? ($email !== '' ? $email : $cpf),
            'event' => 'falha',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'channel' => request()->routeIs('gestao.*') ? 'gestao' : 'portal',
            'origin' => $event->credentials['origin'] ?? null,
        ]);
    }
}
