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
 * Fonte das SOLICITAÇÕES de viabilidade para o export transversal (HU-131): o
 * conjunto exportado é EXATAMENTE o filtrado da consulta de processos (RN-005),
 * tanto no síncrono quanto no assíncrono. Reusa
 * {@see ProcessoQueryService::filtered()} a partir de
 * {@see ReportFilters::toProcessoFiltros()} — o bag carrega o vocabulário
 * COMPLETO das telas (17 chaves do SAPS), então NÃO há filtro reimplementado nem
 * recorte manual; toProcessoFiltros é a fonte única do filtro.
 *
 * Reconstrutível só a partir do bag (INVARIANTE do {@see ReportSource}): o
 * construtor injeta apenas o {@see ProcessoQueryService} (resolvido pelo
 * container em `app(self::class)`), sem estado de filtro — logo o
 * GerarExportacaoJob a reconstrói no assíncrono sem perder nada. As colunas
 * espelham o CSV de processos (ProcessoController::exportarCsv) acrescidas de
 * bairro e da decisão (quando/resultado); o CNPJ vem já formatado da
 * {@see ProcessoResource} (PII minimizada na origem — RN-007). personalData=true.
 */
final class SolicitacoesReportSource implements ReportSource
{
    public function __construct(private readonly ProcessoQueryService $processos) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Solicitações de viabilidade',
            colunas: [
                ['key' => 'protocolo', 'label' => 'Processo'],
                ['key' => 'empresa', 'label' => 'Empresa'],
                ['key' => 'cnpj', 'label' => 'CNPJ'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'categoria', 'label' => 'Categoria'],
                ['key' => 'bairro', 'label' => 'Bairro'],
                ['key' => 'analista', 'label' => 'Analista'],
                ['key' => 'protocolado_em', 'label' => 'Protocolado em'],
                ['key' => 'decidido_em', 'label' => 'Decidido em'],
                ['key' => 'resultado', 'label' => 'Resultado'],
            ],
            // RN-005: o MESMO Builder filtrado da consulta de processos, recortado
            // pelas 17 chaves do bag — zero filtro duplicado.
            builder: fn (): Builder => $this->processos->filtered($filtros->toProcessoFiltros()),
            mapRow: fn (ViabilityRequest $solicitacao): array => $this->linha($solicitacao),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-solicitacoes',
            personalData: true,
            arquivoBase: 'solicitacoes',
        );
    }

    /**
     * Linha exportada (ordem alinhada às colunas), lendo os campos já preparados
     * pela {@see ProcessoResource} (status_label, categoria primária, CNPJ
     * formatado) + bairro e decisão diretamente do modelo carregado.
     *
     * @return array<int, scalar|null>
     */
    private function linha(ViabilityRequest $solicitacao): array
    {
        $dados = (new ProcessoResource($solicitacao))->resolve();

        return [
            $dados['protocol_number'],
            $dados['empresa'],
            $dados['cnpj'],
            $dados['status_label'],
            $dados['categoria'],
            $solicitacao->address_neighborhood,
            $dados['analista'],
            $dados['protocoled_at'],
            $solicitacao->decision?->decided_at?->toIso8601String(),
            $solicitacao->decision?->outcome?->label(),
        ];
    }
}
