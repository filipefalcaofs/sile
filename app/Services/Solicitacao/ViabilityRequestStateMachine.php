<?php

namespace App\Services\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Support\Audit\AuditService;

/**
 * Máquina de estados da solicitação de viabilidade (HU-068 protocolar, HU-070
 * cancelar). O mapa é EXPLÍCITO e cobre só as transições ativas desta fase;
 * transição inválida lança InvalidStatusTransitionException (CA-03). Cada
 * transição válida grava a timeline (viability_request_transitions) e audita
 * (RN-002) via AuditService::log('solicitacoes','transicao',...).
 *
 * A máquina NÃO abre transação própria — o caller (08-10 protocolo, 08-12
 * cancelamento) controla a transação. As Fases 9/10/11 só ADICIONAM entradas no
 * mapa (em_analise, deferida, indeferida, ...) e listeners no evento de
 * domínio; os ganchos do enum NÃO são transicionáveis aqui.
 */
class ViabilityRequestStateMachine
{
    /**
     * Transições ativas, por valor de estado. O EP09 (fluxo expresso) só
     * ADICIONA as saídas de decisão de protocolada e a antessala aguardando_bap
     * (HU-134) — sem tocar nas entradas da Fase 8 (rascunho/protocolada→cancelada
     * seguem intactas). A decisão (deferida/indeferida) é final: não há saída.
     *
     * @var array<string, list<string>>
     */
    private const array TRANSITIONS = [
        'rascunho' => ['protocolada', 'cancelada'],
        'protocolada' => ['cancelada', 'em_analise', 'deferida', 'indeferida', 'aguardando_bap'],
        'aguardando_bap' => ['deferida', 'indeferida', 'em_analise'],
    ];

    public function __construct(private AuditService $audit) {}

    public function canTransition(ViabilityRequestStatus $from, ViabilityRequestStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(
        ViabilityRequest $request,
        ViabilityRequestStatus $to,
        ?User $actor = null,
        ?string $reason = null,
        ?string $publicLabel = null,
    ): ViabilityRequestTransition {
        $from = $request->status;

        if (! $this->canTransition($from, $to)) {
            throw InvalidStatusTransitionException::para($from, $to);
        }

        $request->forceFill(['status' => $to])->save();

        $transition = $request->transitions()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'public_label' => $publicLabel,
            'actor_user_id' => $actor?->id,
        ]);

        $this->audit->log(
            'solicitacoes',
            'transicao',
            "Transição {$from->value}→{$to->value} da solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'from' => $from->value,
                'to' => $to->value,
                'protocol_number' => $request->protocol_number,
            ],
            subject: $request,
        );

        return $transition;
    }
}
