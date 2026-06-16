<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Http\Resources\ProcessoResource;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte da CONSULTA DE PROCESSOS (HU-082) para o export transversal
 * (HU-131/RN-009): consolida o CSV simples que vivia no
 * ProcessoController::exportarCsv no contrato único, PRESERVANDO exatamente as
 * colunas, a ordem e o nome de arquivo históricos (rede anti-regressão 10-14). O
 * conjunto exportado é EXATAMENTE o filtrado da consulta (RN-005), reusando
 * {@see ProcessoQueryService::filtered()} a partir das 17 chaves do
 * {@see ReportFilters::toProcessoFiltros()} — sem filtro reimplementado.
 *
 * É uma fonte SEPARADA da {@see SolicitacoesReportSource} (export dos relatórios
 * — 15-03/09): aquela tem shape, evento e arquivo próprios ('solicitacoes',
 * 'exporta-solicitacoes', 10 colunas com bairro/decisão) e é consumida pelo
 * RelatorioController. Esta espelha a tela de processos ('processos',
 * 'exporta-processos', as 9 colunas do SAPS), mantendo a meta-auditoria
 * 'analise'/'exporta-processos-csv' idêntica à atual. Reconstrutível só pelo bag.
 */
final class ProcessosReportSource implements ReportSource
{
    public function __construct(private readonly ProcessoQueryService $processos) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Consulta de processos',
            colunas: [
                ['key' => 'protocolo', 'label' => 'Processo'],
                ['key' => 'bap', 'label' => 'BAP'],
                ['key' => 'produto_tvl', 'label' => 'Produto TVL'],
                ['key' => 'empresa', 'label' => 'Empresa'],
                ['key' => 'cnpj', 'label' => 'CNPJ'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'categoria', 'label' => 'Categoria'],
                ['key' => 'analista', 'label' => 'Analista'],
                ['key' => 'prazo', 'label' => 'Prazo'],
            ],
            // RN-005: o MESMO Builder filtrado da consulta de processos, recortado
            // pelas 17 chaves do bag — zero filtro duplicado.
            builder: fn (): Builder => $this->processos->filtered($filtros->toProcessoFiltros()),
            mapRow: fn (ViabilityRequest $processo): array => $this->linha($processo),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'analise',
            event: 'exporta-processos',
            personalData: false,
            arquivoBase: 'processos',
        );
    }

    /**
     * Linha exportada (ordem alinhada às colunas), lendo os campos já preparados
     * pela {@see ProcessoResource} — idêntica ao CSV histórico de processos.
     *
     * @return array<int, scalar|null>
     */
    private function linha(ViabilityRequest $processo): array
    {
        $dados = (new ProcessoResource($processo))->resolve();

        return [
            $dados['protocol_number'],
            $dados['bap'],
            $dados['tvl_product_number'],
            $dados['empresa'],
            $dados['cnpj'],
            $dados['status_label'],
            $dados['categoria'],
            $dados['analista'],
            $dados['analysis_due_at'],
        ];
    }
}
