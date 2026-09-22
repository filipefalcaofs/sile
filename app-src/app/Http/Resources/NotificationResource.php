<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Payload de leitura de uma notificação do canal database nativo (central
 * in-app, HU-090). Expõe o id (uuid), o tipo, o `data` gravado pelo
 * toDatabase() das Notifications de processo (title/summary/url), o read_at e o
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
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data,
            'lida' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
