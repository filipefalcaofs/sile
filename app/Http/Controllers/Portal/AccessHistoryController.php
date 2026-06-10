<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessHistoryController extends Controller
{
    /**
     * Histórico de acessos do próprio usuário (HU-010 CA-01) — o filtro por
     * user_id OU e-mail inclui falhas e bloqueios pré-login gravados sem user_id.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('portal/acessos', [
            'logs' => AccessLog::query()
                ->where(fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->orWhere('email', $user->email))
                ->latest('created_at')
                ->paginate((int) Settings::get('ui.access_history.per_page', 15))
                ->through(fn (AccessLog $log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'ip_address' => $log->ip_address,
                    'channel' => $log->channel,
                    'created_at' => $log->created_at->toIso8601String(),
                ]),
        ]);
    }
}
