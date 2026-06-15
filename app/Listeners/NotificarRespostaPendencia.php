<?php

namespace App\Listeners;

use App\Events\PendenciaRespondida;
use App\Notifications\RespostaPendenciaNotification;
use App\Services\Comunicacao\NotificationDispatcher;
use App\Support\Audit\AuditService;

/**
 * Efeito colateral DESACOPLADO do PendenciaRespondida (HU-091/092): avisa o
 * ANALISTA responsável (assigned_user_id) de que o requerente respondeu a
 * pendência e a análise reabriu — pelo NotificationDispatcher (multicanal). É o
 * retorno que a Fase 10 não fechava.
 *
 * Registro ÚNICO por AUTO-DESCOBERTA (type-hint do evento no handle); NÃO
 * registrar via Event::listen — duplicaria o aviso e o ledger (lição Fases 8/9).
 * A reabertura (em_pendencia→em_analise), a timeline e a auditoria
 * pendencia-respondida já ocorreram SÍNCRONAS na transação (Fase 10) e NÃO
 * dependem deste listener; ele só pluga a comunicação.
 *
 * Degradação HONESTA: sem analista atribuído, audita 'sem-destinatario' e retorna
 * (nunca inventa envio). Os toggles de canal e o mapa_canais (HU-014) são
 * resolvidos pelo dispatcher.
 */
class NotificarRespostaPendencia
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private AuditService $audit,
    ) {}

    public function handle(PendenciaRespondida $event): void
    {
        $request = $event->request;
        $pendency = $event->pendency;
        $analista = $request->assignedTo;

        if ($analista === null) {
            $this->audit->log(
                'notificacoes',
                'pendencia-respondida',
                "Sem analista responsável para notificar a resposta da pendência #{$pendency->id} do protocolo {$request->protocol_number}.",
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

        $this->dispatcher->deliver($analista, new RespostaPendenciaNotification(
            $request->protocol_number ?? '',
            $request->id,
        ));
    }
}
