<?php

namespace App\Services\Regin;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;

/**
 * Provider INDISPONÍVEL do parecer Regin/Junta: o contrato e a homologação do
 * integrador estão PENDENTES (Fase 13, HU-104). Sem canal oficial, NUNCA
 * fingimos que o parecer foi transmitido — degradação honesta, jamais adaptador
 * falso. Quem chama (listener 09-08) captura a exceção e audita a pendência de
 * integração (nunca registra sucesso fictício). A Fase 13 troca SÓ este binding.
 */
class UnavailableReginParecerNotifier implements ReginParecerNotifier
{
    public function notifyParecer(ViabilityRequest $request, ViabilityDecision $decision): void
    {
        // Integrador pendente Fase 13: degrada honestamente, nunca simula o envio.
        throw new ReginUnavailableException($request->protocol_number);
    }
}
