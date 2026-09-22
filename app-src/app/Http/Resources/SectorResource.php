<?php

namespace App\Http\Resources;

use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de leitura do setor (HU-138) para a retaguarda: nome, situação, os
 * analistas vinculados (N:N — RN-005) e os contadores de analistas e de
 * processos. Insumo da caixa de distribuição (10-07) e das telas de console
 * (10-17).
 *
 * Consumido como prop do Inertia: o controller chama ->resolve() para entregar
 * o array puro (sem o wrapper "data" do JsonResource).
 *
 * @mixin Sector
 */
class SectorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
            'analysts_count' => $this->whenCounted('analysts'),
            'requests_count' => $this->whenCounted('requests'),
            'analysts' => $this->whenLoaded('analysts', fn () => $this->analysts
                ->map(fn (User $analyst) => [
                    'id' => $analyst->id,
                    'name' => $analyst->name,
                ])
                ->values()),
        ];
    }
}
