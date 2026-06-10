<?php

namespace App\Listeners;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

class RecordFailedLogin
{
    public function handle(Failed $event): void
    {
        // Com Fortify::authenticateUsing, o evento Failed chega sem o usuário;
        // resolve pelo e-mail para manter a trilha com o titular conhecido.
        $userId = $event->user?->getAuthIdentifier()
            ?? User::query()->where('email', (string) ($event->credentials['email'] ?? ''))->value('id');

        AccessLog::create([
            'user_id' => $userId,
            'email' => (string) ($event->credentials['email'] ?? ''),
            'event' => 'falha',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'channel' => request()->routeIs('gestao.*') ? 'gestao' : 'portal',
        ]);
    }
}
