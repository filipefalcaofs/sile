<?php

namespace App\Services\EscritorioVirtual;

use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeActivityCnae;
use App\Models\VirtualOfficeInscriptionLock;

/**
 * Resolve se uma solicitação é ABRIGADO de escritório virtual (RN-EV-05): a
 * inscrição tem uma SEDE ativa e os CNAEs do processo estão na Lista EV vigente.
 * Devolve a referência ao produto da sede (nº TVL) ou null.
 */
class AbrigadoResolver
{
    /**
     * @return array{hq_tvl_number: string|null}|null
     */
    public function resolve(ViabilityRequest $request): ?array
    {
        $inscricao = $request->property_registration;

        if ($inscricao === null || $inscricao === '') {
            return null;
        }

        $lock = VirtualOfficeInscriptionLock::sedeAtiva($inscricao);

        if ($lock === null) {
            return null;
        }

        $cnaes = $request->cnaes;

        if ($cnaes->isEmpty() || $cnaes->contains(fn ($cnae): bool => ! VirtualOfficeActivityCnae::permitido($cnae->code))) {
            return null;
        }

        return ['hq_tvl_number' => $lock->sede?->decision?->tvl_product_number];
    }
}
