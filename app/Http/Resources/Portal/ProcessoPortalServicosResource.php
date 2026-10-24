<?php

namespace App\Http\Resources\Portal;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Processo de viabilidade na lista do Portal de Serviços da SEDUR: só o que o
 * cidadão já vê em Minhas solicitações, mais o endereço do Detalhar (o Portal
 * acrescenta o token da sessão).
 *
 * @mixin ViabilityRequest
 */
class ProcessoPortalServicosResource extends JsonResource
{
    public function __construct(ViabilityRequest $resource, private readonly string $vinculo)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'numero_processo' => $this->protocol_number,
            'numero_viabilidade' => $this->decision?->tvl_product_number,
            'bap' => $this->external_reference,
            'servico' => $this->serviceType?->name,
            'empresa' => $this->company?->legal_name,
            'cnpj' => $this->company?->cnpj,
            'situacao' => $this->status->value,
            'situacao_descricao' => $this->status->publicLabel(),
            'convite_pendente' => $this->status === ViabilityRequestStatus::EmPendencia,
            'protocolado_em' => $this->protocoled_at?->toIso8601String(),
            'atualizado_em' => $this->updated_at?->toIso8601String(),
            'vinculo' => $this->vinculo,
            'detalhar_url' => route('portal.entrar.portal-servicos').'?destino=processo&numero='.urlencode((string) $this->protocol_number),
        ];
    }
}
