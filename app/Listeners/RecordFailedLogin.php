<?php

namespace App\Listeners;

use App\Models\AccessLog;
use Illuminate\Auth\Events\Failed;

class RecordFailedLogin
{
    public function handle(Failed $event): void
    {
        AccessLog::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => (string) ($event->credentials['email'] ?? ''),
            'event' => 'falha',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'channel' => request()->routeIs('gestao.*') ? 'gestao' : 'portal',
        ]);
    }
}
