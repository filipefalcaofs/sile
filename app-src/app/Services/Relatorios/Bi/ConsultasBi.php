<?php

namespace App\Services\Relatorios\Bi;

use App\Enums\AnalysisCategory;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Populações e filtros comuns dos indicadores do BI. As colunas são sempre
 * qualificadas para conviver com os joins de cada indicador.
 */
trait ConsultasBi
{
    /** Bairro oficial (camada GeoSalvador) com o digitado de reserva. */
    private const BAIRRO = 'coalesce(viability_requests.bairro_oficial, viability_requests.address_neighborhood)';

    /** Situações em que a solicitação ainda aguarda decisão. */
    private const STATUS_ABERTOS = [
        ViabilityRequestStatus::Protocolada->value,
        ViabilityRequestStatus::AguardandoBap->value,
        ViabilityRequestStatus::EmAnalise->value,
        ViabilityRequestStatus::EmPendencia->value,
    ];

    /**
     * Solicitações protocoladas no período (rascunhos e canceladas ficam fora).
     *
     * @return Builder<ViabilityRequest>
     */
    protected function solicitacoes(ReportFilters $f): Builder
    {
        return $this->filtrar(
            ViabilityRequest::query()
                ->whereNotNull('viability_requests.protocoled_at')
                ->whereNotIn('viability_requests.status', [
                    ViabilityRequestStatus::Rascunho->value,
                    ViabilityRequestStatus::Cancelada->value,
                ])
                ->when($f->from(), fn (Builder $q, $de): Builder => $q->where('viability_requests.protocoled_at', '>=', $de))
                ->when($f->to(), fn (Builder $q, $ate): Builder => $q->where('viability_requests.protocoled_at', '<=', $ate)),
            $f,
        );
    }

    /**
     * Estoque atual: o que segue em aberto, sem recorte de período (é uma foto
     * de hoje), respeitando os demais filtros.
     *
     * @return Builder<ViabilityRequest>
     */
    protected function estoque(ReportFilters $f): Builder
    {
        return $this->filtrar(
            ViabilityRequest::query()->whereIn('viability_requests.status', self::STATUS_ABERTOS),
            $f,
        );
    }

    /**
     * Decisões vinculantes com `decided_at` no período, juntadas à solicitação.
     *
     * @return Builder<ViabilityDecision>
     */
    protected function decisoes(ReportFilters $f): Builder
    {
        return $this->filtrar(
            ViabilityDecision::query()
                ->join('viability_requests', 'viability_requests.id', '=', 'viability_decisions.viability_request_id')
                ->when($f->from(), fn (Builder $q, $de): Builder => $q->where('viability_decisions.decided_at', '>=', $de))
                ->when($f->to(), fn (Builder $q, $ate): Builder => $q->where('viability_decisions.decided_at', '<=', $ate)),
            $f,
        );
    }

    /**
     * Horas úteis de viabilidade das decisões do período, já materializadas no
     * processo. Processo sem tempo apurável não entra na amostra.
     *
     * @return list<float>
     */
    protected function temposViabilidade(ReportFilters $f, ?string $fluxo = null): array
    {
        return $this->decisoes($f)
            ->whereNotNull('viability_requests.viabilidade_horas_uteis')
            ->when($fluxo, fn (Builder $q, string $valor): Builder => $q->where('viability_decisions.flow', $valor))
            ->toBase()
            ->pluck('viability_requests.viabilidade_horas_uteis')
            ->map(fn ($valor): float => (float) $valor)
            ->all();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function filtrar(Builder $query, ReportFilters $f): Builder
    {
        return $query
            ->when($f->setorId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.sector_id', $id))
            ->when($f->analistaId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.assigned_user_id', $id))
            ->when($f->bairro(), fn (Builder $q, string $b): Builder => $q->whereLike(DB::raw(self::BAIRRO), "%{$b}%", caseSensitive: false))
            ->when($f->categoria(), fn (Builder $q, string $c): Builder => match ($c) {
                'malha_fina' => $q->where('viability_requests.in_fine_mesh', true),
                'sede_escritorio' => $q->where('viability_requests.is_virtual_office', true),
                'expresso' => $q->where('viability_requests.analysis_category', AnalysisCategory::Expresso->value),
                'semi_expresso' => $q->where('viability_requests.analysis_category', AnalysisCategory::SemiExpresso->value),
                default => $q,
            })
            ->when($f->cnae(), fn (Builder $q, string $cnae): Builder => $q->whereExists(function ($sub) use ($cnae): void {
                $sub->selectRaw('1')
                    ->from('viability_request_cnaes as vrc_f')
                    ->join('cnaes as cnae_f', 'cnae_f.id', '=', 'vrc_f.cnae_id')
                    ->whereColumn('vrc_f.viability_request_id', 'viability_requests.id')
                    ->where('cnae_f.code', $cnae);
            }));
    }
}
