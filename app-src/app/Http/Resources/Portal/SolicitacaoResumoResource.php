<?php

namespace App\Http\Resources\Portal;

use App\Models\ViabilityRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Núcleo comum de uma solicitação para o portal do cidadão (listagem e painel):
 * protocolo, situação em linguagem pública (publicLabel — a cor é derivada no
 * front a partir de status.value), tipo de serviço, empresa e data. Campos
 * específicos de contexto (editable/cancelable) ficam no call site, não aqui.
 *
 * @mixin ViabilityRequest
 */
class SolicitacaoResumoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'protocol_number' => $this->protocol_number,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'public_label' => $this->status->publicLabel(),
            ],
            'service_type' => $this->serviceType?->name,
            'company' => $this->company ? [
                'legal_name' => $this->company->legal_name,
                'formatted_cnpj' => $this->company->formatted_cnpj,
            ] : null,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
