<?php

namespace App\Listeners;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Str;

class RecordLockout
{
    public function handle(Lockout $event): void
    {
        // Portal: CPF. Gestão: identifier (usuário AD ou e-mail) — mantém
        // compatibilidade com o campo email legado. Resolve o titular para
        // a trilha consultável pelo histórico (HU-010).
        $identifier = Str::lower(trim((string) (
            $event->request->input('identifier')
            ?? $event->request->input('email')
            ?? ''
        )));
        $cpf = preg_replace('/\D/', '', (string) $event->request->input('cpf', ''));

        $user = null;

        if ($identifier !== '') {
            $user = User::query()
                ->where(function ($query) use ($identifier) {
                    $query->where('email', $identifier)
                        ->orWhere('ad_username', $identifier);
                })
                ->first();
        } elseif ($cpf !== '') {
            $user = User::query()->where('cpf', $cpf)->first();
        }

        AccessLog::create([
            'user_id' => $user?->id,
            'email' => $user?->email ?? ($identifier !== '' ? $identifier : $cpf),
            'event' => 'bloqueio',
            'ip_address' => $event->request->ip(),
            'user_agent' => substr((string) $event->request->userAgent(), 0, 500),
            'channel' => $event->request->routeIs('gestao.*') ? 'gestao' : 'portal',
        ]);
    }
}
