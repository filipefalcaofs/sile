<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Http\Controllers\Gestao\AccessHistoryController;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte do histórico de acessos DE UM USUÁRIO (HU-010) para o contrato único de
 * exportação (HU-131/RN-009): o {@see AccessHistoryController}
 * ganha um branch ?formato= delegando ao {@see ReportExporter}
 * sem rota nova (a rota acessos.show já existe, gated por
 * consultar-acessos-de-qualquer-conta). O usuário-alvo viaja no BAG (`user`),
 * NÃO no construtor — a fonte é reconstrutível só pelo bag (INVARIANTE do
 * {@see ReportSource}); o e-mail do alvo é derivado do id (não entra no bag, para
 * não vazar PII na auditoria).
 *
 * RN-005: reproduz EXATAMENTE a query do controller (acessos com user_id do alvo
 * OU e-mail do alvo — inclui falhas/bloqueios pré-login sem user_id), na mesma
 * ordem (mais recentes primeiro). Colunas espelham a listagem da tela
 * (evento/ip/canal/data-hora). personalData=true (IP é dado pessoal — LGPD HU-102).
 */
final class AcessosUsuarioReportSource implements ReportSource
{
    public function definition(ReportFilters $filtros): ReportDefinition
    {
        $userId = (int) $filtros->get('user');
        $alvo = User::find($userId);

        return new ReportDefinition(
            titulo: 'Histórico de acessos do usuário',
            colunas: [
                ['key' => 'event', 'label' => 'Evento'],
                ['key' => 'ip_address', 'label' => 'IP'],
                ['key' => 'channel', 'label' => 'Canal'],
                ['key' => 'created_at', 'label' => 'Data/hora'],
            ],
            // RN-005: a MESMA query do AccessHistoryController (user_id OU e-mail do
            // alvo), reconstruída a partir do id que veio no bag.
            builder: fn (): Builder => AccessLog::query()
                ->where(function ($query) use ($userId, $alvo): void {
                    $query->where('user_id', $userId);

                    if ($alvo !== null) {
                        $query->orWhere('email', $alvo->email);
                    }
                })
                ->latest('created_at'),
            mapRow: fn (AccessLog $log): array => [
                $log->event,
                $log->ip_address,
                $log->channel,
                $log->created_at?->toIso8601String(),
            ],
            filtrosAplicados: $filtros->aplicados(),
            logName: 'acessos',
            event: 'exporta-acessos-usuario',
            personalData: true,
            arquivoBase: 'acessos-usuario',
        );
    }
}
