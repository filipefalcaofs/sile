<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\AccessLog;
use App\Services\Auditoria\AuditTrailQueryService;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte do histórico de acessos para o contrato único de exportação
 * (HU-131/RN-009) — consolida o streaming CSV que vivia em
 * AuditoriaController::exportarAcessos. Fonte SECUNDÁRIA da trilha (HU-100):
 * access_logs globais (login/logout/falha/bloqueio) filtrados por
 * período/usuário/evento via {@see AuditTrailQueryService::acessos()} a partir
 * do bag — conjunto exportado EXATAMENTE o filtrado (RN-005), no síncrono e no
 * assíncrono (round-trip do bag pelo Job).
 *
 * Classe SEPARADA do {@see AtividadesReportSource} para o caminho assíncrono
 * reconstruir a fonte certa via `app($sourceClass)` (INVARIANTE do
 * {@see ReportSource}). Colunas e mapeamento preservados fielmente do controller;
 * personalData=true (HU-101 — e-mail/usuário expostos). Guarda de volume técnica
 * (sile.auditoria.export.max_linhas) aplicada no builder.
 */
final class AcessosReportSource implements ReportSource
{
    /**
     * Chaves de filtro aplicáveis a access_logs (período/usuário/evento) — o
     * whitelisting do source sobre o bag completo.
     *
     * @var list<string>
     */
    private const CHAVES = ['data_de', 'data_ate', 'usuario_id', 'usuario', 'event'];

    public function __construct(private readonly AuditTrailQueryService $trilha) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        $acessoFiltros = $filtros->only(self::CHAVES);
        $maxLinhas = (int) config('sile.auditoria.export.max_linhas', 50000);

        return new ReportDefinition(
            titulo: 'Histórico de acessos',
            colunas: [
                ['key' => 'created_at', 'label' => 'Data/hora'],
                ['key' => 'event', 'label' => 'Evento'],
                ['key' => 'usuario', 'label' => 'Usuário'],
                ['key' => 'email', 'label' => 'E-mail'],
                ['key' => 'ip_address', 'label' => 'IP'],
                ['key' => 'channel', 'label' => 'Canal'],
            ],
            builder: function () use ($acessoFiltros, $maxLinhas): Builder {
                // Guarda de volume técnica via subquery (ver AtividadesReportSource):
                // o teto sobrevive ao count()/chunk()/cursor().
                return AccessLog::query()
                    ->with('user:id,name')
                    ->whereIn('id', $this->trilha->acessos($acessoFiltros)->select('id')->limit($maxLinhas))
                    ->orderByDesc('id');
            },
            mapRow: fn (AccessLog $acesso): array => [
                $acesso->created_at?->toIso8601String(),
                $acesso->event,
                $acesso->user?->name,
                $acesso->email,
                $acesso->ip_address,
                $acesso->channel,
            ],
            filtrosAplicados: $filtros->aplicados(),
            logName: 'auditoria',
            event: 'exporta-trilha',
            personalData: true,
            arquivoBase: 'auditoria-acessos',
        );
    }
}
