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
            // Endereço completo (com complemento e CEP) para o detalhe do
            // processo (HU-082) — o requisito da SEDUR é sempre mostrar o
            // endereço completo do imóvel, incluindo o complemento.
            'endereco_completo' => $this->enderecoCompleto(),
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
            'analysis_status' => $this->analysis_status?->value,
            'analysis_status_label' => $this->analysis_status?->label(),
            'analysis_due_at' => $this->analysis_due_at?->toIso8601String(),
            'sla' => $this->slaResumo(),
            'protocoled_at' => $this->protocoled_at?->toIso8601String(),
            // Bloco de escritório virtual (T03) — só presente quando o produto é
            // sede ou abrigado; null para o processo comum (a UI não renderiza o
            // card). Fonte única: a decisão imutável (RN-EV-02/05).
            'escritorio_virtual' => $this->escritorioVirtual(),
        ];
    }

    /**
     * Card "Escritório virtual" do produto (T03). Deriva o tipo do produto da
     * decisão (sede quando is_virtual_office_hq; abrigado quando
     * is_virtual_office_tenant) e expõe o TVL da SEDE — para o abrigado é o
     * `virtual_office_hq_tvl_number` (End. Virtual — TVL Nº, RN-EV-05 CA-P-01);
     * para a sede é o próprio `tvl_product_number`. A VALIDADE da sede não é
     * modelada (desfecho spec-2) e degrada para null ("—" na tela) — jamais
     * inventada. Null quando o processo não é EV.
     *
     * @return array{tipo: string, tvl_sede: string|null, inscricao: string|null, validade_sede: null, status_produto: string|null}|null
     */
    private function escritorioVirtual(): ?array
    {
        $decision = $this->decision;

        if ($decision === null) {
            return null;
        }

        $ehSede = $decision->is_virtual_office_hq === true;
        $ehAbrigado = $decision->is_virtual_office_tenant === true;

        if (! $ehSede && ! $ehAbrigado) {
            return null;
        }

        return [
            'tipo' => $ehSede ? 'sede' : 'abrigado',
            'tvl_sede' => $ehSede ? $decision->tvl_product_number : $decision->virtual_office_hq_tvl_number,
            'inscricao' => $this->property_registration,
            // Validade do produto da sede não modelada (desfecho spec-2).
            'validade_sede' => null,
            'status_produto' => $decision->outcome?->label(),
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

    /**
     * Endereço completo do imóvel para o detalhe: logradouro, número e
     * COMPLEMENTO, bairro e CEP. Degrada honesto — só concatena o que existe,
     * nunca inventa partes ausentes.
     */
    private function enderecoCompleto(): string
    {
        $logradouro = trim(implode(', ', array_filter([
            $this->address_street,
            $this->address_number,
            $this->address_complement,
        ])));

        $partes = implode(' - ', array_filter([
            $logradouro !== '' ? $logradouro : null,
            $this->address_neighborhood,
        ]));

        return trim(implode(' · ', array_filter([
            $partes !== '' ? $partes : null,
            $this->address_zip !== null && $this->address_zip !== '' ? "CEP {$this->address_zip}" : null,
        ])));
    }
}
