<?php

namespace App\Listeners;

use App\Events\ResultadoEmitido;
use App\Notifications\ResultadoExpressoNotification;
use App\Services\Comunicacao\NotificationDispatcher;
use App\Support\Audit\AuditService;
use App\Support\Settings;

/**
 * Efeito colateral DESACOPLADO do ResultadoEmitido (HU-077): notifica o
 * requerente do desfecho do fluxo expresso. A partir do EP11 (11-06) o aviso
 * percorre o NotificationDispatcher (HU-090/094) — ganha in-app + histórico
 * (communications) além do e-mail, unificando as notificações de processo num só
 * roteador. O e-mail segue SEM anexo de TVL (o canal oficial de entrega é o
 * Regin/SEFAZ).
 *
 * Registro ÚNICO por AUTO-DESCOBERTA (type-hint do evento no handle); NÃO
 * registrar via Event::listen — duplicaria a notificação e a auditoria (lição
 * Fase 8). A decisão e a auditoria autoritativa (HU-078) já ocorreram SÍNCRONAS
 * na transação (09-05) e NÃO dependem deste listener; ele só pluga o aviso ao
 * cidadão. Mantém-se SÍNCRONO — quem enfileira é a notificação (ShouldQueue),
 * espelhando o padrão da casa (RegistrarTrilhaProtocolo/VerifyEmailQueued).
 *
 * Toggle de TIPO features.notificacao_resultado_expresso off → degradação
 * COMUNICADA: não notifica e AUDITA (nunca falha silenciosa). Os CANAIS (e o
 * ledger communications) passam a ser resolvidos pelo dispatcher (mapa ∩ toggles
 * de canal). Sem destinatário com e-mail → audita 'sem-destinatario' e retorna
 * (honesto — não inventa envio).
 */
class NotificarResultadoExpresso
{
    public function __construct(
        private AuditService $audit,
        private NotificationDispatcher $dispatcher,
    ) {}

    public function handle(ResultadoEmitido $event): void
    {
        $request = $event->request;
        $decision = $event->decision;

        $habilitado = Settings::get(
            'features.notificacao_resultado_expresso',
            config('sile.features.notificacao_resultado_expresso', true),
        );

        if (! $habilitado) {
            $this->audit->log(
                'notificacoes',
                'resultado-expresso',
                "Notificação do resultado do protocolo {$request->protocol_number} desativada por parâmetro (features.notificacao_resultado_expresso)",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'outcome' => $decision->outcome->value,
                ],
                subject: $request,
                result: 'desativado',
            );

            return;
        }

        $requester = $request->requester;

        if ($requester === null || blank($requester->email)) {
            $this->audit->log(
                'notificacoes',
                'resultado-expresso',
                "Sem destinatário com e-mail para notificar o resultado do protocolo {$request->protocol_number}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'outcome' => $decision->outcome->value,
                ],
                subject: $request,
                result: 'sem-destinatario',
            );

            return;
        }

        $this->dispatcher->deliver(
            $requester,
            new ResultadoExpressoNotification($request->protocol_number, $decision->outcome, $request->id),
        );
    }
}
