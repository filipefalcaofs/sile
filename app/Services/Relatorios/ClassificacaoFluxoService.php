<?php

namespace App\Services\Relatorios;

use App\Enums\AnalysisRecordStatus;
use App\Enums\ResultadoViabilidade;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Como cada processo decidido foi concluído, a partir do que a aplicação grava:
 *
 * - a conclusão é a decisão do processo (viability_decisions, uma por
 *   processo); flow = expresso só sai do motor e das regras automáticas;
 * - a decisão do motor é a ficha pré-analisada (analysis_records com
 *   engine_available e um veredito do motor diferente de pendente) — gerá-la
 *   não conclui nada;
 * - a análise humana é a ficha finalizada por um analista.
 *
 * Quem disparou a operação (decided_by_user_id) não entra no critério: o
 * motor também decide quando acionado pelo REGIN ou pela contingência.
 *
 * As categorias são exclusivas e somam o total. "revisada" é a conclusão
 * automática trocada depois por uma pessoa; "sem_analise_concluida" é a
 * conclusão fora do motor sem ficha finalizada (ex.: indeferimento por prazo
 * do convite). O período segue decided_at, que a revisão regrava.
 */
class ClassificacaoFluxoService
{
    public const AUTOMATICA = 'automatica';

    public const SEMI_EXPRESSA = 'semi_expressa';

    public const HUMANA = 'humana';

    public const REVISADA = 'revisada';

    public const SEM_ANALISE_CONCLUIDA = 'sem_analise_concluida';

    /** @var list<string> */
    public const CATEGORIAS = [self::AUTOMATICA, self::SEMI_EXPRESSA, self::HUMANA, self::REVISADA, self::SEM_ANALISE_CONCLUIDA];

    private const FLOW_EXPRESSO = 'expresso';

    /** Vereditos com que o motor decide; pendente é a ausência de decisão. */
    private const VEREDITOS_DO_MOTOR = [
        ResultadoViabilidade::Permitido->value,
        ResultadoViabilidade::PermitidoComCondicoes->value,
        ResultadoViabilidade::NaoPermitido->value,
    ];

    /**
     * Processos decididos no período por categoria, com o total e a taxa de
     * automação (automáticas ÷ total × 100; null sem decisão no período).
     *
     * @return array{automatica: int, semi_expressa: int, humana: int, revisada: int, sem_analise_concluida: int, total: int, taxa_automacao: ?float}
     */
    public function contagem(ReportFilters $f): array
    {
        $contagem = [];

        foreach (self::CATEGORIAS as $categoria) {
            $contagem[$categoria] = $this->aplicar($this->decididosNoPeriodo($f), $categoria)->count();
        }

        $total = array_sum($contagem);

        return [
            ...$contagem,
            'total' => $total,
            'taxa_automacao' => $total > 0 ? round($contagem[self::AUTOMATICA] / $total * 100, 2) : null,
        ];
    }

    /**
     * Restringe um Builder de solicitações a uma categoria. Processo sem
     * conclusão não pertence a nenhuma.
     *
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    public function aplicar(Builder $query, string $categoria): Builder
    {
        return match ($categoria) {
            self::AUTOMATICA => $this->peloMotor($query)->whereDoesntHave('transitions', fn (Builder $t) => $this->revisao($t)),
            self::REVISADA => $this->peloMotor($query)->whereHas('transitions', fn (Builder $t) => $this->revisao($t)),
            self::SEMI_EXPRESSA => $this->foraDoMotor($query)
                ->whereHas('analysisRecords', fn (Builder $r) => $this->decisaoDoMotor($r))
                ->whereHas('analysisRecords', fn (Builder $r) => $this->analiseHumana($r)),
            self::HUMANA => $this->foraDoMotor($query)
                ->whereDoesntHave('analysisRecords', fn (Builder $r) => $this->decisaoDoMotor($r))
                ->whereHas('analysisRecords', fn (Builder $r) => $this->analiseHumana($r)),
            self::SEM_ANALISE_CONCLUIDA => $this->foraDoMotor($query)
                ->whereDoesntHave('analysisRecords', fn (Builder $r) => $this->analiseHumana($r)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Produtividade semi-expressa em dois números que não se somam: decisões
     * que o motor gerou para a análise (uma por processo, mesmo reavaliado) e
     * análises que analistas concluíram sobre elas (uma por ficha finalizada).
     * Atribuir ou abrir o processo não conta como análise.
     *
     * @return array{decisoes_do_motor: int, analises_humanas: int, por_analista: list<array{analista_id: int, analises: int}>}
     */
    public function produtividadeSemiExpressa(ReportFilters $f, ?int $analistaId = null): array
    {
        $decisoesDoMotor = $this->decisaoDoMotor(AnalysisRecord::query())
            ->when($f->from(), fn (Builder $q, $de) => $q->where('created_at', '>=', $de))
            ->when($f->to(), fn (Builder $q, $ate) => $q->where('created_at', '<=', $ate))
            ->distinct()
            ->count('viability_request_id');

        $analises = $this->analiseHumana(AnalysisRecord::query())
            ->whereHas('viabilityRequest.analysisRecords', fn (Builder $r) => $this->decisaoDoMotor($r))
            ->when($f->from(), fn (Builder $q, $de) => $q->where('finalized_at', '>=', $de))
            ->when($f->to(), fn (Builder $q, $ate) => $q->where('finalized_at', '<=', $ate))
            ->when($analistaId, fn (Builder $q, int $id) => $q->where('analyst_user_id', $id));

        $porAnalista = (clone $analises)
            ->groupBy('analyst_user_id')
            ->orderByDesc('analises')
            ->orderBy('analyst_user_id')
            ->get(['analyst_user_id', DB::raw('count(*) as analises')])
            ->map(fn ($linha): array => ['analista_id' => (int) $linha->analyst_user_id, 'analises' => (int) $linha->analises])
            ->all();

        return [
            'decisoes_do_motor' => $decisoesDoMotor,
            'analises_humanas' => $analises->count(),
            'por_analista' => $porAnalista,
        ];
    }

    /**
     * @return Builder<ViabilityRequest>
     */
    private function decididosNoPeriodo(ReportFilters $f): Builder
    {
        return ViabilityRequest::query()->whereHas('decision', fn (Builder $d) => $d
            ->when($f->from(), fn (Builder $q, $de) => $q->where('decided_at', '>=', $de))
            ->when($f->to(), fn (Builder $q, $ate) => $q->where('decided_at', '<=', $ate)));
    }

    /**
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function peloMotor(Builder $query): Builder
    {
        return $query->whereHas('decision', fn (Builder $d) => $d->where('flow', self::FLOW_EXPRESSO));
    }

    /**
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function foraDoMotor(Builder $query): Builder
    {
        return $query->whereHas('decision', fn (Builder $d) => $d->where('flow', '!=', self::FLOW_EXPRESSO));
    }

    /**
     * Troca do resultado de um processo já concluído (ConclusaoAnaliseService
     * revisa sempre mudando a situação final). Cassar, desativar ou revogar um
     * deferido é encerramento, não revisão.
     *
     * @param  Builder<ViabilityRequestTransition>  $transicoes
     * @return Builder<ViabilityRequestTransition>
     */
    private function revisao(Builder $transicoes): Builder
    {
        $finais = array_map(fn (ViabilityRequestStatus $s): string => $s->value, [
            ViabilityRequestStatus::Deferida,
            ViabilityRequestStatus::Indeferida,
            ViabilityRequestStatus::Desativado,
            ViabilityRequestStatus::Cassado,
            ViabilityRequestStatus::Revogado,
        ]);

        return $transicoes
            ->whereIn('from_status', $finais)
            ->whereIn('to_status', $finais)
            ->whereColumn('to_status', '!=', 'from_status')
            ->whereNot(fn (Builder $encerramento) => $encerramento
                ->where('from_status', ViabilityRequestStatus::Deferida->value)
                ->whereIn('to_status', [
                    ViabilityRequestStatus::Desativado->value,
                    ViabilityRequestStatus::Cassado->value,
                    ViabilityRequestStatus::Revogado->value,
                ]));
    }

    /**
     * @param  Builder<AnalysisRecord>  $fichas
     * @return Builder<AnalysisRecord>
     */
    private function decisaoDoMotor(Builder $fichas): Builder
    {
        return $fichas
            ->where('engine_available', true)
            ->whereIn('engine_snapshot->consolidado', self::VEREDITOS_DO_MOTOR);
    }

    /**
     * @param  Builder<AnalysisRecord>  $fichas
     * @return Builder<AnalysisRecord>
     */
    private function analiseHumana(Builder $fichas): Builder
    {
        return $fichas
            ->where('status', AnalysisRecordStatus::Finalizada->value)
            ->whereNotNull('analyst_user_id');
    }
}
