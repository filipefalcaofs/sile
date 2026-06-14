<?php

namespace App\Services\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Support\Settings;

/**
 * Timeline simplificada da solicitação (HU-069 RN-004/006) montada a partir das
 * viability_request_transitions REAIS — a fonte de verdade do andamento
 * (anti-fachada: nada é inventado). No modo PÚBLICO (link assinado, LGPD) cada
 * etapa expõe SÓ a data + o rótulo amigável (publicLabel); o motivo interno, o
 * ator e o rótulo técnico ficam exclusivamente no modo autenticado, junto das
 * pendências do requerente. O prazo estimado é parametrizado
 * (solicitacao.prazo_estimado_dias) COM ressalva honesta — a medição real por
 * etapa é da HU-129/Fase 15.
 */
class TimelineSolicitacao
{
    /**
     * Estados terminais: o processo já se encerrou, não há prazo em curso.
     *
     * @var list<ViabilityRequestStatus>
     */
    private const array TERMINAIS = [
        ViabilityRequestStatus::Cancelada,
        ViabilityRequestStatus::Deferida,
        ViabilityRequestStatus::Indeferida,
    ];

    /**
     * @return array{
     *     status_atual: array<string, string>,
     *     etapas: list<array<string, mixed>>,
     *     pendencias: list<string>,
     *     prazo_estimado: array{dias: int, ressalva: string}|null
     * }
     */
    public function build(ViabilityRequest $request, bool $publico = false): array
    {
        $status = $request->status;

        $etapas = $request->transitions()
            ->reorder('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ViabilityRequestTransition $transition) => $this->etapa($transition, $publico))
            ->all();

        $statusAtual = [
            'value' => $status->value,
            'public_label' => $status->publicLabel(),
        ];

        // O rótulo técnico só aparece para o dono autenticado.
        if (! $publico) {
            $statusAtual['label'] = $status->label();
        }

        return [
            'status_atual' => $statusAtual,
            'etapas' => $etapas,
            // Pendências do requerente só no modo autenticado (RN-006): o link
            // público é informativo e não orienta ação privada.
            'pendencias' => $publico ? [] : $this->pendencias($status),
            'prazo_estimado' => $this->prazoEstimado($status),
        ];
    }

    /**
     * Uma etapa concluída a partir de uma transição real. O rótulo amigável vem
     * do public_label gravado na transição (fallback: publicLabel do destino).
     *
     * @return array<string, mixed>
     */
    private function etapa(ViabilityRequestTransition $transition, bool $publico): array
    {
        $etapa = [
            'rotulo' => $transition->public_label ?? $transition->to_status->publicLabel(),
            'data' => $transition->created_at?->toIso8601String(),
        ];

        // Detalhe técnico e motivo são exclusivos do modo autenticado — nunca no
        // link público (LGPD).
        if (! $publico) {
            $etapa['status'] = $transition->to_status->value;
            $etapa['status_label'] = $transition->to_status->label();
            $etapa['motivo'] = $transition->reason;
        }

        return $etapa;
    }

    /**
     * Pendências REAIS do requerente derivadas do status (RN-006) — nunca
     * inventadas: em rascunho falta concluir/protocolar; em pendência há ação
     * aguardando. Nos demais estados (ex.: protocolada) não há nada a fazer.
     *
     * @return list<string>
     */
    private function pendencias(ViabilityRequestStatus $status): array
    {
        return match ($status) {
            ViabilityRequestStatus::Rascunho => ['Conclua o preenchimento e protocole a solicitação.'],
            ViabilityRequestStatus::EmPendencia => ['Há uma pendência aguardando sua ação. Verifique as orientações da análise.'],
            default => [],
        };
    }

    /**
     * Prazo estimado parametrizado (RN-005) COM ressalva honesta — a medição real
     * por etapa (HU-129) ainda não existe (Fase 15). Estados terminais não têm
     * prazo em curso a estimar.
     *
     * @return array{dias: int, ressalva: string}|null
     */
    private function prazoEstimado(ViabilityRequestStatus $status): ?array
    {
        if (in_array($status, self::TERMINAIS, true)) {
            return null;
        }

        return [
            'dias' => (int) Settings::get(
                'solicitacao.prazo_estimado_dias',
                config('sile.solicitacao.prazo_estimado_dias', 30),
            ),
            'ressalva' => 'Prazo estimado e não vinculante. A medição por etapa com base em '
                .'processos equivalentes (HU-129) ainda será implementada; até lá este valor é '
                .'uma estimativa parametrizada.',
        ];
    }
}
