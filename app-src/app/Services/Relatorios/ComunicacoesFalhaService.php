<?php

namespace App\Services\Relatorios;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Models\Communication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consulta operacional de falhas de comunicação (HU-096): responde "quais
 * notificações falharam ou foram bloqueadas, de quais processos?" sobre o
 * ledger REAL de communications — a pendência operacional fica visível ao
 * gestor (nunca falha silenciosa). Recorte por período (created_at) e canal;
 * SEM dados do destinatário (LGPD — o cidadão não aparece na listagem).
 * Agregação em SQL, route-free e sem estado, como os demais serviços do EP15.
 */
class ComunicacoesFalhaService
{
    /** Status que representam NÃO entrega (falha real ou bloqueio auditado). */
    private const STATUS_FALHA = [
        CommunicationStatus::Falhou->value,
        CommunicationStatus::Bloqueado->value,
    ];

    /**
     * Resumo: falharam / bloqueadas no recorte + quebra por canal (group by em
     * SQL). Contagens zeradas são fato.
     *
     * @return array{falharam: int, bloqueadas: int, por_canal: list<array{canal: string, canal_label: string, total: int}>}
     */
    public function resumo(ReportFilters $f): array
    {
        return [
            'falharam' => (clone $this->builderSemOrdem($f))
                ->where('status', CommunicationStatus::Falhou->value)
                ->count(),
            'bloqueadas' => (clone $this->builderSemOrdem($f))
                ->where('status', CommunicationStatus::Bloqueado->value)
                ->count(),
            'por_canal' => $this->porCanal($f),
        ];
    }

    /**
     * Builder das comunicações não entregues no recorte, mais recentes
     * primeiro, com o processo eager-loaded — base da tela paginada e do
     * export (RN-005: o MESMO recorte nos dois caminhos).
     *
     * @return Builder<Communication>
     */
    public function builder(ReportFilters $f): Builder
    {
        return $this->builderSemOrdem($f)
            ->with('viabilityRequest')
            ->orderByDesc('created_at');
    }

    /**
     * Projeção da linha (tela + export) — SEM destinatário (LGPD). Campos
     * anuláveis degradam para null, nunca um dado inventado.
     *
     * @return array<string, mixed>
     */
    public function linha(Communication $c): array
    {
        return [
            'id' => $c->id,
            'processo' => $c->viabilityRequest?->protocol_number,
            'canal' => $c->channel->value,
            'canal_label' => $c->channel->label(),
            'tipo_label' => $c->type->label(),
            'titulo' => $c->title,
            'erro' => $c->error_message,
            'status' => $c->status->value,
            'status_label' => $c->status->label(),
            'em' => ($c->failed_at ?? $c->created_at)?->toIso8601String(),
        ];
    }

    /**
     * Quebra por canal das não entregues (group by em SQL), com o rótulo do
     * enum — canal desconhecido fica com o código cru (honesto).
     *
     * @return list<array{canal: string, canal_label: string, total: int}>
     */
    private function porCanal(ReportFilters $f): array
    {
        return $this->builderSemOrdem($f)
            ->groupBy('channel')
            ->orderBy('channel')
            ->get(['channel', DB::raw('count(*) as total')])
            ->map(function ($linha): array {
                // O cast do model devolve o enum mesmo no agregado do groupBy.
                $canal = $linha->channel instanceof CommunicationChannel ? $linha->channel->value : (string) $linha->channel;

                return [
                    'canal' => $canal,
                    'canal_label' => CommunicationChannel::tryFrom($canal)?->label() ?? $canal,
                    'total' => (int) $linha->total,
                ];
            })
            ->all();
    }

    /**
     * Base SEM ordenação nem eager-load (o resumo só conta): não entregues no
     * período (created_at) + filtro opcional de canal.
     *
     * @return Builder<Communication>
     */
    private function builderSemOrdem(ReportFilters $f): Builder
    {
        $canal = $f->get('canal');
        $canalValido = is_string($canal) && CommunicationChannel::tryFrom($canal) !== null;

        return Communication::query()
            ->whereIn('status', self::STATUS_FALHA)
            ->when($f->from(), fn (Builder $q, Carbon $from): Builder => $q->where('created_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, Carbon $to): Builder => $q->where('created_at', '<=', $to))
            ->when($canalValido, fn (Builder $q): Builder => $q->where('channel', $canal));
    }
}
