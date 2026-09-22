<?php

namespace App\Services\Regin;

use App\Models\ViabilityRequest;

/**
 * Contrato de resolução do vínculo BAP (Boletim de Atividade Produtiva) do
 * processo junto à Junta/Regin (HU-134/HU-133). O provider concreto é
 * substituível por binding — a Fase 13 liga a base do Regin sem tocar call
 * sites. Retorna null quando NÃO há vínculo: o processo não entra em
 * aguardando_bap (estado honesto, não um erro).
 */
interface BapRegistry
{
    /**
     * Resolve o vínculo BAP do processo consultando o integrador Regin/Junta.
     * Retorna null quando não há vínculo — honesto, sem inventar prazo (HU-134
     * dormente até o Regin alimentar o vínculo na Fase 13).
     */
    public function findLinkage(ViabilityRequest $request): ?BapLinkage;
}
