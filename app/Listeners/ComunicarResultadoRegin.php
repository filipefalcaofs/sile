<?php

namespace App\Listeners;

use App\Events\ResultadoEmitido;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginParecerNotifier;
use App\Services\Regin\ReginUnavailableException;
use App\Support\Audit\AuditService;
use Throwable;

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
 * O envio sai na hora, sem passar pela fila (o parecer não espera os jobs lentos
 * de GeoServer): no request do analista roda logo DEPOIS da resposta, para não
 * segurar a tela; em console (job, comando, teste) roda direto. A decisão já foi
 * gravada e auditada SÍNCRONA (HU-078). Nenhuma falha é relançada — a integração
 * indisponível jamais quebra o efeito do resultado; o registro 'bloqueado' é a
 * pendência que o regin:reenviar-pareceres agendado retoma.
 *
 * Registrado SÓ por auto-descoberta de eventos (type-hint de ResultadoEmitido no
 * handle): NÃO registrar via Event::listen (lição da Fase 8 — duplicaria a
 * auditoria). A não-duplicação é travada por CONTAGEM no teste.
 */
class ComunicarResultadoRegin
{
    public function __construct(
        private ReginParecerNotifier $regin,
        private AuditService $audit,
    ) {}

    public function handle(ResultadoEmitido $event): void
    {
        if (app()->runningInConsole()) {
            $this->enviar($event);

            return;
        }

        dispatch(fn () => $this->enviar($event))->afterResponse();
    }

    private function enviar(ResultadoEmitido $event): void
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
            $this->registrarPendencia($request, $properties, $e);
        } catch (Throwable $e) {
            report($e);

            $this->registrarPendencia($request, $properties, $e);
        }
    }

    /**
     * Degradação honesta: a transmissão NÃO ocorreu. Registra a pendência visível
     * (trilha) — jamais um sucesso fictício.
     *
     * @param  array<string, mixed>  $properties
     */
    private function registrarPendencia(ViabilityRequest $request, array $properties, Throwable $e): void
    {
        $this->audit->log(
            logName: 'integracoes',
            event: 'regin-parecer',
            description: "Comunicação do parecer ao Regin/Junta pendente para o protocolo {$request->protocol_number}",
            properties: array_merge($properties, ['erro' => $e->getMessage()]),
            subject: $request,
            result: 'bloqueado',
        );
    }
}
