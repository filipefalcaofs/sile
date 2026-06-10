<?php

namespace App\Listeners;

use App\Models\AccessLog;
use Illuminate\Auth\Events\Logout;

class RecordLogout
{
    public function handle(Logout $event): void
    {
        // Logout de sessão expirada pode chegar sem usuário.
        AccessLog::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->user?->email ?? '',
            'event' => 'logout',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'channel' => request()->routeIs('gestao.*') ? 'gestao' : 'portal',
        ]);
    }
}
