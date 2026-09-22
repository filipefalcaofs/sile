<?php

namespace App\Http\Resources;

use App\Models\AbuseAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de leitura de um alerta de abuso (HU-149) para o painel de revisão
 * humana (gestao/abuso/index). Expõe a regra disparada, a severidade e o status
 * (value + label), a janela analisada, as evidências (já minimizadas pelos
 * detectores), o processo vinculado (protocolo + id p/ o link), se foi
 * encaminhado à malha fina (ortogonal ao status) e a resolução quando houver
 * (quem deu baixa, quando e a justificativa — RN-003). Por minimização (LGPD)
 * só devolve o que a triagem precisa; nada de PII crua.
 *
 * Consumido como prop do Inertia (->resolve()), sem o wrapper "data". A tela é
 * construída em 12-11.
 *
 * @mixin AbuseAlert
 */
class AbuseAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_key' => $this->rule_key,
            'severity' => [
                'value' => $this->severity->value,
                'label' => $this->severity->label(),
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'detected_at' => $this->detected_at?->toIso8601String(),
            'window' => [
                'start' => $this->window_start?->toIso8601String(),
                'end' => $this->window_end?->toIso8601String(),
            ],
            // Evidências do detector (total/limite/ids/identificador) — já são o
            // recorte mínimo e legível da ocorrência, não o payload cru.
            'evidence' => $this->evidence,
            'processo' => $this->resumoProcesso(),
            // A malha fina é ortogonal ao status: só sinaliza que o motor já
            // encaminhou (acima do limiar), nunca decide o processo.
            'encaminhado_malha_fina' => $this->fine_mesh_referral_id !== null,
            'resolucao' => $this->resumoResolucao(),
        ];
    }

    /**
     * Processo de viabilidade vinculado (protocolo + id p/ o link), null quando
     * o alerta não tem processo concreto (ex.: padrão por contador agregado).
     *
     * @return array{id: int, protocol_number: string|null}|null
     */
    private function resumoProcesso(): ?array
    {
        if ($this->viability_request_id === null) {
            return null;
        }

        return [
            'id' => $this->viability_request_id,
            'protocol_number' => $this->viabilityRequest?->protocol_number,
        ];
    }

    /**
     * Resolução do alerta (baixa humana): quem confirmou/descartou, quando e a
     * justificativa obrigatória (RN-003). Null enquanto o alerta está aberto.
     *
     * @return array{resolved_by: array{id: int, nome: string|null}|null, resolved_at: string|null, justification: string|null}|null
     */
    private function resumoResolucao(): ?array
    {
        if (! $this->status->isResolved()) {
            return null;
        }

        $resolvedBy = $this->resolvedBy;

        return [
            'resolved_by' => $resolvedBy === null ? null : [
                'id' => $resolvedBy->getKey(),
                'nome' => $resolvedBy->getAttribute('name'),
            ],
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'justification' => $this->justification,
        ];
    }
}
