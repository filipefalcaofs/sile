<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;

/**
 * Contrato de envio dos dados de viabilidade à SEFAZ municipal (HU-110). SÓ o
 * DEFERIMENTO é enviado — quem chama (listener 09-09) decide. O provider concreto
 * é substituível por binding — a Fase 13 liga a SEFAZ conveniada sem tocar call
 * sites (mesmo padrão de PropertyRegistryLookup).
 */
interface SefazViabilidadeGateway
{
    /**
     * Envia os dados de viabilidade do deferimento à SEFAZ municipal. A forma
     * fina do payload é definida na Fase 13 (contrato/homologação).
     *
     * @throws SefazUnavailableException quando a SEFAZ está indisponível/pendente (Fase 13)
     */
    public function sendViabilidade(ViabilityRequest $request, ViabilityDecision $decision): void;
}
