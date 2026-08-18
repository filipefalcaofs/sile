<?php

namespace App\Services\Solicitacao;

use App\Enums\ResultadoViabilidade;
use App\Enums\ViabilityRequestStatus;
use App\Events\SolicitacaoProtocolada;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Support\Facades\DB;

/**
 * Protocolo da solicitação de viabilidade (HU-068) — o coração da fase.
 *
 * Em UMA transação: gera o número único (ProtocolNumberGenerator com
 * protocol_sequences->lockForUpdate(), unique como defesa final), grava
 * protocol_number/protocoled_at e transiciona rascunho→protocolada pela
 * ViabilityRequestStateMachine (que grava a timeline e audita — RN-002).
 *
 * Antes da transação (para NÃO consumir número quando bloqueado):
 *  - dados mínimos presentes (empresa, imóvel/polígono, área, CNAE principal);
 *  - documentos obrigatórios atendidos — DocumentRequirementResolver::missing()
 *    vazio; senão BLOQUEIA com aviso (HU-067).
 *
 * A simulação (HU-141) é orientativa e NÃO bloqueia: quando a tendência é de
 * indeferimento e o requerente opta por prosseguir mesmo assim, registra a
 * ciência (applicant_proceeded_despite) — direito de petição (RN-002). O
 * snapshot já persistido (08-09) é CONGELADO, nunca reprocessado (RN-003).
 *
 * Após o commit dispara SolicitacaoProtocolada — o primeiro evento de domínio
 * do sistema. A auditoria do protocolo NÃO depende do evento: a transição
 * síncrona já garante a trilha mesmo se um listener falhar.
 */
class ProtocolarSolicitacaoService
{
    public function __construct(
        private ProtocolNumberGenerator $generator,
        private ViabilityRequestStateMachine $stateMachine,
        private DocumentRequirementResolver $documents,
    ) {}

    public function protocol(ViabilityRequest $request, User $actor, bool $proceedDespite = false): ViabilityRequest
    {
        $this->guardStatus($request);
        $this->guardMinimumData($request);
        $this->guardRequiredDocuments($request);

        // Ciência só faz sentido quando a tendência simulada é desfavorável
        // (indeferimento); sem simulação desfavorável não há o que registrar.
        $proceededDespite = $proceedDespite && $this->unfavorableTendency($request);

        DB::transaction(function () use ($request, $actor, $proceededDespite): void {
            $request->forceFill([
                'protocol_number' => $this->generator->generate(),
                'protocoled_at' => now(),
                'applicant_proceeded_despite' => $proceededDespite,
            ])->save();

            $this->stateMachine->transition(
                $request,
                ViabilityRequestStatus::Protocolada,
                $actor,
                publicLabel: ViabilityRequestStatus::Protocolada->publicLabel(),
            );
        });

        // Disparo APÓS o commit: a transação já fechou aqui. O evento implementa
        // ShouldDispatchAfterCommit como defesa adicional contra disparo precoce.
        SolicitacaoProtocolada::dispatch($request);

        return $request;
    }

    /**
     * Só rascunho protocola (a policy já barra no controller; aqui é a defesa
     * do domínio para chamadas diretas — e evita consumir número fora de hora).
     */
    private function guardStatus(ViabilityRequest $request): void
    {
        if ($request->status !== ViabilityRequestStatus::Rascunho) {
            throw InvalidStatusTransitionException::para($request->status, ViabilityRequestStatus::Protocolada);
        }
    }

    /**
     * Dados mínimos da solicitação (HU-068 FA-01) — bloqueia com a lista dos
     * campos pendentes antes de gerar qualquer número.
     */
    private function guardMinimumData(ViabilityRequest $request): void
    {
        $missing = [];

        if ($request->company_id === null) {
            $missing[] = 'empresa';
        }

        if (blank($request->property_polygon_geojson)) {
            $missing[] = 'imóvel (polígono)';
        }

        if ($request->used_area_m2 === null || (float) $request->used_area_m2 <= 0.0) {
            $missing[] = 'área utilizada';
        }

        if (! $request->primaryCnae()->exists()) {
            $missing[] = 'atividade principal (CNAE)';
        }

        if ($missing !== []) {
            throw SolicitacaoIncompletaException::paraCampos($missing);
        }
    }

    /**
     * Documentos obrigatórios (HU-067): missing() vazio para prosseguir, senão
     * bloqueia com a lista dos requisitos pendentes (aviso, nunca silencioso).
     */
    private function guardRequiredDocuments(ViabilityRequest $request): void
    {
        $missing = $this->documents->missing($request);

        if ($missing->isNotEmpty()) {
            throw DocumentacaoIncompletaException::paraRequisitos(
                $missing->pluck('name')->all(),
            );
        }
    }

    /**
     * Tendência desfavorável = simulação consolidada em "não permitido"
     * (indeferimento). "pendente" é indeterminado, não desfavorável.
     */
    private function unfavorableTendency(ViabilityRequest $request): bool
    {
        return $request->simulation_resultado === ResultadoViabilidade::NaoPermitido->value;
    }
}
