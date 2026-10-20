<?php

namespace App\Http\Resources;

use App\Models\ViabilityRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Payload de leitura de uma notificação do canal database nativo (central
 * in-app, HU-086). Expõe o id (uuid), o tipo, o `data` gravado pelo
 * toDatabase() das Notifications de processo (title/summary/url; os payloads
 * legados titulo/mensagem/message são normalizados para title/summary), o read_at e o
 * created_at, mais um booleano `lida` de conveniência para a UI (11-09).
 *
 * Consumido como prop do Inertia: o controller chama ->resolve() para entregar
 * o array puro (sem o wrapper "data" do JsonResource).
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];
        $data['title'] ??= $data['titulo'] ?? null;
        $data['summary'] ??= $data['mensagem'] ?? $data['message'] ?? null;
        $solicitacaoId = isset($data['viability_request_id']) ? (int) $data['viability_request_id'] : null;

        if ($solicitacaoId !== null && ! ViabilityRequest::query()->whereKey($solicitacaoId)->exists()) {
            $data['url'] = null;
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $data,
            'lida' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
