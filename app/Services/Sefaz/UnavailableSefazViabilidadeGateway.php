<?php

namespace App\Services\Sefaz;

use App\Models\SefazNotification;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;

/**
 * Provider INDISPONÍVEL do envio à SEFAZ municipal: o contrato e a homologação
 * estão PENDENTES (Fase 13, HU-110). Sem canal oficial, NUNCA fingimos o envio —
 * degradação honesta, jamais adaptador falso. Cobre os dois canais do contrato:
 * o listener que envia o deferimento (09-09) e a comunicação de eventos de
 * escritório virtual (RN-EV-09) capturam a exceção e registram a pendência
 * como reprocessável. A Fase 13 troca SÓ este binding.
 */
class UnavailableSefazViabilidadeGateway implements SefazViabilidadeGateway
{
    public function sendViabilidade(ViabilityRequest $request, ViabilityDecision $decision): void
    {
        // SEFAZ pendente Fase 13: degrada honestamente, nunca simula o envio.
        throw new SefazUnavailableException($request->protocol_number);
    }

    public function sendEventoEscritorioVirtual(SefazNotification $notification): void
    {
        // SEFAZ pendente Fase 13: degrada honestamente, nunca simula o envio.
        throw new SefazUnavailableException(
            $notification->request?->protocol_number ?? "sefaz-notification-{$notification->id}",
            $notification->event->value,
        );
    }
}
