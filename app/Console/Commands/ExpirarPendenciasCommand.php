<?php

namespace App\Console\Commands;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Notifications\PendenciaExpiradaNotification;
use App\Services\Analise\IndeferirPorPrazoConviteService;
use App\Services\Comunicacao\NotificationDispatcher;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * pendencias:expirar (relatório SEDUR 2026-07-09, Fase 2a): expira os convites
 * (pendências) abertos cujo due_at venceu sem resposta e INDEFERE o processo
 * automaticamente. Para cada convite vencido: marca AnalysisPendencyStatus::
 * Expirada (transação + auditoria, RN-002); indefere o processo via
 * IndeferirPorPrazoConviteService (decisão imutável + transição em_pendencia→
 * indeferida + ResultadoEmitido) — o processo sai das caixas do setor e do
 * analista (arquivo virtual, pois indeferida ∉ STATUS_FILA); e notifica o
 * analista responsável.
 *
 * Rito de não-resposta (fornecido pela SEDUR): após 48h úteis sem resposta ao
 * convite, indefere. IDEMPOTÊNCIA (RN-004): a própria transição Aberta→Expirada
 * impede o reprocesso — a 2ª passada não encontra mais o convite Aberto vencido.
 * Agendado com withoutOverlapping/onOneServer.
 */
class ExpirarPendenciasCommand extends Command
{
    protected $signature = 'pendencias:expirar';

    protected $description = 'Expira os convites abertos vencidos sem resposta (48h úteis) e indefere o processo automaticamente, notificando o analista';

    public function handle(
        NotificationDispatcher $dispatcher,
        AuditService $audit,
        IndeferirPorPrazoConviteService $indeferidor,
    ): int {
        $pendencias = AnalysisPendency::query()
            ->where('status', AnalysisPendencyStatus::Aberta)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()) // vencida sem resposta
            ->with(['viabilityRequest.assignedTo', 'requestedBy'])
            ->orderBy('id')
            ->get();

        if ($pendencias->isEmpty()) {
            $this->info('Nenhum convite vencido a expirar.');

            return self::SUCCESS;
        }

        foreach ($pendencias as $pendencia) {
            $request = $pendencia->viabilityRequest;

            // Expira o convite + auditoria em UMA transação (RN-002).
            DB::transaction(function () use ($pendencia, $request, $audit): void {
                $pendencia->update(['status' => AnalysisPendencyStatus::Expirada]);

                $audit->log(
                    'analise',
                    'pendencia-expirada',
                    "Convite #{$pendencia->id} expirado por prazo sem resposta na solicitação #{$request?->id}.",
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

            // Indefere o processo automaticamente (convite sem resposta no prazo).
            // O serviço abre a própria transação e dispara ResultadoEmitido após o
            // commit. Guarda de estado: só indefere quando ainda em em_pendencia.
            if ($request !== null && $request->status === ViabilityRequestStatus::EmPendencia) {
                $indeferidor->indeferir($request, $pendencia);
            }

            // Notifica o analista responsável (atual; senão quem abriu) APÓS os efeitos.
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

        $this->info("Expirado(s) {$pendencias->count()} convite(s) vencido(s) sem resposta — processo(s) indeferido(s).");

        return self::SUCCESS;
    }
}
