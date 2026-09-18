<?php

namespace App\Services\Relatorios;

use App\Enums\AnalysisPendencyStatus;
use App\Models\AnalysisPendency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Relatório operacional de pendências/exigências: responde "quantas exigências
 * abertas, quantas vencidas, qual o tempo médio de resposta do requerente e
 * quantas expiraram?" sobre as analysis_pendencies REAIS (HU-083/084). O
 * resumo agrega em SQL (nunca loop PHP sobre coleção carregada); o tempo médio
 * de resposta é em minutos CORRIDOS (o prazo é do cidadão, não da SEDUR) via
 * cursor — null sem amostras (honesto, nunca um tempo inventado). Route-free
 * e sem estado, como os demais serviços de relatório do EP15.
 */
class PendenciasRelatorioService
{
    /**
     * Resumo: abertas e vencidas AGORA (estado corrente) + respondidas e
     * expiradas no PERÍODO do filtro (evento). Contagens zeradas são fato; o
     * tempo médio é null sem respondidas no recorte (degradação honesta).
     *
     * @return array{abertas: int, vencidas: int, respondidas: int, expiradas: int, tempo_medio_resposta_minutos: int|null}
     */
    public function resumo(ReportFilters $f): array
    {
        $agora = Carbon::now();

        return [
            'abertas' => (clone $this->builderSemOrdem($f))
                ->where('status', AnalysisPendencyStatus::Aberta->value)
                ->count(),
            'vencidas' => (clone $this->builderSemOrdem($f))
                ->where('status', AnalysisPendencyStatus::Aberta->value)
                ->where('due_at', '<', $agora)
                ->count(),
            'respondidas' => (clone $this->builderSemOrdem($f))
                ->where('status', AnalysisPendencyStatus::Respondida->value)
                ->count(),
            'expiradas' => (clone $this->builderSemOrdem($f))
                ->where('status', AnalysisPendencyStatus::Expirada->value)
                ->count(),
            'tempo_medio_resposta_minutos' => $this->tempoMedioResposta($f),
        ];
    }

    /**
     * Builder das pendências do recorte (período pela ABERTURA + status quando
     * filtrado), com o protocolo da solicitação e o analista eager-loaded —
     * base da tela paginada e do export (RN-005: o MESMO recorte nos dois).
     * Abertas primeiro (pelo prazo), depois as demais pela abertura recente.
     *
     * @return Builder<AnalysisPendency>
     */
    public function builder(ReportFilters $f): Builder
    {
        return $this->builderSemOrdem($f)
            ->with(['viabilityRequest', 'requestedBy'])
            ->orderByRaw("CASE WHEN analysis_pendencies.status = 'aberta' THEN 0 ELSE 1 END")
            ->orderBy('analysis_pendencies.due_at')
            ->orderByDesc('analysis_pendencies.created_at');
    }

    /**
     * Projeção da linha (tela + export). Campos anuláveis degradam para null —
     * a tela/export mostram travessão, nunca um dado inventado.
     *
     * @return array<string, mixed>
     */
    public function linha(AnalysisPendency $p): array
    {
        return [
            'id' => $p->id,
            'processo' => $p->viabilityRequest?->protocol_number,
            'descricao' => $p->description,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'analista' => $p->requestedBy?->name,
            'aberta_em' => $p->created_at?->toIso8601String(),
            'limite_em' => $p->due_at?->toIso8601String(),
            'respondida_em' => $p->responded_at?->toIso8601String(),
            'tempo_resposta_minutos' => $p->responded_at !== null && $p->created_at !== null
                ? (int) $p->created_at->diffInMinutes($p->responded_at)
                : null,
        ];
    }

    /**
     * Tempo médio de resposta (minutos corridos) das pendências RESPONDIDAS no
     * recorte, via cursor (sem carregar a coleção). null sem amostras.
     */
    private function tempoMedioResposta(ReportFilters $f): ?int
    {
        $total = 0;
        $amostras = 0;

        foreach ($this->builderSemOrdem($f)->where('status', AnalysisPendencyStatus::Respondida->value)->whereNotNull('responded_at')->cursor() as $p) {
            $total += (int) $p->created_at->diffInMinutes($p->responded_at);
            $amostras++;
        }

        return $amostras > 0 ? (int) round($total / $amostras) : null;
    }

    /**
     * Base SEM ordenação nem eager-load (o resumo só conta): período pela
     * abertura (created_at) + filtro opcional de status.
     *
     * @return Builder<AnalysisPendency>
     */
    private function builderSemOrdem(ReportFilters $f): Builder
    {
        $status = $f->get('status_pendencia');
        $statusValido = is_string($status) && AnalysisPendencyStatus::tryFrom($status) !== null;

        return AnalysisPendency::query()
            ->when($f->from(), fn (Builder $q, Carbon $from): Builder => $q->where('created_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, Carbon $to): Builder => $q->where('created_at', '<=', $to))
            ->when($statusValido, fn (Builder $q): Builder => $q->where('status', $status));
    }
}
