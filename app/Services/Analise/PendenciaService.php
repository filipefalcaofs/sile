<?php

namespace App\Services\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Events\PendenciaSolicitada;
use App\Models\AnalysisPendency;
use App\Models\EmailLog;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\PendenciaSolicitadaNotification;
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
 * dispara o evento gancho PendenciaSolicitada (comunicação plena → EP11) e envia
 * um e-mail SIMPLES e REAL ao requerente.
 *
 * responder(): o requerente responde pelo portal. Em UMA transação grava
 * response/responded_at (respondida) e transiciona em_pendencia→em_analise
 * (reabre a análise), auditando (RN-002).
 *
 * Anti-fachada: o convite via Simplifica/Regin e os canais plenos (multicanal)
 * ficam BLOQUEADOS → EP11/Fase 13. O que existe aqui — estado em_pendencia,
 * portal SILE, e-mail simples — executa de verdade; o evento é o gancho honesto,
 * nunca um "enviado" simulado. A expiração por prazo (HU-147) é gancho do
 * scheduler do EP11 — fora do escopo; o due_at já fica gravado.
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

        // APÓS o commit: o evento gancho (EP11) e o e-mail simples real ao
        // requerente — efeitos colaterais só de uma abertura efetivada.
        PendenciaSolicitada::dispatch($request, $pendency);
        $this->notificarRequerente($request, $pendency);

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
    }

    /**
     * E-mail simples e REAL ao requerente (sem anexo), pela infra de e-mail da
     * casa (EmailLog + ShouldQueue). Degradação HONESTA: sem destinatário com
     * e-mail válido, audita 'sem-destinatario' e não envia (nunca inventa envio).
     */
    private function notificarRequerente(ViabilityRequest $request, AnalysisPendency $pendency): void
    {
        $requester = $request->requester;

        if ($requester === null || blank($requester->email)) {
            $this->audit->log(
                'notificacoes',
                'pendencia-solicitada',
                "Sem destinatário com e-mail para notificar a pendência #{$pendency->id} do protocolo {$request->protocol_number}.",
                properties: [
                    'viability_request_id' => $request->id,
                    'analysis_pendency_id' => $pendency->id,
                    'protocol_number' => $request->protocol_number,
                ],
                subject: $request,
                result: 'sem-destinatario',
            );

            return;
        }

        $log = EmailLog::create([
            'recipient_email' => $requester->email,
            'recipient_name' => $requester->name,
            'notification_class' => PendenciaSolicitadaNotification::class,
            'status' => 'na_fila',
            'queued_at' => now(),
        ]);

        $notification = new PendenciaSolicitadaNotification(
            $request->protocol_number ?? '',
            $pendency->description,
            $request->id,
        );
        $notification->emailLogId = $log->id;

        $requester->notify($notification);
    }
}
