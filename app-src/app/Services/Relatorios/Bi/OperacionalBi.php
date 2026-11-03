<?php

namespace App\Services\Relatorios\Bi;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Services\Relatorios\GargalosService;
use App\Services\Relatorios\SlaVencimentosService;
use App\Services\Relatorios\TempoAnaliseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Eficiência operacional: quanto tempo o processo leva, onde para e quem carrega
 * a fila. Os indicadores por analista saem com identificador interno; o nome só
 * vai quando `viabiliza.integrations.bi.nominal` está ligado.
 */
class OperacionalBi
{
    use ConsultasBi;

    /** Faixas de idade da fila, em dias corridos desde o protocolo. */
    private const FAIXAS_IDADE = [
        ['ate_2_dias', 'Até 2 dias', 0, 2],
        ['3_a_5_dias', '3 a 5 dias', 3, 5],
        ['6_a_10_dias', '6 a 10 dias', 6, 10],
        ['11_a_20_dias', '11 a 20 dias', 11, 20],
        ['21_a_30_dias', '21 a 30 dias', 21, 30],
        ['acima_30_dias', 'Acima de 30 dias', 31, null],
    ];

    public function __construct(
        private readonly SlaVencimentosService $sla,
        private readonly TempoAnaliseService $tempos,
        private readonly GargalosService $gargalos,
    ) {}

    /**
     * Tempo de conclusão (horas úteis): média, mediana e percentil 90, no geral
     * e por modalidade de decisão.
     *
     * @return array<string, mixed>
     */
    public function tempoConclusao(BiConsulta $c): array
    {
        return [
            'unidade' => 'horas_uteis',
            'geral' => BiEstatistica::resumo($this->temposViabilidade($c->filtros)),
            'por_modalidade' => [
                'expresso' => BiEstatistica::resumo($this->temposViabilidade($c->filtros, 'expresso')),
                'analise_tecnica' => BiEstatistica::resumo($this->temposViabilidade($c->filtros, 'analise_tecnica')),
            ],
        ];
    }

    /**
     * Tempo médio por etapa do processo (horas úteis).
     *
     * @return list<array{etapa: string, media_horas_uteis: float|null, amostras: int}>
     */
    public function tempoPorEtapa(BiConsulta $c): array
    {
        return array_map(fn (array $etapa): array => [
            'etapa' => $etapa['etapa'],
            'media_horas_uteis' => $etapa['media_minutos'] === null ? null : round($etapa['media_minutos'] / 60, 2),
            'amostras' => $etapa['amostras'],
        ], $this->tempos->tempoPorEtapa($c->filtros)['etapas']);
    }

    /**
     * @return array{dentro_do_prazo: int, com_prazo: int, taxa: float|null}
     */
    public function dentroDoSla(BiConsulta $c): array
    {
        $cumprimento = $this->sla->cumprimento($c->filtros);

        return [
            'dentro_do_prazo' => $cumprimento['dentro_sla'],
            'com_prazo' => $cumprimento['com_prazo'],
            'taxa' => $cumprimento['taxa'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function slaVencido(BiConsulta $c): array
    {
        return [
            ...$this->sla->resumo($c->filtros),
            ...$this->sla->detalheAcao($c->filtros),
            'por_etapa' => $this->sla->atrasadosPorEtapa($c->filtros),
        ];
    }

    /**
     * Envelhecimento da fila: dias corridos desde o protocolo dos processos em
     * aberto, e o avanço sobre o prazo de cada um.
     *
     * @return array{total: int, por_idade: list<array<string, mixed>>, por_prazo: list<array<string, mixed>>}
     */
    public function envelhecimentoFila(BiConsulta $c): array
    {
        $hoje = Carbon::now()->startOfDay();
        $contagens = array_fill_keys(array_column(self::FAIXAS_IDADE, 0), 0);
        $total = 0;

        $protocolos = $this->estoque($c->filtros)
            ->whereNotNull('viability_requests.protocoled_at')
            ->toBase()
            ->pluck('viability_requests.protocoled_at');

        foreach ($protocolos as $protocolo) {
            $dias = (int) Carbon::parse((string) $protocolo)->startOfDay()->diffInDays($hoje);

            foreach (self::FAIXAS_IDADE as [$chave, , $minimo, $maximo]) {
                if ($dias >= $minimo && ($maximo === null || $dias <= $maximo)) {
                    $contagens[$chave]++;
                    $total++;

                    break;
                }
            }
        }

        return [
            'total' => $total,
            'por_idade' => array_map(fn (array $faixa): array => [
                'faixa' => $faixa[0],
                'rotulo' => $faixa[1],
                'total' => $contagens[$faixa[0]],
            ], self::FAIXAS_IDADE),
            'por_prazo' => $this->sla->aging($c->filtros),
        ];
    }

    /**
     * Processos em análise ou em convite, por setor e analista.
     *
     * @return list<array<string, mixed>>
     */
    public function cargaPorAnalistaSetor(BiConsulta $c): array
    {
        $nominal = (bool) config('viabiliza.integrations.bi.nominal');

        $linhas = $this->estoque($c->filtros)
            ->whereIn('viability_requests.status', [
                ViabilityRequestStatus::EmAnalise->value,
                ViabilityRequestStatus::EmPendencia->value,
            ])
            ->leftJoin('sectors', 'sectors.id', '=', 'viability_requests.sector_id')
            ->leftJoin('users', 'users.id', '=', 'viability_requests.assigned_user_id')
            ->groupBy('viability_requests.sector_id', 'sectors.name', 'viability_requests.assigned_user_id', 'users.name')
            ->orderByDesc('total')
            ->toBase()
            ->get([
                'viability_requests.sector_id as setor_id',
                'sectors.name as setor',
                'viability_requests.assigned_user_id as analista_id',
                'users.name as analista_nome',
                DB::raw('count(*) as total'),
            ]);

        return $linhas->map(fn ($linha): array => [
            'setor_id' => $linha->setor_id !== null ? (int) $linha->setor_id : null,
            'setor' => $linha->setor,
            'analista_id' => $linha->analista_id !== null ? (int) $linha->analista_id : null,
            ...($nominal ? ['analista' => $linha->analista_nome] : []),
            'total' => (int) $linha->total,
        ])->all();
    }

    /**
     * Decisões técnicas por analista no período.
     *
     * @return list<array<string, mixed>>
     */
    public function produtividade(BiConsulta $c): array
    {
        $nominal = (bool) config('viabiliza.integrations.bi.nominal');

        return $this->decisoes($c->filtros)
            ->where('viability_decisions.flow', 'analise_tecnica')
            ->whereNotNull('viability_decisions.decided_by_user_id')
            ->join('users', 'users.id', '=', 'viability_decisions.decided_by_user_id')
            ->groupBy('viability_decisions.decided_by_user_id', 'users.name')
            ->orderByDesc('decididas')
            ->orderBy('viability_decisions.decided_by_user_id')
            ->toBase()
            ->get([
                'viability_decisions.decided_by_user_id as analista_id',
                'users.name as analista_nome',
                DB::raw('count(*) as decididas'),
                DB::raw("sum(case when viability_decisions.outcome = '".DecisionOutcome::Deferida->value."' then 1 else 0 end) as deferidas"),
                DB::raw("sum(case when viability_decisions.outcome = '".DecisionOutcome::Indeferida->value."' then 1 else 0 end) as indeferidas"),
                DB::raw('avg(viability_requests.viabilidade_horas_uteis) as tempo_medio'),
            ])
            ->map(fn ($linha): array => [
                'analista_id' => (int) $linha->analista_id,
                ...($nominal ? ['analista' => (string) $linha->analista_nome] : []),
                'decididas' => (int) $linha->decididas,
                'deferidas' => (int) $linha->deferidas,
                'indeferidas' => (int) $linha->indeferidas,
                'tempo_medio_horas_uteis' => $linha->tempo_medio !== null ? round((float) $linha->tempo_medio, 2) : null,
            ])
            ->all();
    }

    /**
     * Demora do requerente (convites respondidos) × demora administrativa
     * (fila e análise).
     *
     * @return array<string, mixed>
     */
    public function tempoRespostaRequerente(BiConsulta $c): array
    {
        $convites = AnalysisPendency::query()
            ->join('viability_requests', 'viability_requests.id', '=', 'analysis_pendencies.viability_request_id')
            ->when($c->filtros->from(), fn (Builder $q, $de): Builder => $q->where('analysis_pendencies.created_at', '>=', $de))
            ->when($c->filtros->to(), fn (Builder $q, $ate): Builder => $q->where('analysis_pendencies.created_at', '<=', $ate))
            ->when($c->filtros->setorId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.sector_id', $id))
            ->toBase()
            ->get(['analysis_pendencies.status', 'analysis_pendencies.created_at', 'analysis_pendencies.responded_at']);

        $porStatus = [];
        $horas = [];

        foreach ($convites as $convite) {
            $porStatus[$convite->status] = ($porStatus[$convite->status] ?? 0) + 1;

            if ($convite->responded_at !== null) {
                $horas[] = Carbon::parse((string) $convite->created_at)->diffInMinutes(Carbon::parse((string) $convite->responded_at)) / 60;
            }
        }

        $etapas = collect($this->tempoPorEtapa($c))->keyBy('etapa');

        return [
            'requerente' => [
                'convites' => $convites->count(),
                'por_situacao' => $porStatus,
                'tempo_resposta_horas_corridas' => BiEstatistica::resumo($horas),
            ],
            'administrativo' => [
                'espera_media_horas_uteis' => $etapas->get('espera')['media_horas_uteis'] ?? null,
                'analise_media_horas_uteis' => $etapas->get('analise')['media_horas_uteis'] ?? null,
                'convite_media_horas_uteis' => $etapas->get('pendencia')['media_horas_uteis'] ?? null,
            ],
        ];
    }

    /**
     * Maiores gargalos: onde as horas úteis dos processos decididos se acumulam.
     *
     * @return list<array{posicao: int, faixa: string, rotulo: string, horas: float, percentual: float}>
     */
    public function gargalos(BiConsulta $c): array
    {
        $linhas = $this->gargalos->porPeriodo($c->filtros);

        return array_map(
            fn (array $linha, int $i): array => ['posicao' => $i + 1, ...$linha],
            $linhas,
            array_keys($linhas),
        );
    }
}
