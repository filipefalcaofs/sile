<?php

namespace App\Console\Commands;

use App\Enums\AnalysisPendencyStatus;
use App\Models\AnalysisPendency;
use App\Notifications\PendenciaExpiradaNotification;
use App\Services\Comunicacao\NotificationDispatcher;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * HU-091 RN-005: expira as pendências abertas cujo due_at venceu sem resposta —
 * marca AnalysisPendencyStatus::Expirada (transação + auditoria, RN-002) e
 * notifica o ANALISTA responsável pelo NotificationDispatcher (multicanal).
 *
 * CRÍTICO (anti-fachada): SEM decisão automática — NÃO indefere nem transiciona o
 * processo. O rito de não-resposta (indeferir por prazo) é pendência SEDUR e não
 * é inventado: hoje a rotina expira + notifica + MANTÉM o estado do processo.
 *
 * IDEMPOTÊNCIA (RN-004): a própria transição Aberta→Expirada impede o reprocesso —
 * a 2ª passada não encontra mais a pendência Aberta vencida (espelha o
 * ExpressoIndeferirSemBap, cujo status muda e não reprocessa). Agendado com
 * withoutOverlapping/onOneServer.
 */
class ExpirarPendenciasCommand extends Command
{
    protected $signature = 'pendencias:expirar';

    protected $description = 'HU-091 RN-005: marca as pendências abertas vencidas sem resposta como expiradas e notifica o analista (sem decisão automática)';

    public function handle(NotificationDispatcher $dispatcher, AuditService $audit): int
    {
        $pendencias = AnalysisPendency::query()
            ->where('status', AnalysisPendencyStatus::Aberta)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()) // vencida sem resposta
            ->with(['viabilityRequest.assignedTo', 'requestedBy'])
            ->orderBy('id')
            ->get();

        if ($pendencias->isEmpty()) {
            $this->info('Nenhuma pendência vencida a expirar.');

            return self::SUCCESS;
        }

        foreach ($pendencias as $pendencia) {
            $request = $pendencia->viabilityRequest;

            // Expiração + auditoria em UMA transação (RN-002). O estado do processo
            // permanece intacto — sem decisão automática (rito SEDUR não inventado).
            DB::transaction(function () use ($pendencia, $request, $audit): void {
                $pendencia->update(['status' => AnalysisPendencyStatus::Expirada]);

                $audit->log(
                    'analise',
                    'pendencia-expirada',
                    "Pendência #{$pendencia->id} expirada por prazo sem resposta na solicitação #{$request?->id}.",
                    properties: [
                        'viability_request_id' => $request?->id,
                        'analysis_pendency_id' => $pendencia->id,
                        'protocol_number' => $request?->protocol_number,
                        'due_at' => $pendencia->due_at?->toIso8601String(),
                    ],
                    subject: $request ?? $pendencia,
                    result: 'expirada',
                );
            });

            // Notifica o analista responsável (atual; senão quem abriu a pendência)
            // APÓS o commit — aviso honesto, sem qualquer decisão de mérito.
            $analista = $request?->assignedTo ?? $pendencia->requestedBy;

            if ($analista !== null) {
                $dispatcher->deliver($analista, new PendenciaExpiradaNotification(
                    viabilityRequestId: (int) $pendencia->viability_request_id,
                    protocolNumber: (string) $request?->protocol_number,
                    descricao: $pendencia->description,
                    url: $request !== null ? route('gestao.processos.show', $request->id) : '',
                ));
            }
        }

        $this->info("Expirada(s) {$pendencias->count()} pendência(s) vencida(s) sem resposta.");

        return self::SUCCESS;
    }
}
