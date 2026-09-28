<?php

namespace App\Services\Renovacao;

use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Support\Settings;

/**
 * Diz se um TVL já deferido pode abrir renovação. A primeira regra que falha
 * devolve o motivo em linguagem simples — nunca um bloqueio silencioso.
 */
class RenovacaoElegibilidadeService
{
    private const MOTIVO_SEM_VINCULO = 'Não há viabilidade vinculada a este CNPJ.';

    private const MOTIVO_DEFINITIVA = 'Viabilidade definitiva não pode ser renovada.';

    private const MOTIVO_EM_ANALISE = 'Para cadastrar novo processo de renovação, aguarde a conclusão da análise ou solicite desistência do processo em análise.';

    public function avaliar(ViabilityRequest $origem): RenovacaoElegibilidade
    {
        $origem->loadMissing('decision');
        $decisao = $origem->decision;

        if ($origem->status !== ViabilityRequestStatus::Deferida || $decisao === null || $decisao->tvl_product_number === null) {
            return new RenovacaoElegibilidade(false, self::MOTIVO_SEM_VINCULO);
        }

        if ($decisao->tvl_kind !== TvlKind::Provisoria) {
            return new RenovacaoElegibilidade(false, self::MOTIVO_DEFINITIVA);
        }

        $meses = max(1, (int) Settings::get('renovacao.intervalo_meses', 3));

        $renovadaRecentemente = ViabilityRequest::query()
            ->where('renews_viability_request_id', $origem->id)
            ->where('status', ViabilityRequestStatus::Deferida)
            ->whereHas('decision', fn ($query) => $query->where('decided_at', '>=', now()->subMonths($meses)))
            ->exists();

        if ($renovadaRecentemente) {
            return new RenovacaoElegibilidade(false, "Esta viabilidade foi renovada há menos de {$meses} meses.");
        }

        $emAberto = ViabilityRequest::query()
            ->where('renews_viability_request_id', $origem->id)
            ->whereIn('status', [
                ViabilityRequestStatus::Protocolada,
                ViabilityRequestStatus::EmAnalise,
                ViabilityRequestStatus::EmPendencia,
            ])
            ->exists();

        if ($emAberto) {
            return new RenovacaoElegibilidade(false, self::MOTIVO_EM_ANALISE);
        }

        return new RenovacaoElegibilidade(true, null);
    }
}
