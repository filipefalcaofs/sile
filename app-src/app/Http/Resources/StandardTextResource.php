<?php

namespace App\Http\Resources;

use App\Models\StandardText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de leitura do texto-padrão (HU-085) para a retaguarda: categoria,
 * conteúdo, situação e versão (RN-005). Insumo das telas de console (10-17) e
 * da inserção no parecer (10-09, que lê apenas os ativos).
 *
 * Consumido como prop do Inertia: o controller chama ->resolve() para entregar
 * o array puro (sem o wrapper "data" do JsonResource).
 *
 * @mixin StandardText
 */
class StandardTextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'content' => $this->content,
            'active' => $this->active,
            'version' => $this->version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
