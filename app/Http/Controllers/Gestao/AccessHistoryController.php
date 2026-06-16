<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\AcessosUsuarioReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AccessHistoryController extends Controller
{
    /**
     * Consulta administrativa do histórico de acessos de qualquer conta
     * (HU-010 CA-01). Consulta a dados de terceiro é relevante: auditada
     * explicitamente (CA-02). Mesmo filtro combinado do portal — inclui
     * bloqueios/falhas pré-login gravados sem user_id.
     */
    public function __invoke(Request $request, User $user): Response|HttpResponse
    {
        // HU-131/RN-009: com ?formato=, exporta o histórico do usuário-alvo pelo
        // contrato único — sem rota nova (o `user` viaja no bag). O ReportExporter
        // audita a exportação (RN-008, personal_data), então o branch retorna antes
        // da auditoria da consulta interativa abaixo.
        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(AcessosUsuarioReportSource::class),
                ReportFilters::fromArray(['user' => $user->id]),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

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
