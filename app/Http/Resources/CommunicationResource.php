<?php

namespace App\Http\Resources;

use App\Models\Communication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de leitura de uma linha do ledger communications para o histórico
 * unificado (HU-096): canal (+rótulo), tipo (+rótulo), status HONESTO (+rótulo),
 * título/resumo, destinatário e carimbos de cada etapa. SOMENTE LEITURA — o
 * ledger é imutável (11-01).
 *
 * O error_message é diagnóstico INTERNO do canal: só entra no payload quando o
 * controller habilita (gestão). No portal ele é omitido (LGPD), mas o status
 * (ex.: 'falhou') segue visível — degradação honesta, sem expor diagnóstico.
 *
 * Consumido como prop do Inertia: o controller chama ->resolve() para entregar
 * o array puro (sem o wrapper "data" do JsonResource).
 *
 * @mixin Communication
 */
class CommunicationResource extends JsonResource
{
    private bool $withErrorMessage = false;

    /**
     * Habilita o error_message no payload (só a gestão vê o diagnóstico interno).
     */
    public function withErrorMessage(bool $value = true): static
    {
        $this->withErrorMessage = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'title' => $this->title,
            'summary' => $this->summary,
            'recipient' => $this->recipient?->only('id', 'name'),
            'queued_at' => $this->queued_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Diagnóstico interno do canal: só na gestão (LGPD — ver docblock).
            ...($this->withErrorMessage ? ['error_message' => $this->error_message] : []),
        ];
    }
}
