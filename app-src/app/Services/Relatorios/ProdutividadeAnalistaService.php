<?php

namespace App\Services\Relatorios;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Produtividade por analista (HU-130) sobre DADO REAL, em modo CONSERVADOR por
 * sensibilidade RH/LGPD. A contagem é agregação SQL real (nunca loop PHP):
 * decisões de análise técnica por {@see ViabilityDecision::$decided_by_user_id}
 * (`flow='analise_tecnica'`) e processos atribuídos por
 * {@see ViabilityRequest::$assigned_user_id}.
 *
 * O default é AGREGADO/ANONIMIZADO: cada linha recebe um rótulo ordinal estável
 * (`Analista #N`, por ordem de volume), sem nome nem id — minimização RN-007. A
 * visão NOMINAL (com o nome do analista) é um PARÂMETRO do serviço, liberada só
 * sob a permissão `relatorios.produtividade.nominal` (o gate e a auditoria moram
 * no HTTP de 15-09 — aqui o default conservador não bloqueia). O escopo do
 * próprio analista (`scopeUserId`) devolve só o recorte dele e ignora o nominal
 * (é o próprio). Sem decisões no período → array vazio, NUNCA produtividade
 * inventada (CA-03 anti-fachada).
 *
 * Espelha o {@see ProcessoQueryService}: serviço route-free,
 * Builder reutilizável com `when()` e agregação em SQL.
 */
class ProdutividadeAnalistaService
{
    /** Fluxo da decisão da análise técnica humana (≠ expresso automático). */
    public const FLOW_ANALISE_TECNICA = 'analise_tecnica';

    /**
     * Produtividade por analista no recorte do {@see ReportFilters} (período em
     * `decided_at`). `$nominal=true` inclui o nome do analista (join users);
     * `$scopeUserId` restringe ao próprio analista (e ignora o nominal).
     *
     * @return list<array<string, scalar>>
     */
    public function porAnalista(ReportFilters $filtros, bool $nominal = false, ?int $scopeUserId = null): array
    {
        // Escopo do próprio analista ignora o gate nominal — é o recorte dele.
        $nominal = $scopeUserId !== null ? false : $nominal;

        $linhas = $this->decisoesAgregadas($filtros, $nominal, $scopeUserId)
            ->orderByDesc('decididas')
            ->orderBy('analista_id')
            ->get();

        if ($linhas->isEmpty()) {
            return [];
        }

        $atribuidas = $this->atribuidasPorAnalista($filtros, $scopeUserId);

        return $linhas->values()->map(function (ViabilityDecision $linha, int $i) use ($atribuidas, $nominal): array {
            $analistaId = (int) $linha->analista_id;

            $metricas = [
                'decididas' => (int) $linha->decididas,
                'deferidas' => (int) $linha->deferidas,
                'indeferidas' => (int) $linha->indeferidas,
                'atribuidas' => $atribuidas[$analistaId] ?? 0,
            ];

            if ($nominal) {
                return ['analista_id' => $analistaId, 'nome' => (string) $linha->nome, ...$metricas];
            }

            return ['analista_rotulo' => 'Analista #'.($i + 1), ...$metricas];
        })->all();
    }

    /**
     * Builder agregado das decisões de análise técnica por analista (a sub-query
     * compartilhada com o {@see Export\Sources\ProdutividadeReportSource}). Conta
     * em SQL `decididas`/`deferidas`/`indeferidas` com `groupBy(decided_by_user_id)`;
     * no modo nominal faz join em users para trazer o nome. Os outcomes vão como
     * bind (não interpolados).
     *
     * @return Builder<ViabilityDecision>
     */
    public function decisoesAgregadas(ReportFilters $filtros, bool $nominal = false, ?int $scopeUserId = null): Builder
    {
        $query = ViabilityDecision::query()
            ->where('viability_decisions.flow', self::FLOW_ANALISE_TECNICA)
            ->whereNotNull('viability_decisions.decided_by_user_id')
            ->when($scopeUserId !== null, fn (Builder $q): Builder => $q->where('viability_decisions.decided_by_user_id', $scopeUserId))
            ->when($filtros->from(), fn (Builder $q, Carbon $from): Builder => $q->where('viability_decisions.decided_at', '>=', $from))
            ->when($filtros->to(), fn (Builder $q, Carbon $to): Builder => $q->where('viability_decisions.decided_at', '<=', $to))
            ->groupBy('viability_decisions.decided_by_user_id');

        $select = 'viability_decisions.decided_by_user_id as analista_id, '
            .'count(*) as decididas, '
            .'sum(case when viability_decisions.outcome = ? then 1 else 0 end) as deferidas, '
            .'sum(case when viability_decisions.outcome = ? then 1 else 0 end) as indeferidas';

        if ($nominal) {
            $query->join('users', 'users.id', '=', 'viability_decisions.decided_by_user_id')
                ->groupBy('users.name');

            $select .= ', users.name as nome';
        }

        return $query->selectRaw($select, [
            DecisionOutcome::Deferida->value,
            DecisionOutcome::Indeferida->value,
        ]);
    }

    /**
     * Processos atribuídos a cada analista por `assigned_user_id` (agregação SQL),
     * no mesmo período (`protocoled_at`). Mapa [analista_id => total].
     *
     * @return array<int, int>
     */
    private function atribuidasPorAnalista(ReportFilters $filtros, ?int $scopeUserId = null): array
    {
        return ViabilityRequest::query()
            ->whereNotNull('assigned_user_id')
            ->when($scopeUserId !== null, fn (Builder $q): Builder => $q->where('assigned_user_id', $scopeUserId))
            ->when($filtros->from(), fn (Builder $q, Carbon $from): Builder => $q->where('protocoled_at', '>=', $from))
            ->when($filtros->to(), fn (Builder $q, Carbon $to): Builder => $q->where('protocoled_at', '<=', $to))
            ->groupBy('assigned_user_id')
            ->selectRaw('assigned_user_id, count(*) as atribuidas')
            ->pluck('atribuidas', 'assigned_user_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}
