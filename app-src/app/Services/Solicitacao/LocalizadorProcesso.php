<?php

namespace App\Services\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;

/**
 * Acha o processo pelo número que o cidadão tem em mãos: o do processo, o do
 * BAP ou o da viabilidade (TVL). Rascunho não é processo. Havendo mais de um,
 * vale o que casa pelo número do processo e, depois, o mais recente.
 */
class LocalizadorProcesso
{
    public function normalizar(?string $numero): ?string
    {
        $numero = strtoupper(preg_replace('/\s+/', '', (string) $numero) ?? '');

        return preg_match('#^[A-Z0-9][A-Z0-9./-]{0,59}$#', $numero) === 1 ? $numero : null;
    }

    public function localizar(?string $numero): ?ViabilityRequest
    {
        $numero = $this->normalizar($numero);

        if ($numero === null) {
            return null;
        }

        return ViabilityRequest::query()
            ->where('status', '!=', ViabilityRequestStatus::Rascunho)
            ->whereNotNull('protocol_number')
            ->where(fn ($query) => $query
                ->whereRaw('upper(protocol_number) = ?', [$numero])
                ->orWhereRaw('upper(external_reference) = ?', [$numero])
                ->orWhereHas('decision', fn ($decisao) => $decisao->whereRaw('upper(tvl_product_number) = ?', [$numero])))
            ->latest('id')
            ->get()
            ->sortByDesc(fn (ViabilityRequest $candidato) => strtoupper((string) $candidato->protocol_number) === $numero)
            ->first();
    }
}
