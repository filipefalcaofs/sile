<?php

namespace App\Services\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Services\Relatorios\Export\Sources\EscritorioVirtualReportSource;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

/**
 * Tempo POR ETAPA da timeline (HU-129) + os relatórios SAPS de tempo (RN-006),
 * medidos em tempo ÚTIL via {@see BusinessDeadlineCalculator::businessDurationBetween}
 * (15-04 — desconta fins de semana e feriados ativos). É a correção da distorção
 * do legado, que reporta tempo de CALENDÁRIO e mistura etapas (19 dias onde foram
 * 42h reais): aqui cada etapa é ISOLADA a partir de viability_request_transitions
 * e o tempo de espera que NÃO é trabalho da SEDUR (encaminhamento, Junta/BAP) fica
 * visível.
 *
 * Route-free e sem estado: recebe um {@see ReportFilters} e devolve arrays prontos
 * para a UI/export. Degradação HONESTA (entrega-funcional, sem fachada): sem
 * transições/decisões no período retorna média NULL e amostras 0 — NUNCA tempo
 * inventado (CA-03).
 *
 * Por que o pareamento das transições é em PHP e não em SQL: o desconto de tempo
 * útil (dia a dia, descontando fim de semana/feriado) NÃO tem expressão portável
 * entre SQLite e PostgreSQL (15-RESEARCH Open Q #3). A varredura é ESCOPADA por
 * período em SQL e processada em cursor() (memória baixa); só o cálculo da duração
 * útil de cada par roda em PHP — não é "loop para contar o que o SQL faria".
 */
class TempoAnaliseService
{
    /**
     * Etapa medida pelo status DEIXADO em cada transição (o intervalo é o tempo que
     * o processo passou NAQUELE status até a transição). A espera agrega
     * `protocolada` (encaminhamento) e `aguardando_bap` (Junta) — tempo de espera
     * que não é trabalho da SEDUR e que o legado escondia. value do
     * {@see ViabilityRequestStatus} => etapa.
     *
     * @var array<string, string>
     */
    private const ETAPA_POR_STATUS = [
        'rascunho' => 'preenchimento',
        'protocolada' => 'espera',
        'aguardando_bap' => 'espera',
        'em_analise' => 'analise',
        'em_pendencia' => 'pendencia',
    ];

    /** Ordem canônica das etapas no relatório. */
    private const ETAPAS = ['preenchimento', 'espera', 'analise', 'pendencia'];

    public function __construct(private readonly BusinessDeadlineCalculator $calculator) {}

    /**
     * Tempo médio por etapa (HU-129) em MINUTOS ÚTEIS sobre os processos
     * protocolados no período. Varre as transições ordenadas por processo e
     * instante (cursor), pareia as consecutivas e soma a duração útil de cada
     * etapa por processo; a média/amostras é por processo que PASSOU pela etapa.
     * Reentradas (em_analise após pendência) somam na mesma etapa, isolando a
     * pendência do tempo de análise. Sem amostras ⇒ media_minutos NULL (CA-03).
     *
     * @return array{etapas: list<array{etapa: string, media_minutos: int|null, amostras: int}>}
     */
    public function tempoPorEtapa(ReportFilters $f): array
    {
        $soma = array_fill_keys(self::ETAPAS, 0);
        $amostras = array_fill_keys(self::ETAPAS, 0);

        $requestId = null;
        $criadoEm = null;
        $transicoes = [];

        $consolidar = function () use (&$transicoes, &$criadoEm, &$soma, &$amostras): void {
            if ($criadoEm === null) {
                return;
            }

            foreach ($this->minutosPorEtapa($criadoEm, $transicoes) as $etapa => $minutos) {
                $soma[$etapa] += $minutos;
                $amostras[$etapa]++;
            }
        };

        foreach ($this->transicoesNoPeriodo($f) as $linha) {
            $id = (int) $linha->viability_request_id;

            if ($id !== $requestId) {
                $consolidar();
                $requestId = $id;
                $criadoEm = Carbon::parse((string) $linha->request_created_at);
                $transicoes = [];
            }

            $transicoes[] = [
                'from' => $linha->from_status?->value,
                'at' => $linha->created_at,
            ];
        }

        $consolidar();

        return [
            'etapas' => array_map(fn (string $etapa): array => [
                'etapa' => $etapa,
                'media_minutos' => $amostras[$etapa] > 0 ? (int) round($soma[$etapa] / $amostras[$etapa]) : null,
                'amostras' => $amostras[$etapa],
            ], self::ETAPAS),
        ];
    }

    /**
     * Tempo médio de EMISSÃO do TVL (SAPS, RN-006) em MINUTOS ÚTEIS:
     * protocoled_at → decided_at das decisões COM número de produto TVL decididas
     * no período. Sem registros ⇒ media_minutos NULL (honesto, jamais 0 disfarçado
     * de real, CA-03).
     *
     * @return array{media_minutos: int|null, amostras: int}
     */
    public function tempoEmissaoTvl(ReportFilters $f): array
    {
        $soma = 0;
        $amostras = 0;

        $rows = ViabilityDecision::query()
            ->join('viability_requests as vr', 'vr.id', '=', 'viability_decisions.viability_request_id')
            ->whereNotNull('viability_decisions.tvl_product_number')
            ->whereNotNull('vr.protocoled_at')
            ->when($f->from(), fn (Builder $q, $from): Builder => $q->where('viability_decisions.decided_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, $to): Builder => $q->where('viability_decisions.decided_at', '<=', $to))
            ->when($f->setorId(), fn (Builder $q, int $id): Builder => $q->where('vr.sector_id', $id))
            ->when($f->analistaId(), fn (Builder $q, int $id): Builder => $q->where('vr.assigned_user_id', $id))
            ->select('viability_decisions.decided_at', 'vr.protocoled_at as protocoled_at')
            ->cursor();

        foreach ($rows as $row) {
            $soma += $this->calculator->businessDurationBetween(
                Carbon::parse((string) $row->protocoled_at),
                Carbon::instance($row->decided_at),
            );
            $amostras++;
        }

        return [
            'media_minutos' => $amostras > 0 ? (int) round($soma / $amostras) : null,
            'amostras' => $amostras,
        ];
    }

    /**
     * Builder das SEDES de escritório virtual (SAPS, RN-006): recorte
     * is_virtual_office=true + filtros comuns por when(). Retornar o Builder
     * permite o {@see EscritorioVirtualReportSource}
     * reusar EXATAMENTE o mesmo recorte (RN-005). Eager-load de company/decisão
     * para o mapRow do export (empresa/CNPJ/quando/resultado).
     *
     * @return Builder<ViabilityRequest>
     */
    public function sedesEscritorioVirtual(ReportFilters $f): Builder
    {
        return ViabilityRequest::query()
            ->with(['company', 'sector:id,name', 'assignedTo:id,name', 'decision'])
            ->where('viability_requests.is_virtual_office', true)
            ->when($f->from(), fn (Builder $q, $from): Builder => $q->where('viability_requests.protocoled_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, $to): Builder => $q->where('viability_requests.protocoled_at', '<=', $to))
            ->when($f->setorId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.sector_id', $id))
            ->when($f->analistaId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.assigned_user_id', $id))
            ->when($f->bairro(), fn (Builder $q, string $b): Builder => $q->whereLike('viability_requests.address_neighborhood', "%{$b}%", caseSensitive: false))
            ->orderByDesc('viability_requests.id');
    }

    /**
     * Minutos ÚTEIS por etapa de UM processo, a partir do seu created_at e das
     * transições ordenadas (asc por instante). Cada par (instante anterior →
     * instante da transição) é o tempo no status DEIXADO (from_status), convertido
     * em tempo útil pelo calculator. Só as etapas que OCORRERAM aparecem no retorno
     * (base honesta das amostras); reentradas acumulam na mesma etapa. Helper puro
     * reusado pela agregação e pelo detalhamento por processo do export.
     *
     * @param  list<array{from: string|null, at: DateTimeInterface}>  $transicoes
     * @return array<string, int>
     */
    public function minutosPorEtapa(DateTimeInterface $criadoEm, array $transicoes): array
    {
        $minutos = [];
        $anterior = Carbon::instance($criadoEm);

        foreach ($transicoes as $transicao) {
            $instante = Carbon::instance($transicao['at']);
            $etapa = self::ETAPA_POR_STATUS[$transicao['from']] ?? null;

            if ($etapa !== null) {
                $minutos[$etapa] = ($minutos[$etapa] ?? 0)
                    + $this->calculator->businessDurationBetween($anterior, $instante);
            }

            $anterior = $instante;
        }

        return $minutos;
    }

    /**
     * Minutos úteis por etapa de UM processo já carregado (com `transitions`),
     * para o detalhamento por processo do export. Ordena as transições asc (a
     * relação vem `latest()`) e delega ao helper puro {@see minutosPorEtapa},
     * reusando o MESMO pareamento da agregação.
     *
     * @return array<string, int>
     */
    public function minutosPorEtapaDoProcesso(ViabilityRequest $request): array
    {
        $transicoes = $request->transitions
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->map(fn (ViabilityRequestTransition $t): array => [
                'from' => $t->from_status?->value,
                'at' => $t->created_at,
            ])
            ->values()
            ->all();

        return $this->minutosPorEtapa($request->created_at, $transicoes);
    }

    /**
     * Varredura escopada por período (protocoled_at) das transições dos processos
     * protocolados, ordenada por processo e instante para o pareamento consecutivo
     * em PHP; cursor() mantém a memória baixa. `request_created_at` (alias) é o
     * marco inicial do preenchimento (rascunho → protocolada).
     *
     * @return LazyCollection<int, ViabilityRequestTransition>
     */
    private function transicoesNoPeriodo(ReportFilters $f): LazyCollection
    {
        return ViabilityRequestTransition::query()
            ->join('viability_requests as vr', 'vr.id', '=', 'viability_request_transitions.viability_request_id')
            ->whereNotNull('vr.protocoled_at')
            ->when($f->from(), fn (Builder $q, $from): Builder => $q->where('vr.protocoled_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, $to): Builder => $q->where('vr.protocoled_at', '<=', $to))
            ->when($f->setorId(), fn (Builder $q, int $id): Builder => $q->where('vr.sector_id', $id))
            ->when($f->analistaId(), fn (Builder $q, int $id): Builder => $q->where('vr.assigned_user_id', $id))
            ->orderBy('vr.id')
            ->orderBy('viability_request_transitions.created_at')
            ->orderBy('viability_request_transitions.id')
            ->select('viability_request_transitions.*', 'vr.created_at as request_created_at')
            ->cursor();
    }
}
