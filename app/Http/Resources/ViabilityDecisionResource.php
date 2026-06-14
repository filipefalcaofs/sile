<?php

namespace App\Http\Resources;

use App\Enums\ResultadoViabilidade;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de leitura da ViabilityDecision para a retaguarda (HU-076/HU-078):
 * expõe o desfecho (+rótulo), o veredito consolidado (+rótulo), o número TVL
 * (só no deferimento — RN-007), a decisão por CNAE, as versões de regra da
 * época (RN-005), a fundamentação legal, o motivo (ex.: 'indeferido sem
 * atuação', HU-134) e um resumo honesto da solicitação. SOMENTE LEITURA — a
 * decisão é imutável (append-only, 09-02/09-05); este recurso nunca a altera.
 *
 * Como é consumido como prop do Inertia, o controller chama ->resolve() para
 * entregar o array puro (sem o wrapper "data" do JsonResource).
 *
 * @mixin ViabilityDecision
 */
class ViabilityDecisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $solicitacao = $this->viabilityRequest;

        return [
            'id' => $this->id,
            'flow' => $this->flow,
            'outcome' => $this->outcome->value,
            'outcome_label' => $this->outcome->label(),
            'consolidated_result' => $this->consolidated_result,
            'consolidated_result_label' => $this->consolidatedResultLabel(),
            'tvl_product_number' => $this->tvl_product_number,
            'per_cnae' => array_values((array) ($this->per_cnae ?? [])),
            'rules_versions' => $this->rules_versions ?? [],
            // Sempre uma LISTA (array_values): a tela de detalhe itera a
            // fundamentação; um shape associativo legado não pode derrubar o SSR.
            'fundamentacao' => array_values((array) ($this->fundamentacao ?? [])),
            'reason' => $this->reason,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decided_by' => $this->decided_by_user_id === null ? null : $this->decidedBy?->name,
            'is_sistema' => $this->decided_by_user_id === null,
            'solicitacao' => $solicitacao === null ? null : [
                'id' => $solicitacao->id,
                'protocol_number' => $solicitacao->protocol_number,
                'status' => $solicitacao->status->value,
                'status_label' => $solicitacao->status->label(),
                'empresa' => $solicitacao->company?->trade_name ?: $solicitacao->company?->legal_name,
                'cnpj' => $solicitacao->company?->formatted_cnpj,
                'endereco' => $this->enderecoResumo($solicitacao),
            ],
        ];
    }

    /**
     * Rótulo do veredito consolidado (LOUOS RN-009). Mantém o valor cru como
     * fallback caso surja um resultado fora do enum conhecido — nunca esconde
     * o dado real.
     */
    private function consolidatedResultLabel(): ?string
    {
        if ($this->consolidated_result === null) {
            return null;
        }

        return ResultadoViabilidade::tryFrom((string) $this->consolidated_result)?->label()
            ?? (string) $this->consolidated_result;
    }

    /**
     * Endereço resumido do imóvel da solicitação (logradouro, número e bairro).
     */
    private function enderecoResumo(ViabilityRequest $solicitacao): string
    {
        $logradouro = trim(implode(', ', array_filter([
            $solicitacao->address_street,
            $solicitacao->address_number,
        ])));

        $partes = array_filter([
            $logradouro !== '' ? $logradouro : null,
            $solicitacao->address_neighborhood,
        ]);

        return implode(' - ', $partes);
    }
}
