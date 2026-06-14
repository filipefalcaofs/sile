<?php

namespace App\Services\Regin;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;

/**
 * Contrato de comunicação do parecer (deferimento/indeferimento) ao integrador
 * Regin/Junta Comercial (HU-104). O provider concreto é substituível por binding
 * — a Fase 13 liga o integrador conveniado sem tocar nenhum call site (mesmo
 * padrão de PropertyRegistryLookup/CnpjLookup/Geocoder).
 */
interface ReginParecerNotifier
{
    /**
     * Comunica o parecer da viabilidade ao integrador Regin/Junta. A forma fina
     * do payload é definida na Fase 13 (contrato/homologação); o contrato carrega
     * a solicitação e a decisão imutável já tomada.
     *
     * @throws ReginUnavailableException quando o integrador está indisponível/pendente (Fase 13)
     */
    public function notifyParecer(ViabilityRequest $request, ViabilityDecision $decision): void;
}
