<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\Communication;
use App\Services\Relatorios\ComunicacoesFalhaService;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Fonte de exportação da consulta de falhas de comunicação: reusa EXATAMENTE
 * o builder/linha do {@see ComunicacoesFalhaService} (RN-005 — mesmo recorte
 * da tela). Reconstrutível só a partir do bag (INVARIANTE do {@see ReportSource}).
 *
 * `personalData=false`: as colunas trazem processo/canal/tipo/erro — SEM o
 * destinatário (LGPD: o cidadão não aparece na exportação).
 */
final class ComunicacoesFalhaReportSource implements ReportSource
{
    public function __construct(private readonly ComunicacoesFalhaService $service) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Falhas de comunicação',
            colunas: [
                ['key' => 'processo', 'label' => 'Processo'],
                ['key' => 'canal_label', 'label' => 'Canal'],
                ['key' => 'tipo_label', 'label' => 'Tipo'],
                ['key' => 'titulo', 'label' => 'Título'],
                ['key' => 'status_label', 'label' => 'Situação'],
                ['key' => 'erro', 'label' => 'Erro'],
                ['key' => 'em', 'label' => 'Em'],
            ],
            builder: fn (): Builder => $this->service->builder($filtros),
            mapRow: fn (Communication $c): array => $this->linha($c),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-comunicacoes-falhas',
            personalData: false,
            arquivoBase: 'comunicacoes-falhas',
        );
    }

    /**
     * Linha exportada (ordem alinhada às colunas): reusa a projeção da tela e
     * formata a data para pt-BR.
     *
     * @return array<int, scalar|null>
     */
    private function linha(Communication $c): array
    {
        $l = $this->service->linha($c);

        return [
            $l['processo'],
            $l['canal_label'],
            $l['tipo_label'],
            $l['titulo'],
            $l['status_label'],
            $l['erro'],
            $l['em'] !== null ? Carbon::parse($l['em'])->format('d/m/Y H:i') : null,
        ];
    }
}
