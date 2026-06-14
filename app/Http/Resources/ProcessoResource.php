<?php

namespace App\Http\Resources;

use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisSlaService;
use App\Services\Analise\ProcessoQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de leitura do processo para a consulta (HU-082) e a fila (HU-144) da
 * retaguarda. Expõe os TRÊS identificadores do processo (RN-007 — número do
 * processo SEDUR, protocolo BAP e número do produto TVL), a empresa, o imóvel, o
 * status, a categoria DERIVADA (RN-005), o analista/setor responsáveis e o SLA
 * resumido (semáforo calculado on-the-fly via AnalysisSlaService — nunca
 * persistido). Consumido como prop do Inertia (->resolve()), sem o wrapper
 * "data". A tela (fila/consulta/detalhe) é construída em 10-16.
 *
 * @mixin ViabilityRequest
 */
class ProcessoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $categorias = ProcessoQueryService::categoriasDe($this->resource);

        return [
            'id' => $this->id,
            // Três identificadores do processo (RN-007).
            'protocol_number' => $this->protocol_number,
            'bap' => $this->external_reference,
            'tvl_product_number' => $this->decision?->tvl_product_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'empresa' => $this->company?->trade_name ?: $this->company?->legal_name,
            'cnpj' => $this->company?->formatted_cnpj,
            'imovel' => $this->enderecoResumo(),
            'inscricao' => $this->property_registration,
            'categorias' => $categorias,
            // Categoria primária para exibição compacta (lista/CSV); a lista
            // completa fica em `categorias`.
            'categoria' => $categorias[0]['label'] ?? null,
            'analista' => $this->assignedTo?->name,
            'assigned_user_id' => $this->assigned_user_id,
            'setor' => $this->sector?->name,
            'sector_id' => $this->sector_id,
            'analysis_stage' => $this->analysis_stage?->value,
            'analysis_stage_label' => $this->analysis_stage?->label(),
            'analysis_due_at' => $this->analysis_due_at?->toIso8601String(),
            'sla' => $this->slaResumo(),
            'protocoled_at' => $this->protocoled_at?->toIso8601String(),
        ];
    }

    /**
     * Semáforo do SLA + tempo restante calculados ON-THE-FLY (HU-144). Null
     * quando o processo ainda não tem prazo/etapa materializados (sem fila).
     *
     * @return array{status: string, status_label: string, restante: string}|null
     */
    private function slaResumo(): ?array
    {
        if ($this->analysis_due_at === null || $this->analysis_stage_started_at === null) {
            return null;
        }

        $resultado = app(AnalysisSlaService::class)->statusFor(
            $this->analysis_due_at,
            $this->analysis_stage_started_at,
        );

        return [
            'status' => $resultado['status']->value,
            'status_label' => $resultado['status']->label(),
            'restante' => $resultado['restante'],
        ];
    }

    /**
     * Endereço resumido do imóvel (logradouro, número e bairro).
     */
    private function enderecoResumo(): string
    {
        $logradouro = trim(implode(', ', array_filter([$this->address_street, $this->address_number])));

        return implode(' - ', array_filter([
            $logradouro !== '' ? $logradouro : null,
            $this->address_neighborhood,
        ]));
    }
}
