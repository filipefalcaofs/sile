<?php

namespace App\Services\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Events\PendenciaRespondida;
use App\Events\PendenciaSolicitada;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de pendência interno da análise técnica (HU-083/084 — PARCIAL).
 *
 * abrir(): o analista pede complementação ao requerente. Em UMA transação cria a
 * analysis_pendencies (aberta, prazo = analise.pendencia.prazo_resposta_dias),
 * transiciona em_analise→em_pendencia (ViabilityRequestStateMachine, que grava a
 * timeline e audita a transição) e audita a abertura (RN-002). APÓS o commit,
 * dispara o evento gancho PendenciaSolicitada — o listener AUTO-DESCOBERTO
 * NotificarPendencia (EP11) notifica o requerente de forma MULTICANAL. O serviço
 * NÃO notifica direto (anti-duplicação): o aviso é responsabilidade exclusiva do
 * listener.
 *
 * responder(): o requerente responde pelo portal. Em UMA transação grava
 * response/responded_at (respondida) e transiciona em_pendencia→em_analise
 * (reabre a análise), auditando (RN-002). APÓS o commit, dispara o evento gancho
 * PendenciaRespondida — o listener auto-descoberto NotificarRespostaPendencia
 * avisa o analista responsável que a análise reabriu (HU-091/092).
 *
 * Anti-fachada: estado em_pendencia, portal SILE e a comunicação multicanal real
 * executam de verdade; os eventos são ganchos honestos, nunca um "enviado"
 * simulado. A expiração por prazo (HU-147) é gancho do scheduler do EP11 — fora
 * do escopo; o due_at já fica gravado.
 */
class PendenciaService
{
    public function __construct(
        private ViabilityRequestStateMachine $stateMachine,
        private AuditService $audit,
    ) {}

    /**
     * Abre uma pendência e move o processo para em_pendencia. Exige em_analise;
     * caso contrário lança PendenciaInvalidaException sem gravar nada.
     */
    public function abrir(ViabilityRequest $request, User $analista, string $descricao): AnalysisPendency
    {
        if ($request->status !== ViabilityRequestStatus::EmAnalise) {
            throw PendenciaInvalidaException::naoAbrivel($request);
        }

        $pendency = DB::transaction(function () use ($request, $analista, $descricao): AnalysisPendency {
            $prazoDias = (int) Settings::get(
                'analise.pendencia.prazo_resposta_dias',
                config('sile.analise.pendencia.prazo_resposta_dias', 15),
            );

            $pendency = AnalysisPendency::create([
                'viability_request_id' => $request->id,
                'requested_by_user_id' => $analista->id,
                'description' => $descricao,
                'status' => AnalysisPendencyStatus::Aberta,
                'due_at' => now()->addDays($prazoDias),
            ]);

            $this->stateMachine->transition(
                $request,
                ViabilityRequestStatus::EmPendencia,
                actor: $analista,
                reason: 'Pendência aberta pela análise técnica',
                publicLabel: ViabilityRequestStatus::EmPendencia->publicLabel(),
            );

            $this->audit->log(
                'analise',
                'pendencia-aberta',
                "Pendência #{$pendency->id} aberta na solicitação #{$request->id}.",
                properties: [
                    'viability_request_id' => $request->id,
                    'analysis_pendency_id' => $pendency->id,
                    'protocol_number' => $request->protocol_number,
                    'requested_by_user_id' => $analista->id,
                    'due_at' => $pendency->due_at?->toIso8601String(),
                ],
                subject: $request,
            );

            return $pendency;
        });

        // APÓS o commit: o evento gancho (EP11). O listener auto-descoberto
        // NotificarPendencia notifica o requerente (multicanal) — o serviço NÃO
        // notifica direto (anti-duplicação). Efeito só de uma abertura efetivada.
        PendenciaSolicitada::dispatch($request, $pendency);

        return $pendency;
    }

    /**
     * Registra a resposta do requerente e reabre a análise (em_pendencia→
     * em_analise). Exige a pendência aberta e o processo em em_pendencia; caso
     * contrário lança PendenciaInvalidaException sem gravar nada.
     */
    public function responder(AnalysisPendency $pendency, string $resposta): void
    {
        $request = $pendency->viabilityRequest;

        if ($pendency->status !== AnalysisPendencyStatus::Aberta
            || $request->status !== ViabilityRequestStatus::EmPendencia) {
            throw PendenciaInvalidaException::naoRespondivel($pendency);
        }

        DB::transaction(function () use ($pendency, $request, $resposta): void {
            $pendency->update([
                'response' => $resposta,
                'responded_at' => now(),
                'status' => AnalysisPendencyStatus::Respondida,
            ]);

            $this->stateMachine->transition(
                $request,
                ViabilityRequestStatus::EmAnalise,
                actor: auth()->user(),
                reason: 'Pendência respondida pelo requerente',
                publicLabel: ViabilityRequestStatus::EmAnalise->publicLabel(),
            );

            $this->audit->log(
                'analise',
                'pendencia-respondida',
                "Pendência #{$pendency->id} respondida na solicitação #{$request->id} — análise reaberta.",
                properties: [
                    'viability_request_id' => $request->id,
                    'analysis_pendency_id' => $pendency->id,
                    'protocol_number' => $request->protocol_number,
                ],
                subject: $request,
            );
        });

        // APÓS o commit: o evento gancho (EP11). O listener auto-descoberto
        // NotificarRespostaPendencia avisa o analista responsável que a análise
        // reabriu. Efeito só de uma resposta efetivada.
        PendenciaRespondida::dispatch($request, $pendency);
    }
}
