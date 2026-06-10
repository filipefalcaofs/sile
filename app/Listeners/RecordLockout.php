<?php

namespace App\Listeners;

use App\Models\AccessLog;
use Illuminate\Auth\Events\Lockout;

class RecordLockout
{
    public function handle(Lockout $event): void
    {
        AccessLog::create([
            'user_id' => null,
            'email' => (string) $event->request->input('email', ''),
            'event' => 'bloqueio',
            'ip_address' => $event->request->ip(),
            'user_agent' => substr((string) $event->request->userAgent(), 0, 500),
            'channel' => $event->request->routeIs('gestao.*') ? 'gestao' : 'portal',
        ]);
    }
}
