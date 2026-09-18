<?php

namespace App\Services\Relatorios;

use App\Enums\AnalysisStage;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisSlaService;
use App\Services\Analise\SlaStatus;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Relatório operacional de SLA e vencimentos da análise: responde "o que está
 * vencido ou vence em X dias, por setor/analista?" sobre os processos EM
 * ANDAMENTO (em_analise/em_pendencia com analysis_due_at materializado por
 * quem transiciona — 10-07). O resumo agrega em SQL (nunca loop PHP); a linha
 * reusa o semáforo on-the-fly do {@see AnalysisSlaService} (nunca persistido).
 * A janela de "vencendo" é parametrizável (relatorios.sla.janela_vencimento_dias,
 * default inline 2 dias — HU-014). Route-free e sem estado, como os demais
 * serviços de relatório do EP15.
 */
class SlaVencimentosService
{
    /** Status canônicos em andamento na análise (eixo do SLA). */
    private const STATUS_EM_ANDAMENTO = [
        ViabilityRequestStatus::EmAnalise->value,
        ViabilityRequestStatus::EmPendencia->value,
    ];

    private const FAIXAS_AGING = [
        '0_50' => '0–50%',
        '50_80' => '50–80%',
        '80_100' => '80–100%',
        'acima_100' => '>100%',
        'indeterminada' => 'Indeterminada',
    ];

    public function __construct(private readonly AnalysisSlaService $sla) {}

    /**
     * Resumo em SQL: total em andamento, vencidos (due_at < agora) e vencendo
     * na janela parametrizável. Contagens zeradas são FATO (não degradam para
     * null — diferente das taxas, aqui zero é resposta real).
     *
     * @return array{em_andamento: int, vencidos: int, vencendo: int, janela_vencimento_dias: int}
     */
    public function resumo(ReportFilters $f): array
    {
        $agora = Carbon::now();
        $janela = $this->janelaVencimentoDias();

        return [
            'em_andamento' => (clone $this->builderSemOrdem($f))->count(),
            'vencidos' => (clone $this->builderSemOrdem($f))->where('analysis_due_at', '<', $agora)->count(),
            'vencendo' => (clone $this->builderSemOrdem($f))
                ->whereBetween('analysis_due_at', [$agora, $agora->copy()->addDays($janela)])
                ->count(),
            'janela_vencimento_dias' => $janela,
        ];
    }

    /**
     * Builder dos processos em andamento com prazo, ordenado pelo vencimento
     * (o mais urgente primeiro) — base da tela paginada e do export (RN-005:
     * o MESMO recorte nos dois caminhos). Eager-load de setor/analista para a
     * projeção sem N+1.
     *
     * @return Builder<ViabilityRequest>
     */
    public function builder(ReportFilters $f): Builder
    {
        return $this->builderSemOrdem($f)
            ->with(['sector', 'assignedTo'])
            ->orderBy('analysis_due_at');
    }

    /**
     * Projeção da linha (tela + export): o semáforo vem do AnalysisSlaService
     * quando há início de etapa materializado; sem ele, degrada honesto para
     * vencido/no prazo pelo due_at (nunca um semáforo inventado).
     *
     * @return array<string, mixed>
     */
    public function linha(ViabilityRequest $r): array
    {
        $agora = Carbon::now();
        $dueAt = $r->analysis_due_at;

        if ($dueAt !== null && $r->analysis_stage_started_at !== null) {
            $sla = $this->sla->statusFor($dueAt, $r->analysis_stage_started_at, $agora);
            $status = $sla['status'];
            $restante = $sla['restante'];
        } elseif ($dueAt !== null) {
            $status = $dueAt->lt($agora) ? SlaStatus::Vermelho : SlaStatus::Verde;
            $restante = $dueAt->locale('pt_BR')->diffForHumans([
                'other' => $agora,
                'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
                'parts' => 2,
            ]);
        } else {
            $status = null;
            $restante = null;
        }

        return [
            'id' => $r->id,
            'processo' => $r->protocol_number,
            'etapa' => $r->analysis_stage instanceof AnalysisStage ? $r->analysis_stage->label() : ($r->analysis_stage ?? '—'),
            'setor' => $r->sector?->name,
            'analista' => $r->assignedTo?->name,
            'iniciado_em' => $r->analysis_stage_started_at?->toIso8601String(),
            'limite_em' => $dueAt?->toIso8601String(),
            'situacao' => $status?->value,
            'situacao_label' => $status?->label(),
            'restante' => $restante,
        ];
    }

    /**
     * @return list<array{faixa: string, label: string, total: int}>
     */
    public function aging(ReportFilters $f): array
    {
        $agora = Carbon::now()->toDateTimeString();
        $progresso = $this->progressoSql();
        $faixaSql = "CASE
        WHEN analysis_stage_started_at IS NULL THEN 'indeterminada'
        WHEN analysis_due_at <= analysis_stage_started_at THEN 'indeterminada'
        WHEN analysis_due_at <= ? THEN 'acima_100'
        WHEN {$progresso} < 0.5 THEN '0_50'
        WHEN {$progresso} < 0.8 THEN '50_80'
        WHEN {$progresso} < 1.0 THEN '80_100'
        ELSE 'acima_100'
    END";

        $ocorrenciasProgresso = substr_count($faixaSql, $progresso);
        $bindings = array_merge([$agora], array_fill(0, $ocorrenciasProgresso, $agora));

        $totais = (clone $this->builderSemOrdem($f))
            ->selectRaw("{$faixaSql} as faixa, count(*) as total", $bindings)
            ->groupBy('faixa')
            ->pluck('total', 'faixa');

        return collect(self::FAIXAS_AGING)
            ->map(fn (string $label, string $faixa): array => [
                'faixa' => $faixa,
                'label' => $label,
                'total' => (int) ($totais[$faixa] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{etapa: string, label: string, total: int}>
     */
    public function atrasadosPorEtapa(ReportFilters $f): array
    {
        $totais = (clone $this->builderSemOrdem($f))
            ->where('analysis_due_at', '<', Carbon::now())
            ->groupBy('analysis_stage')
            ->get([
                'analysis_stage',
                DB::raw('count(*) as total'),
            ])
            ->keyBy(fn ($linha): string => $linha->analysis_stage instanceof AnalysisStage
                ? $linha->analysis_stage->value
                : (string) $linha->analysis_stage);

        return collect(AnalysisStage::cases())
            ->map(fn (AnalysisStage $etapa): array => [
                'etapa' => $etapa->value,
                'label' => $etapa->label(),
                'total' => (int) ($totais->get($etapa->value)?->total ?? 0),
            ])
            ->all();
    }

    /**
     * @return array{dentro_sla: int, com_prazo: int, taxa: float|null, data_de: string|null, data_ate: string|null}
     */
    public function cumprimento(ReportFilters $f): array
    {
        $base = ViabilityDecision::query()
            ->join('viability_requests', 'viability_requests.id', '=', 'viability_decisions.viability_request_id')
            ->whereNotNull('viability_requests.analysis_due_at')
            ->when($f->from(), fn (Builder $q, $from): Builder => $q->where('viability_decisions.decided_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, $to): Builder => $q->where('viability_decisions.decided_at', '<=', $to))
            ->when($f->setorId(), fn (Builder $q, int $setor): Builder => $q->where('viability_requests.sector_id', $setor))
            ->when($f->analistaId(), fn (Builder $q, int $analista): Builder => $q->where('viability_requests.assigned_user_id', $analista));

        $comPrazo = (clone $base)->count();
        $dentro = (clone $base)
            ->whereColumn('viability_decisions.decided_at', '<=', 'viability_requests.analysis_due_at')
            ->count();

        return [
            'dentro_sla' => $dentro,
            'com_prazo' => $comPrazo,
            'taxa' => $comPrazo > 0 ? round($dentro / $comPrazo * 100, 1) : null,
            'data_de' => $f->from()?->toDateString(),
            'data_ate' => $f->to()?->toDateString(),
        ];
    }

    /**
     * Janela (dias) do "vencendo": parâmetro administrável
     * relatorios.sla.janela_vencimento_dias com default inline 2 (HU-014).
     */
    private function janelaVencimentoDias(): int
    {
        return (int) Settings::get(
            'relatorios.sla.janela_vencimento_dias',
            config('sile.relatorios.sla.janela_vencimento_dias', 2),
        );
    }

    /**
     * Base SEM ordenação nem eager-load (o resumo só conta): em andamento com
     * prazo materializado, recortada por setor/analista quando filtrados.
     *
     * @return Builder<ViabilityRequest>
     */
    private function builderSemOrdem(ReportFilters $f): Builder
    {
        return ViabilityRequest::query()
            ->whereIn('status', self::STATUS_EM_ANDAMENTO)
            ->whereNotNull('analysis_due_at')
            ->when($f->setorId(), fn (Builder $q, int $setor): Builder => $q->where('sector_id', $setor))
            ->when($f->analistaId(), fn (Builder $q, int $analista): Builder => $q->where('assigned_user_id', $analista));
    }

    private function progressoSql(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return '(EXTRACT(EPOCH FROM CAST(? AS timestamp)) - EXTRACT(EPOCH FROM analysis_stage_started_at))'
                .' / NULLIF(EXTRACT(EPOCH FROM analysis_due_at) - EXTRACT(EPOCH FROM analysis_stage_started_at), 0)';
        }

        return '(strftime(\'%s\', ?) - strftime(\'%s\', analysis_stage_started_at)) * 1.0'
            .' / NULLIF(strftime(\'%s\', analysis_due_at) - strftime(\'%s\', analysis_stage_started_at), 0)';
    }
}
