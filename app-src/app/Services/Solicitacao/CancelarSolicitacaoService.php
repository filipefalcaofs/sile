<?php

namespace App\Services\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Cancelamento da solicitação de viabilidade (HU-070): o requerente interrompe
 * um processo indevido ou duplicado enquanto NÃO decidido.
 *
 * Os estados em que o cancelamento é permitido são PARAMETRIZÁVEIS
 * (solicitacao.cancelamento.estados_cancelaveis — HU-014); o default honesto é
 * rascunho + protocolada (antes da decisão), e a definição fina é pendência
 * SEDUR. Fora dos estados canceláveis o cancelamento é BLOQUEADO com aviso e
 * auditado (RN-002, CA-03) — sem nada cancelar (anti-fachada).
 *
 * Dentro dos estados, grava cancelled_at/cancelled_reason/cancelled_by_user_id
 * e transiciona o status para Cancelada pela ViabilityRequestStateMachine (que
 * grava a timeline e audita a transição), tudo em UMA transação.
 */
class CancelarSolicitacaoService
{
    public function __construct(
        private ViabilityRequestStateMachine $stateMachine,
        private AuditService $audit,
    ) {}

    public function cancel(ViabilityRequest $request, User $actor, string $reason): void
    {
        $cancelableStates = $this->cancelableStates();

        if (! in_array($request->status->value, $cancelableStates, true)) {
            $this->audit->log(
                'solicitacoes',
                'cancelamento-bloqueado',
                "Cancelamento bloqueado: a solicitação #{$request->id} está no estado {$request->status->value}, fora dos estados canceláveis.",
                properties: [
                    'viability_request_id' => $request->id,
                    'status' => $request->status->value,
                    'estados_cancelaveis' => $cancelableStates,
                    'protocol_number' => $request->protocol_number,
                ],
                subject: $request,
                result: 'bloqueado',
            );

            throw CancelamentoNaoPermitidoException::para($request->status, $cancelableStates);
        }

        DB::transaction(function () use ($request, $actor, $reason): void {
            $request->forceFill([
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
                'cancelled_by_user_id' => $actor->id,
            ])->save();

            $this->stateMachine->transition(
                $request,
                ViabilityRequestStatus::Cancelada,
                $actor,
                reason: $reason,
                publicLabel: 'Cancelada a pedido do requerente',
            );
        });
    }

    /**
     * Estados em que a solicitação pode ser cancelada pelo requerente — lidos do
     * parâmetro (efeito sem deploy, HU-014). Default honesto enquanto não há
     * decisão (rascunho + protocolada); a definição fina é pendência SEDUR.
     *
     * @return list<string>
     */
    private function cancelableStates(): array
    {
        return Settings::get(
            'solicitacao.cancelamento.estados_cancelaveis',
            config('sile.solicitacao.cancelamento.estados_cancelaveis', ['rascunho', 'protocolada']),
        );
    }
}
