<?php

namespace App\Services\Renovacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Support\Settings;
use Carbon\CarbonInterface;

/**
 * Diz se um TVL já deferido neste sistema pode abrir renovação. A primeira
 * regra que falha devolve o motivo em linguagem simples. TVL de origem SIGS
 * não entra aqui: a renovação dele continua no SIGS.
 */
class RenovacaoElegibilidadeService
{
    public const MOTIVO_SEM_VINCULO = 'Não há viabilidade vinculada a este CNPJ.';

    public const MOTIVO_DEFINITIVA = 'Viabilidade definitiva não pode ser renovada.';

    public const MOTIVO_EM_ANALISE = 'Para cadastrar novo processo de renovação, aguarde a conclusão da análise ou solicite desistência do processo em análise.';

    public function avaliar(ViabilityRequest $origem): RenovacaoElegibilidade
    {
        $origem->loadMissing('decision');
        $decisao = $origem->decision;

        if ($origem->status !== ViabilityRequestStatus::Deferida || $decisao === null || $decisao->tvl_product_number === null) {
            return new RenovacaoElegibilidade(false, self::MOTIVO_SEM_VINCULO);
        }

        if ($decisao->tvl_kind?->renovavel() !== true) {
            return new RenovacaoElegibilidade(false, self::MOTIVO_DEFINITIVA);
        }

        $foraDaJanela = self::motivoForaDaJanela($decisao->tvl_valid_until);

        if ($foraDaJanela !== null) {
            return new RenovacaoElegibilidade(false, $foraDaJanela);
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

    /**
     * A renovação só pode ser solicitada a partir de N meses antes do vencimento
     * (renovacao.antecedencia_meses, padrão 3); depois de vencida a regra não barra.
     */
    public static function motivoForaDaJanela(?CarbonInterface $validade): ?string
    {
        if ($validade === null) {
            return null;
        }

        $meses = max(1, (int) Settings::get('renovacao.antecedencia_meses', 3));
        $abertura = $validade->copy()->startOfDay()->subMonthsNoOverflow($meses);

        if (! now()->startOfDay()->lt($abertura)) {
            return null;
        }

        return "A renovação só pode ser solicitada a partir de {$abertura->format('d/m/Y')}, {$meses} meses antes do vencimento.";
    }
}
