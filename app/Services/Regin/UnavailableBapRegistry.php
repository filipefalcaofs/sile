<?php

namespace App\Services\Regin;

use App\Models\ViabilityRequest;

/**
 * Provider INDISPONÍVEL do vínculo BAP: a base do Regin/Junta está PENDENTE
 * (Fase 13, HU-134). Sem fonte oficial, NUNCA inventamos um vínculo — retorna
 * null (não há vínculo). Ao contrário de Regin/SEFAZ, a ausência de vínculo é um
 * estado honesto de negócio (não exceção): nada entra em aguardando_bap até o
 * Regin alimentar o vínculo. A Fase 13 troca SÓ este binding.
 */
class UnavailableBapRegistry implements BapRegistry
{
    public function findLinkage(ViabilityRequest $request): ?BapLinkage
    {
        // Base do Regin pendente Fase 13: sem vínculo honesto, jamais inventado.
        return null;
    }
}
