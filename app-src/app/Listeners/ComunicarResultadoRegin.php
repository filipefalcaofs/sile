<?php

namespace App\Listeners;

use App\Events\ResultadoEmitido;
use App\Services\Regin\ReginParecerNotifier;
use App\Services\Regin\ReginUnavailableException;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Efeito desacoplado do SEGUNDO evento de domínio (HU-104): comunica o parecer da
 * viabilidade — deferimento OU indeferimento (HU-076 RN-008: o parecer vai ao
 * Regin/Junta nos DOIS casos) — ao integrador, via o contrato ReginParecerNotifier.
 *
 * BLOQUEADO HONESTO (Fase 13): o binding atual é o UnavailableReginParecerNotifier,
 * que LANÇA ReginUnavailableException (a transmissão NÃO ocorreu — contrato/
 * homologação pendentes). Este listener CAPTURA a exceção e AUDITA a pendência de
 * integração (logName 'integracoes', result 'bloqueado') — NUNCA registra sucesso
 * fictício, NUNCA simula o envio (anti-fachada). A Fase 13 troca SÓ o binding no
 * AppServiceProvider e o caminho de sucesso (já provado com um fake nos testes)
 * passa a auditar 'sucesso', sem tocar este listener.
 *
 * A integração de saída roda na FILA (ShouldQueue): não atrasa nem derruba a
 * decisão, que já foi gravada e auditada SÍNCRONA (HU-078). A exceção NÃO é
 * relançada para fora do handle — a integração indisponível jamais quebra o efeito
 * do resultado; o registro 'bloqueado' é a pendência visível (outbox/trilha).
 *
 * Registrado SÓ por auto-descoberta de eventos (type-hint de ResultadoEmitido no
 * handle): NÃO registrar via Event::listen (lição da Fase 8 — duplicaria a
 * auditoria). A não-duplicação é travada por CONTAGEM no teste.
 */
class ComunicarResultadoRegin implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private ReginParecerNotifier $regin,
        private AuditService $audit,
    ) {}

    public function handle(ResultadoEmitido $event): void
    {
        $request = $event->request;
        $decision = $event->decision;

        $properties = [
            'viability_request_id' => $request->id,
            'protocolo' => $request->protocol_number,
            'outcome' => $decision->outcome->value,
        ];

        try {
            $this->regin->notifyParecer($request, $decision);

            $this->audit->log(
                logName: 'integracoes',
                event: 'regin-parecer',
                description: "Parecer ({$decision->outcome->value}) comunicado ao Regin/Junta para o protocolo {$request->protocol_number}",
                properties: $properties,
                subject: $request,
                result: 'sucesso',
            );
        } catch (ReginUnavailableException $e) {
            // Degradação honesta (Fase 13): a transmissão NÃO ocorreu. Registra a
            // pendência visível (outbox/trilha) — jamais um sucesso fictício.
            $this->audit->log(
                logName: 'integracoes',
                event: 'regin-parecer',
                description: "Comunicação do parecer ao Regin/Junta pendente (integração bloqueada — Fase 13) para o protocolo {$request->protocol_number}",
                properties: array_merge($properties, ['erro' => $e->getMessage()]),
                subject: $request,
                result: 'bloqueado',
            );
        }
    }
}
