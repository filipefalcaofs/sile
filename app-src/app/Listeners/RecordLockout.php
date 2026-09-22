<?php

namespace App\Listeners;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;

class RecordLockout
{
    public function handle(Lockout $event): void
    {
        // O portal identifica por CPF e a gestão por e-mail; resolve o
        // titular para manter a trilha consultável pelo histórico (HU-010).
        $email = (string) $event->request->input('email', '');
        $cpf = preg_replace('/\D/', '', (string) $event->request->input('cpf', ''));

        $user = null;

        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();
        } elseif ($cpf !== '') {
            $user = User::query()->where('cpf', $cpf)->first();
        }

        AccessLog::create([
            'user_id' => $user?->id,
            'email' => $user->email ?? ($email !== '' ? $email : $cpf),
            'event' => 'bloqueio',
            'ip_address' => $event->request->ip(),
            'user_agent' => substr((string) $event->request->userAgent(), 0, 500),
            'channel' => $event->request->routeIs('gestao.*') ? 'gestao' : 'portal',
        ]);
    }
}
