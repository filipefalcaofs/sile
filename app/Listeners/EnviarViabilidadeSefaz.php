<?php

namespace App\Listeners;

use App\Events\ResultadoEmitido;
use App\Services\Sefaz\SefazUnavailableException;
use App\Services\Sefaz\SefazViabilidadeGateway;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Efeito desacoplado do fluxo expresso (HU-110): envia os dados de viabilidade à
 * SEFAZ municipal. Pendura no SEGUNDO evento de domínio (ResultadoEmitido) e age
 * SÓ no DEFERIMENTO — o canal oficial ao município só faz sentido quando a
 * atividade é deferida. O INDEFERIMENTO não é enviado (HU-134 RN-003): é ignorado
 * de forma auditável, sem tocar o gateway.
 *
 * A transmissão está BLOQUEADA (sem contrato/homologação): o binding padrão é o
 * UnavailableSefazViabilidadeGateway, que LANÇA SefazUnavailableException. Este
 * listener CAPTURA a exceção e AUDITA a pendência (result 'bloqueado') — NUNCA
 * finge o envio (anti-fachada). A Fase 13 liga a SEFAZ conveniada trocando SÓ o
 * binding, sem tocar este listener. A integração bloqueada NÃO faz a decisão
 * falhar — por isso a exceção é absorvida, não relançada.
 *
 * Registrado SÓ por auto-descoberta de eventos (type-hint do evento no handle):
 * NÃO registrar via Event::listen no AppServiceProvider — isso DUPLICARIA o
 * registro e a auditoria (lição da Fase 8 — RN-002). A não-duplicação é travada
 * por CONTAGEM nos testes (1 auditoria por resultado emitido).
 */
class EnviarViabilidadeSefaz implements ShouldQueue
{
    public function __construct(
        private SefazViabilidadeGateway $sefaz,
        private AuditService $audit,
    ) {}

    public function handle(ResultadoEmitido $event): void
    {
        $request = $event->request;
        $decision = $event->decision;

        // HU-134 RN-003: o indeferimento NÃO vai à SEFAZ. Ignora de forma
        // auditável, sem acionar o gateway.
        if (! $decision->isDeferida()) {
            $this->audit->log(
                'integracoes',
                'sefaz-viabilidade',
                'Envio à SEFAZ não se aplica (indeferimento)',
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'outcome' => $decision->outcome->value,
                ],
                result: 'ignorado',
                subject: $request,
            );

            return;
        }

        try {
            $this->sefaz->sendViabilidade($request, $decision);

            $this->audit->log(
                'integracoes',
                'sefaz-viabilidade',
                "Viabilidade enviada à SEFAZ municipal para o protocolo {$request->protocol_number}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'outcome' => $decision->outcome->value,
                ],
                result: 'sucesso',
                subject: $request,
            );
        } catch (SefazUnavailableException) {
            // Transmissão pendente (Fase 13): degrada honesto — audita a
            // pendência, jamais um sucesso fictício. Não relança: a decisão já
            // está efetivada e não falha pela integração bloqueada.
            $this->audit->log(
                'integracoes',
                'sefaz-viabilidade',
                'Envio à SEFAZ pendente (integração bloqueada — Fase 13)',
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'outcome' => $decision->outcome->value,
                ],
                result: 'bloqueado',
                subject: $request,
            );
        }
    }
}
