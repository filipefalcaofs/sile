<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessHistoryController extends Controller
{
    /**
     * Consulta administrativa do histórico de acessos de qualquer conta
     * (HU-010 CA-01). Consulta a dados de terceiro é relevante: auditada
     * explicitamente (CA-02). Mesmo filtro combinado do portal — inclui
     * bloqueios/falhas pré-login gravados sem user_id.
     */
    public function __invoke(Request $request, User $user): Response
    {
        // personalData: consultar o histórico de acessos de OUTRA conta é leitura
        // de dado pessoal de terceiro — medido pelo painel LGPD (HU-102).
        // Marcação ADITIVA, sem mudar a auditoria existente.
        app(AuditService::class)->log(
            'acessos',
            'consulta-acessos',
            'Consulta administrativa do histórico de acessos de outro usuário',
            ['target_user_id' => $user->id],
            personalData: true,
        );

        return Inertia::render('gestao/acessos', [
            'targetUser' => $user->only('id', 'name', 'email'),
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
