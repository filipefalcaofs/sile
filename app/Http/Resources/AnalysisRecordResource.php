<?php

namespace App\Http\Resources;

use App\Models\AnalysisRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de leitura da ficha de análise (HU-135) para a retaguarda: a revisão
 * vigente, seu status (+rótulo) e a flag `editavel` (false na finalizada — RN-003),
 * o per_cnae com a sugestão do motor (status_sugerido) × a escolha do analista
 * (status_escolhido), as condicionantes, as vagas, o parecer e `finalized_at`.
 * Expõe também a disponibilidade do motor (FA-01 — modo manual). SOMENTE LEITURA;
 * a escrita acontece pelo autosave/finalizar (AnalysisRecordService).
 *
 * Consumido como prop do Inertia: o controller chama ->resolve() para entregar o
 * array puro (sem o wrapper "data" do JsonResource). A página é construída em 10-17.
 *
 * @mixin AnalysisRecord
 */
class AnalysisRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'viability_request_id' => $this->viability_request_id,
            'revision' => $this->revision,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'editavel' => ! $this->isFinalizada(),
            'engine_available' => $this->engine_available,
            'per_cnae' => array_values((array) ($this->per_cnae ?? [])),
            'conditions' => array_values((array) ($this->conditions ?? [])),
            'parking' => $this->parking ?? [],
            'parecer' => $this->parecer,
            'analyst' => $this->analyst?->name,
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
