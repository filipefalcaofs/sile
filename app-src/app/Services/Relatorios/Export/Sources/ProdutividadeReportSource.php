<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Jobs\GerarExportacaoJob;
use App\Models\ViabilityDecision;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\Export\SyncOnlyReportSource;
use App\Services\Relatorios\ProdutividadeAnalistaService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte de exportação da produtividade por analista (HU-130/HU-131): reproduz a
 * MESMA agregação SQL de {@see ProdutividadeAnalistaService::decisoesAgregadas()}
 * em forma de Builder, com a MESMA minimização — anônima por default (rótulo
 * ordinal `Analista #N`, sem identidade), nominal só sob a flag/permissão
 * (RN-007), escopo do próprio analista.
 *
 * Implementa {@see SyncOnlyReportSource} porque carrega ESTADO no construtor
 * (`$nominal`/`$scopeUserId`), não reconstrutível só a partir do bag de filtros.
 * É a CORREÇÃO do contrato de 15-02: o {@see ReportExporter}
 * força o caminho síncrono e NUNCA despacha o {@see GerarExportacaoJob}
 * (que reconstruiria via `app($sourceClass)` e perderia nominal/escopo). O volume
 * (nº de analistas) é pequeno, então o síncrono é adequado independentemente do
 * limiar.
 *
 * A agregação é envelopada em `fromSub` para que o `count()` do exporter conte
 * ANALISTAS (linhas do conjunto agregado), e não a contagem do primeiro grupo; o
 * ordinal anônimo vem de `row_number()` por volume — estável no streaming por
 * chunks (sem depender de índice no PHP).
 */
final class ProdutividadeReportSource implements ReportSource, SyncOnlyReportSource
{
    public function __construct(
        private readonly bool $nominal = false,
        private readonly ?int $scopeUserId = null,
    ) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        // Escopo do próprio analista ignora o gate nominal — é o recorte dele.
        $nominal = $this->scopeUserId !== null ? false : $this->nominal;
        $scopeUserId = $this->scopeUserId;

        $colunas = $nominal
            ? [
                ['key' => 'analista_nome', 'label' => 'Analista'],
                ['key' => 'decididas', 'label' => 'Decididas'],
                ['key' => 'deferidas', 'label' => 'Deferidas'],
                ['key' => 'indeferidas', 'label' => 'Indeferidas'],
            ]
            : [
                ['key' => 'analista_rotulo', 'label' => 'Analista'],
                ['key' => 'decididas', 'label' => 'Decididas'],
                ['key' => 'deferidas', 'label' => 'Deferidas'],
                ['key' => 'indeferidas', 'label' => 'Indeferidas'],
            ];

        return new ReportDefinition(
            titulo: 'Produtividade por analista',
            colunas: $colunas,
            builder: fn (): Builder => $this->builder($filtros, $nominal, $scopeUserId),
            mapRow: fn (ViabilityDecision $linha): array => $nominal
                ? [
                    (string) $linha->nome,
                    (int) $linha->decididas,
                    (int) $linha->deferidas,
                    (int) $linha->indeferidas,
                ]
                : [
                    'Analista #'.(int) $linha->posicao,
                    (int) $linha->decididas,
                    (int) $linha->deferidas,
                    (int) $linha->indeferidas,
                ],
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-produtividade',
            personalData: $nominal,
            arquivoBase: 'produtividade-analistas',
        );
    }

    /**
     * Builder do conjunto agregado, envelopado em `fromSub` para count()/chunk()/
     * get() corretos sobre o resultado por analista. `row_number()` numera o
     * ordinal anônimo por volume desc (rótulo `Analista #N`).
     *
     * @return Builder<ViabilityDecision>
     */
    private function builder(ReportFilters $filtros, bool $nominal, ?int $scopeUserId): Builder
    {
        $sub = app(ProdutividadeAnalistaService::class)
            ->decisoesAgregadas($filtros, $nominal, $scopeUserId);

        return ViabilityDecision::query()
            ->fromSub($sub, 'p')
            ->selectRaw('p.*, row_number() over (order by decididas desc, analista_id asc) as posicao')
            ->orderByDesc('decididas')
            ->orderBy('analista_id');
    }
}
