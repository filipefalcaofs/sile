<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Services\Auditoria\AuditTrailQueryService;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte da trilha de atividades/alterações para o contrato único de exportação
 * (HU-131/RN-009) — consolida o streaming CSV que vivia em
 * AuditoriaController::exportarAtividades. Reusa o AuditTrailQueryService
 * (HU-098/100) a partir do bag de filtros (as 9 chaves da consulta da trilha +
 * a `fonte`, que escolhe a trilha completa ou só as alterações), então o
 * conjunto exportado é EXATAMENTE o filtrado (RN-005), no síncrono e no
 * assíncrono (o bag faz round-trip pelo Job — RN-005 no async, sem dump integral
 * da trilha com PII — RN-007).
 *
 * Reconstrutível só a partir do bag (INVARIANTE do {@see ReportSource}): o
 * construtor injeta apenas o serviço de query, sem estado de filtro. As colunas,
 * o mapeamento (via {@see ActivityResource}) e a marca personalData=true (HU-101)
 * são preservados fielmente do controller. A guarda de volume técnica
 * (sile.auditoria.export.max_linhas) é aplicada no builder.
 */
final class AtividadesReportSource implements ReportSource
{
    /**
     * As 9 chaves da consulta da trilha (espelha AuditoriaController::filtros) —
     * o whitelisting do source sobre o bag completo das telas.
     *
     * @var list<string>
     */
    private const CHAVES = ['data_de', 'data_ate', 'usuario_id', 'usuario', 'entidade_tipo', 'entidade_id', 'log_name', 'event', 'resultado'];

    public function __construct(private readonly AuditTrailQueryService $trilha) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        $trilhaFiltros = $filtros->only(self::CHAVES);
        $alteracoes = $filtros->get('fonte') === 'alteracoes';
        $maxLinhas = (int) config('sile.auditoria.export.max_linhas', 50000);

        return new ReportDefinition(
            titulo: $alteracoes ? 'Histórico de alterações' : 'Trilha de atividades',
            colunas: [
                ['key' => 'created_at', 'label' => 'Data/hora'],
                ['key' => 'log_name', 'label' => 'Fonte'],
                ['key' => 'event', 'label' => 'Ação'],
                ['key' => 'description', 'label' => 'Descrição'],
                ['key' => 'causer', 'label' => 'Usuário'],
                ['key' => 'acting_for', 'label' => 'Em nome de'],
                ['key' => 'subject_type', 'label' => 'Entidade'],
                ['key' => 'subject_id', 'label' => 'Entidade ID'],
                ['key' => 'result', 'label' => 'Resultado'],
                ['key' => 'rules_version', 'label' => 'Versão de regras'],
                ['key' => 'ip_address', 'label' => 'IP'],
                ['key' => 'channel', 'label' => 'Canal'],
            ],
            builder: fn (): Builder => $alteracoes
                ? $this->trilha->apenasAlteracoes($trilhaFiltros)
                : $this->trilha->filtered($trilhaFiltros),
            mapRow: fn (Activity $atividade): array => $this->linha($atividade),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'auditoria',
            event: 'exporta-trilha',
            personalData: true,
            arquivoBase: 'auditoria',
            // Guarda de volume técnica (sile.auditoria.export.max_linhas) honrada
            // por TODO driver (HU-101): o teto do streaming histórico é preservado
            // sem materializar a trilha inteira; o count() real decide o limiar.
            maxRows: $maxLinhas,
        );
    }

    /**
     * Linha exportada (ordem alinhada às colunas), reusando o {@see ActivityResource}
     * — o MESMO mapeamento do streaming histórico.
     *
     * @return array<int, scalar|null>
     */
    private function linha(Activity $atividade): array
    {
        $dados = (new ActivityResource($atividade))->resolve();

        return [
            $dados['created_at'],
            $dados['log_name'],
            $dados['event'],
            $dados['description'],
            $dados['causer']['nome'] ?? null,
            $dados['acting_for']['nome'] ?? null,
            $dados['subject']['type'] ?? null,
            $dados['subject']['id'] ?? null,
            $dados['result'],
            $dados['rules_version'],
            $dados['ip_address'],
            $dados['channel'],
        ];
    }
}
