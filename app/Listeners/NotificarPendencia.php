<?php

namespace App\Listeners;

use App\Events\PendenciaSolicitada;
use App\Notifications\PendenciaSolicitadaNotification;
use App\Services\Comunicacao\NotificationDispatcher;
use App\Support\Audit\AuditService;

/**
 * Efeito colateral DESACOPLADO do PendenciaSolicitada (HU-090): notifica o
 * requerente, de forma MULTICANAL, de que a análise técnica abriu uma pendência
 * — e-mail + in-app + histórico (HU-096), pelo NotificationDispatcher (11-04).
 *
 * Generaliza o e-mail direto da Fase 10: o PendenciaService::abrir NÃO notifica
 * mais (anti-duplicação) — este listener é o ÚNICO ponto de aviso. Registro
 * ÚNICO por AUTO-DESCOBERTA (type-hint do evento no handle); NÃO registrar via
 * Event::listen — duplicaria o aviso e o ledger (lição Fases 8/9).
 *
 * A transição em_analise→em_pendencia, a timeline e a auditoria pendencia-aberta
 * já ocorreram SÍNCRONAS na transação (Fase 10) e NÃO dependem deste listener;
 * ele só pluga a comunicação. Degradação HONESTA: sem requerente, audita
 * 'sem-destinatario' e retorna (nunca inventa envio). Os toggles de canal e o
 * mapa_canais (HU-014) são resolvidos pelo dispatcher.
 */
class NotificarPendencia
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private AuditService $audit,
    ) {}

    public function handle(PendenciaSolicitada $event): void
    {
        $request = $event->request;
        $pendency = $event->pendency;
        $requester = $request->requester;

        if ($requester === null) {
            $this->audit->log(
                'notificacoes',
                'pendencia-aberta',
                "Sem destinatário para notificar a pendência #{$pendency->id} do protocolo {$request->protocol_number}.",
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

        $this->dispatcher->deliver($requester, new PendenciaSolicitadaNotification(
            $request->protocol_number ?? '',
            $pendency->description,
            $request->id,
        ));
    }
}
