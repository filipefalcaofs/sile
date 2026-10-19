<?php

namespace App\Services\EscritorioVirtual;

use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeActivityCnae;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;

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

    /**
     * Alteração de atividades da JUCEB (evento 244) de empresa que já é abrigada
     * na inscrição, cuja sede segue ativa: as atividades novas precisam estar no
     * Anexo B (Lista EV). Devolve os CNAEs da lista que ficam fora dele, no
     * formato NNNN-N/NN; vazio quando não se aplica ou quando todos estão no anexo.
     *
     * @return list<string>
     */
    public function cnaesForaDoAnexoBDaAbrigada(ViabilityRequest $request): array
    {
        $inscricao = (string) $request->property_registration;

        if (trim($inscricao) === ''
            || $request->company_id === null
            || ! $request->eventos()->where('codigo', SedeEscritorioVirtualGatilho::EVENTO_ALTERACAO_ATIVIDADES)->exists()
            || VirtualOfficeInscriptionLock::sedeAtiva($inscricao) === null) {
            return [];
        }

        $jaEAbrigada = ViabilityRequest::query()
            ->where('company_id', $request->company_id)
            ->where('property_registration', $inscricao)
            ->whereKeyNot($request->id)
            ->whereHas('decision', fn ($decisao) => $decisao->where('is_virtual_office_tenant', true))
            ->exists();

        if (! $jaEAbrigada) {
            return [];
        }

        return $request->cnaes
            ->reject(fn ($cnae): bool => VirtualOfficeActivityCnae::permitido($cnae->code))
            ->map(fn ($cnae): string => $cnae->formatted_code)
            ->values()
            ->all();
    }
}
