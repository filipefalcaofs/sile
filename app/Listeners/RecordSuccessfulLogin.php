<?php

namespace App\Listeners;

use App\Models\AccessLog;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function handle(Login $event): void
    {
        AccessLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email,
            'event' => 'login',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'channel' => request()->routeIs('gestao.*') ? 'gestao' : 'portal',
            // Origem da credencial (ad/local) definida pelo fluxo de login;
            // null no portal e em logins anteriores à feature.
            'origin' => request()->attributes->get('auth_origin'),
        ]);
    }
}
