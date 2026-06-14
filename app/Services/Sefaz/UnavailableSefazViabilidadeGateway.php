<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;

/**
 * Provider INDISPONÍVEL do envio à SEFAZ municipal: o contrato e a homologação
 * estão PENDENTES (Fase 13, HU-110). Sem canal oficial, NUNCA fingimos o envio —
 * degradação honesta, jamais adaptador falso. O listener que envia o deferimento
 * (09-09) captura a exceção e audita a pendência. A Fase 13 troca SÓ este binding.
 */
class UnavailableSefazViabilidadeGateway implements SefazViabilidadeGateway
{
    public function sendViabilidade(ViabilityRequest $request, ViabilityDecision $decision): void
    {
        // SEFAZ pendente Fase 13: degrada honestamente, nunca simula o envio.
        throw new SefazUnavailableException($request->protocol_number);
    }
}
