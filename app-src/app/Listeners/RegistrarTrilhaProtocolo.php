<?php

namespace App\Listeners;

use App\Enums\ViabilityRequestStatus;
use App\Events\SolicitacaoProtocolada;
use App\Support\Audit\AuditService;

/**
 * Listener síncrono do primeiro evento de domínio (HU-068/HU-069): garante o
 * marco amigável da timeline para o cidadão e grava uma auditoria de alto nível
 * do protocolo. Idempotente — NÃO duplica o marco: a transição síncrona da
 * ViabilityRequestStateMachine já o registrou com o public_label; aqui só
 * garantimos que exista (defesa caso uma transição futura não o traga).
 *
 * A trilha do protocolo NÃO depende deste listener (a transição síncrona já
 * audita 'transicao'); esta é a auditoria de NEGÓCIO de alto nível 'protocolada'.
 */
class RegistrarTrilhaProtocolo
{
    public function __construct(private AuditService $audit) {}

    public function handle(SolicitacaoProtocolada $event): void
    {
        $request = $event->request;

        $temMarcoAmigavel = $request->transitions()
            ->where('to_status', ViabilityRequestStatus::Protocolada)
            ->whereNotNull('public_label')
            ->exists();

        if (! $temMarcoAmigavel) {
            $request->transitions()->create([
                'from_status' => ViabilityRequestStatus::Rascunho,
                'to_status' => ViabilityRequestStatus::Protocolada,
                'public_label' => ViabilityRequestStatus::Protocolada->publicLabel(),
                'actor_user_id' => null,
            ]);
        }

        $this->audit->log(
            'solicitacoes',
            'protocolada',
            "Solicitação protocolada sob o número {$request->protocol_number}",
            properties: [
                'viability_request_id' => $request->id,
                'protocol_number' => $request->protocol_number,
            ],
            subject: $request,
        );
    }
}
