<?php

namespace App\Services\Analise;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisPendency;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Indeferimento por PRAZO DO CONVITE expirado (relatório SEDUR 2026-07-09,
 * Fase 2a). Quando um convite (pendência) vence sem resposta no prazo de 48h
 * úteis, o processo é INDEFERIDO automaticamente — o requerente não prestou o
 * esclarecimento solicitado. Espelha o IndeferirSemBapService: decisão imutável
 * (ViabilityDecision) + transição pela StateMachine + auditoria SÍNCRONA +
 * ResultadoEmitido após o commit.
 *
 * Honestidade do registro: o indeferimento é PROCEDIMENTAL (faltou resposta ao
 * convite no prazo), NÃO um veredito locacional. consolidated_result é o
 * marcador 'convite_expirado' e per_cnae/rules_versions ficam vazios — não há
 * reavaliação dos motores; o motivo legível está no reason (RN-002).
 *
 * Arquivo virtual: indeferida ∉ STATUS_FILA, então o processo sai naturalmente
 * das caixas do setor e do analista ao ser indeferido (ProcessoQuery["STATUS_FILA"]).
 *
 * OBS (spec 2 — fora da Fase 2a): a transmissão do INDEFERIMENTO à SEFAZ via API
 * é tratada no recurso de desfechos/produto; aqui reusa-se o mesmo caminho de
 * emissão existente (ResultadoEmitido), em que a SEFAZ hoje ignora indeferimento.
 */
class IndeferirPorPrazoConviteService
{
    public function __construct(
        private ViabilityRequestStateMachine $stateMachine,
        private AuditService $audit,
    ) {}

    /**
     * Indefere uma solicitação parada em em_pendencia (convite vencido sem
     * resposta). Guarda de estado: só age sobre em_pendencia — chamar sobre
     * outro estado é erro de programação, então lança InvalidArgumentException
     * sem criar nada.
     */
    public function indeferir(ViabilityRequest $request, AnalysisPendency $pendency): ViabilityDecision
    {
        if ($request->status !== ViabilityRequestStatus::EmPendencia) {
            throw new InvalidArgumentException(
                "Indeferimento por prazo de convite exige status em_pendencia; a solicitação #{$request->id} está em {$request->status->value}.",
            );
        }

        $reason = 'Indeferido por prazo do convite expirado sem resposta do requerente';

        $decision = DB::transaction(function () use ($request, $pendency, $reason): ViabilityDecision {
            $decision = ViabilityDecision::create([
                'viability_request_id' => $request->id,
                'flow' => 'analise_tecnica',
                'outcome' => DecisionOutcome::Indeferida,
                'consolidated_result' => 'convite_expirado',
                'tvl_product_number' => null,
                'per_cnae' => [],
                'rules_versions' => [],
                'fundamentacao' => [],
                'reason' => $reason,
                'decided_by_user_id' => null,
                'decided_at' => now(),
            ]);

            $this->stateMachine->transition(
                $request,
                ViabilityRequestStatus::Indeferida,
                null,
                reason: $reason,
                publicLabel: ViabilityRequestStatus::Indeferida->publicLabel(),
            );

            $this->audit->log(
                logName: 'analise',
                event: 'convite-expirado-indeferido',
                description: "Indeferimento por prazo do convite expirado da solicitação #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'analysis_pendency_id' => $pendency->id,
                    'protocol_number' => $request->protocol_number,
                    'outcome' => DecisionOutcome::Indeferida->value,
                    'consolidado' => 'convite_expirado',
                    'motivo' => $reason,
                    'due_at' => $pendency->due_at?->toIso8601String(),
                ],
                subject: $request,
                result: 'indeferida',
            );

            return $decision;
        });

        // APÓS o commit: reusa o caminho de emissão (Regin comunica; SEFAZ hoje
        // ignora indeferimento — spec 2 tratará o envio à SEFAZ).
        ResultadoEmitido::dispatch($request, $decision);

        return $decision;
    }
}
