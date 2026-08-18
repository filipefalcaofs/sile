<?php

namespace App\Services\Relatorios;

use App\Models\ViabilityRequest;
use App\Services\Expresso\BusinessDeadlineCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Relatório SAPS "Tempo de Emissão de TVL" (Tela R2) no modo TABELA: cada linha é
 * um processo DECIDIDO no recorte, com o tempo entre a Abertura (created_at) e a
 * Emissão (decided_at). Route-free e sem estado — recebe um {@see ReportFilters}
 * e devolve o paginator (tela) ou o Builder (export).
 *
 * O período recorta pela EMISSÃO (viability_decisions.decided_at) — "TVL emitido
 * no recorte" (CA-R2-01). Os demais filtros: `servico` (service_type_id),
 * `resultado` (deferida/indeferida — {@see \App\Enums\DecisionOutcome}) e `cnae`
 * (código do CNAE da solicitação — restringe o builder, CA-R2-02).
 *
 * A duração Emissão−Abertura é medida em MINUTOS ÚTEIS pelo MESMO cálculo do
 * {@see TempoAnaliseService} ({@see BusinessDeadlineCalculator::businessDurationBetween}
 * — desconta fins de semana e feriados ativos). Degradação HONESTA: os blocos DAM
 * não são modelados no SILE (vêm em branco); a Revisão via REDESIM não é
 * homologada — o `tipo` é sempre "Viabilidade" e o modo `tipo=revisao` NUNCA
 * simula dados (devolve conjunto vazio, CA-R2-05).
 */
class RelatorioTempoEmissaoTvlService
{
    /** Rótulo único do tipo — a Revisão via REDESIM ainda não é homologada. */
    public const TIPO_VIABILIDADE = 'Viabilidade';

    /** Resultados aceitos no filtro (value do {@see \App\Enums\DecisionOutcome}). */
    private const RESULTADOS = ['deferida', 'indeferida'];

    public function __construct(private readonly BusinessDeadlineCalculator $calculator) {}

    /**
     * Linhas paginadas do recorte (para a tela). Cada item é a solicitação com a
     * decisão/serviço eager-carregados para a projeção por {@see linha()}.
     *
     * @return LengthAwarePaginator<int, ViabilityRequest>
     */
    public function consultar(ReportFilters $f, int $perPage = 15): LengthAwarePaginator
    {
        return $this->builder($f)->paginate($perPage);
    }

    /**
     * Builder do MESMO recorte de {@see consultar()} sem paginar — fonte única da
     * consulta e da exportação (RN-005): o
     * {@see Export\Sources\RelatorioTempoEmissaoTvlReportSource} o reusa para
     * streamar o export com o recorte idêntico ao da tela. Junta a decisão para
     * recortar/ordenar pela Emissão; eager-load de serviço/decisão para a projeção.
     *
     * @return Builder<ViabilityRequest>
     */
    public function builder(ReportFilters $f): Builder
    {
        return ViabilityRequest::query()
            ->select('viability_requests.*')
            ->join('viability_decisions as vd', 'vd.viability_request_id', '=', 'viability_requests.id')
            ->with(['serviceType:id,name', 'decision'])
            ->when($f->from(), fn (Builder $q, $from): Builder => $q->where('vd.decided_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, $to): Builder => $q->where('vd.decided_at', '<=', $to))
            ->when($this->servicoId($f), fn (Builder $q, int $id): Builder => $q->where('viability_requests.service_type_id', $id))
            ->when($this->resultado($f), fn (Builder $q, string $r): Builder => $q->where('vd.outcome', $r))
            ->when($f->cnae(), fn (Builder $q, string $cnae): Builder => $q->whereHas('cnaes', fn (Builder $c) => $c->where('cnaes.code', $cnae)))
            // Revisão via REDESIM não homologada: nunca simula dados (CA-R2-05).
            ->when($this->ehRevisao($f), fn (Builder $q): Builder => $q->whereRaw('1 = 0'))
            ->orderByDesc('vd.decided_at')
            ->orderByDesc('viability_requests.id');
    }

    /**
     * Projeção de UMA linha do relatório (tela e export). Campos anuláveis degradam
     * para null (a tela mostra em branco) — nunca um dado inventado. `tipo` é
     * sempre "Viabilidade"; os blocos DAM vêm em branco (não modelados). A duração
     * é null quando falta Abertura/Emissão, senão os minutos ÚTEIS entre elas.
     *
     * @return array{
     *     processo: string|null,
     *     servico: string|null,
     *     tipo: string,
     *     abertura: string|null,
     *     dam_numero: null,
     *     dam_emissao: null,
     *     dam_pagamento: null,
     *     dam_valor: null,
     *     tvl_disponivel: bool,
     *     tvl_numero: string|null,
     *     emissao: string|null,
     *     duracao_minutos: int|null,
     * }
     */
    public function linha(ViabilityRequest $r): array
    {
        $abertura = $r->created_at;
        $emissao = $r->decision?->decided_at;
        $tvl = $r->decision?->tvl_product_number;

        return [
            'processo' => $r->protocol_number,
            'servico' => $r->serviceType?->name,
            'tipo' => self::TIPO_VIABILIDADE,
            'abertura' => $abertura?->toIso8601String(),
            // Blocos DAM não modelados no SILE — sempre em branco (honesto).
            'dam_numero' => null,
            'dam_emissao' => null,
            'dam_pagamento' => null,
            'dam_valor' => null,
            'tvl_disponivel' => $tvl !== null,
            'tvl_numero' => $tvl,
            'emissao' => $emissao?->toIso8601String(),
            'duracao_minutos' => ($abertura !== null && $emissao !== null)
                ? $this->calculator->businessDurationBetween($abertura, $emissao)
                : null,
        ];
    }

    /**
     * service_type_id do filtro `servico` (inteiro), ou null.
     */
    private function servicoId(ReportFilters $f): ?int
    {
        $valor = $f->get('servico');
        $valor = $valor === null ? '' : trim((string) $valor);

        return $valor !== '' && ctype_digit($valor) ? (int) $valor : null;
    }

    /**
     * Resultado do filtro validado contra o whitelist (deferida/indeferida); fora
     * dele degrada para null (sem inventar recorte).
     */
    private function resultado(ReportFilters $f): ?string
    {
        $valor = $f->get('resultado');
        $valor = $valor === null ? '' : trim((string) $valor);

        return in_array($valor, self::RESULTADOS, true) ? $valor : null;
    }

    /**
     * Modo Revisão (tipo=revisao) — a integração REDESIM não é homologada, então o
     * builder devolve conjunto vazio (nunca simula, CA-R2-05).
     */
    private function ehRevisao(ReportFilters $f): bool
    {
        $valor = $f->get('tipo');

        return $valor !== null && trim((string) $valor) === 'revisao';
    }
}
