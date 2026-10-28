<?php

namespace App\Services\Sefaz;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;

/**
 * Última viabilidade deferida da mesma empresa (CNPJ) no próprio Viabiliza: o
 * número do TVL e os CNAEs do processo que a gerou. Processo sem CNAEs não serve
 * de base: lista vazia não informa nada.
 */
class ProcessoAnteriorViabilizaFonte implements FonteViabilidadeAnterior
{
    public function nome(): string
    {
        return 'viabiliza';
    }

    public function disponivel(): bool
    {
        return true;
    }

    public function consultar(ViabilityRequest $processo, string $cnpj): ?ViabilidadeVinculada
    {
        $decisao = ViabilityDecision::query()
            ->where('outcome', DecisionOutcome::Deferida->value)
            ->where('viability_request_id', '!=', $processo->id)
            ->whereHas('viabilityRequest.company', fn ($empresa) => $empresa->where('cnpj', $cnpj))
            ->whereHas('viabilityRequest.cnaes')
            ->latest('id')
            ->with('viabilityRequest.cnaes')
            ->first();

        if ($decisao === null) {
            return null;
        }

        return new ViabilidadeVinculada(
            numero: (string) $decisao->tvl_product_number,
            cnaes: $decisao->viabilityRequest->cnaes->pluck('code')->all(),
        );
    }
}
