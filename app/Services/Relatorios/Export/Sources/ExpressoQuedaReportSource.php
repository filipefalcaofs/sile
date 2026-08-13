<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Enums\TipoGatilho;
use App\Models\ExpressoQueda;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte de exportação das quedas do fluxo expresso (HU-145/HU-131): exporta o
 * conjunto FILTRADO de {@see ExpressoQueda} (join na solicitação para o
 * protocolo), na MESMA semântica do {@see App\Services\Relatorios\ExpressoQuedaService}.
 *
 * `personalData=false`: as colunas (protocolo/CNAE/gatilho/dimensão/motivo/data)
 * não trazem PII direta do requerente. Reconstrutível só a partir do bag
 * (RN-005): nenhum estado de construtor — segue o caminho síncrono OU assíncrono
 * do {@see App\Services\Relatorios\Export\ReportExporter} sem perder filtro.
 */
final class ExpressoQuedaReportSource implements ReportSource
{
    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Quedas do fluxo expresso',
            colunas: [
                ['key' => 'protocolo', 'label' => 'Protocolo'],
                ['key' => 'cnae', 'label' => 'CNAE'],
                ['key' => 'tipo_gatilho', 'label' => 'Gatilho'],
                ['key' => 'dimensao', 'label' => 'Dimensão'],
                ['key' => 'motivo', 'label' => 'Motivo'],
                ['key' => 'caiu_em', 'label' => 'Caiu em'],
            ],
            builder: fn (): Builder => $this->builder($filtros),
            mapRow: fn (ExpressoQueda $linha): array => [
                (string) ($linha->protocol_number ?? '—'),
                (string) ($linha->cnae ?? '—'),
                $this->rotuloGatilho($linha->tipo_gatilho),
                (string) ($linha->dimensao ?? '—'),
                (string) $linha->motivo,
                (string) $linha->created_at?->format('d/m/Y H:i'),
            ],
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-quedas-expresso',
            personalData: false,
            arquivoBase: 'quedas-expresso',
        );
    }

    /**
     * Builder das quedas filtradas por período (created_at), com o protocolo da
     * solicitação via join — recorte idêntico ao do serviço (RN-005).
     *
     * @return Builder<ExpressoQueda>
     */
    private function builder(ReportFilters $filtros): Builder
    {
        return ExpressoQueda::query()
            ->join('viability_requests', 'viability_requests.id', '=', 'expresso_quedas.viability_request_id')
            ->when($filtros->from(), fn (Builder $q, $from): Builder => $q->where('expresso_quedas.created_at', '>=', $from))
            ->when($filtros->to(), fn (Builder $q, $to): Builder => $q->where('expresso_quedas.created_at', '<=', $to))
            ->orderByDesc('expresso_quedas.created_at')
            ->select([
                'expresso_quedas.id',
                'viability_requests.protocol_number',
                'expresso_quedas.cnae',
                'expresso_quedas.tipo_gatilho',
                'expresso_quedas.dimensao',
                'expresso_quedas.motivo',
                'expresso_quedas.created_at',
            ]);
    }

    /**
     * Rótulo legível do gatilho — null vira 'não classificado' (honesto).
     */
    private function rotuloGatilho(?string $codigo): string
    {
        if ($codigo === null) {
            return 'não classificado';
        }

        return TipoGatilho::tryFrom($codigo)?->label() ?? $codigo;
    }
}
