<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\RelatorioTempoEmissaoTvlService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte de exportação do relatório SAPS "Tempo de Emissão de TVL" (Tela R2). Reusa
 * EXATAMENTE o Builder de {@see RelatorioTempoEmissaoTvlService::builder()} (RN-005
 * — mesmo recorte da tela, sem filtro reimplementado, CA-R2-03) e mapeia cada linha
 * a partir de {@see RelatorioTempoEmissaoTvlService::linha()}. Reconstrutível só a
 * partir do bag (INVARIANTE do {@see ReportSource}): o construtor injeta apenas o
 * serviço (container) e o recorte viaja no bag serializável — NÃO é SyncOnly.
 *
 * `personalData=false`: as colunas trazem só protocolo, serviço, datas e nº do TVL
 * — sem PII do requerente. Os blocos DAM saem em branco (não modelados) e a duração
 * Emissão−Abertura é reportada em MINUTOS ÚTEIS (mesma semântica do TempoAnalise).
 */
final class RelatorioTempoEmissaoTvlReportSource implements ReportSource
{
    public function __construct(private readonly RelatorioTempoEmissaoTvlService $service) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Tempo de emissão de TVL',
            colunas: [
                ['key' => 'processo', 'label' => 'Processo'],
                ['key' => 'servico', 'label' => 'Serviço'],
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'abertura', 'label' => 'Abertura'],
                ['key' => 'dam_numero', 'label' => 'DAM Nº'],
                ['key' => 'dam_emissao', 'label' => 'DAM Emissão'],
                ['key' => 'dam_pagamento', 'label' => 'DAM Pagamento'],
                ['key' => 'dam_valor', 'label' => 'DAM Valor'],
                ['key' => 'tvl_disponivel', 'label' => 'TVL Disponível'],
                ['key' => 'tvl_numero', 'label' => 'Nº TVL'],
                ['key' => 'emissao', 'label' => 'Emissão'],
                ['key' => 'duracao_minutos', 'label' => 'Emissão−Abertura (min úteis)'],
            ],
            builder: fn (): Builder => $this->service->builder($filtros),
            mapRow: fn (ViabilityRequest $r): array => $this->linha($r),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-tempo-emissao-tvl',
            personalData: false,
            arquivoBase: 'tempo-emissao-tvl',
        );
    }

    /**
     * Linha exportada (ordem alinhada às colunas): reusa a projeção da tela e
     * formata o booleano de disponibilidade para Sim/Não.
     *
     * @return array<int, scalar|null>
     */
    private function linha(ViabilityRequest $r): array
    {
        $l = $this->service->linha($r);

        return [
            $l['processo'],
            $l['servico'],
            $l['tipo'],
            $l['abertura'],
            $l['dam_numero'],
            $l['dam_emissao'],
            $l['dam_pagamento'],
            $l['dam_valor'],
            $l['tvl_disponivel'] ? 'Sim' : 'Não',
            $l['tvl_numero'],
            $l['emissao'],
            $l['duracao_minutos'],
        ];
    }
}
