<?php

namespace App\Services\Relatorios;

use App\Enums\ViabilityRequestOrigin;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Relatório gerencial de atendimento em contingência (HU-148): responde "quanto
 * do volume entra pelo canal de operador e por quê?" — indicador de GOVERNANÇA
 * enquanto o canal oficial (Regin) não volta: uma participação crescente sinaliza
 * que a integração segue indisponível. Recorte pelo protocolo no período; a
 * participação é null sem base (honesto, nunca 0% fabricado) e o motivo null
 * (campo texto livre) é rotulado 'não informado' — nunca somado a motivo real.
 * Agregação em SQL, route-free e sem estado, como os demais serviços do EP15.
 */
class ContingenciaRelatorioService
{
    /**
     * Resumo: volume da contingência, volume total protocolado e participação
     * (%) no período + ranking de motivos (group by em SQL).
     *
     * @return array{contingencia: int, protocoladas: int, participacao: float|null, por_motivo: list<array{motivo: string, total: int}>}
     */
    public function resumo(ReportFilters $f): array
    {
        $contingencia = (clone $this->builderSemOrdem($f))->count();
        $protocoladas = $this->protocoladasBase($f)->count();

        return [
            'contingencia' => $contingencia,
            'protocoladas' => $protocoladas,
            'participacao' => $protocoladas > 0 ? round($contingencia / $protocoladas * 100, 1) : null,
            'por_motivo' => $this->rankingMotivos($f),
        ];
    }

    /**
     * Builder das solicitações de contingência no período, mais recentes
     * primeiro, com o operador eager-loaded — base da tela paginada e do
     * export (RN-005: o MESMO recorte nos dois caminhos).
     *
     * @return Builder<ViabilityRequest>
     */
    public function builder(ReportFilters $f): Builder
    {
        return $this->builderSemOrdem($f)
            ->with('createdBy')
            ->orderByDesc('protocoled_at');
    }

    /**
     * Projeção da linha (tela + export). Motivo null degrada para null — a
     * tela/export mostram travessão, nunca um motivo inventado.
     *
     * @return array<string, mixed>
     */
    public function linha(ViabilityRequest $r): array
    {
        return [
            'id' => $r->id,
            'processo' => $r->protocol_number,
            'motivo' => $r->contingency_reason,
            'operador' => $r->createdBy?->name,
            'protocolado_em' => $r->protocoled_at?->toIso8601String(),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
        ];
    }

    /**
     * Ranking de motivos da contingência (group by em SQL): o motivo null é
     * rotulado 'não informado' — honesto, nunca somado a um motivo real.
     *
     * @return list<array{motivo: string, total: int}>
     */
    private function rankingMotivos(ReportFilters $f): array
    {
        return $this->builderSemOrdem($f)
            ->groupBy('contingency_reason')
            ->orderByDesc('total')
            ->orderBy('contingency_reason')
            ->get(['contingency_reason', DB::raw('count(*) as total')])
            ->map(fn ($linha): array => [
                'motivo' => $linha->contingency_reason ?? 'não informado',
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    /**
     * Base SEM ordenação nem eager-load (o resumo só conta): origin=contingencia
     * protocolada no período (protocoled_at).
     *
     * @return Builder<ViabilityRequest>
     */
    private function builderSemOrdem(ReportFilters $f): Builder
    {
        return $this->protocoladasBase($f)
            ->where('origin', ViabilityRequestOrigin::Contingencia->value);
    }

    /**
     * Protocoladas no período (qualquer origem) — denominador da participação.
     *
     * @return Builder<ViabilityRequest>
     */
    private function protocoladasBase(ReportFilters $f): Builder
    {
        return ViabilityRequest::query()
            ->whereNotNull('protocoled_at')
            ->when($f->from(), fn (Builder $q, Carbon $from): Builder => $q->where('protocoled_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, Carbon $to): Builder => $q->where('protocoled_at', '<=', $to));
    }
}
