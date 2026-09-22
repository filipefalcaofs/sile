<?php

namespace App\Services\Expresso;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Indeferimento por prazo BAP (HU-134) — "indeferido sem atuação". É o motor
 * real da rotina dormente: quando uma solicitação fica parada em aguardando_bap
 * além do prazo de atuação na Junta, o processo é INDEFERIDO de verdade,
 * reusando o caminho de emissão do fluxo expresso (09-05): decisão imutável +
 * transição pela StateMachine + auditoria SÍNCRONA + ResultadoEmitido.
 *
 * Honestidade do registro: o indeferimento é PROCEDIMENTAL (faltou atuação no
 * prazo), NÃO um veredito locacional "nao_permitido". Por isso consolidated_result
 * é o marcador 'sem_atuacao_bap' e per_cnae/rules_versions ficam vazios — não há
 * reavaliação dos motores aqui; quem carrega o motivo legível é o reason
 * 'indeferido sem atuação' (RN-002).
 *
 * RN-003 (sem SEFAZ): NÃO aciona o gateway da SEFAZ direto. O ResultadoEmitido
 * dispara os listeners da Wave 4 — ComunicarResultadoRegin (comunica o parecer)
 * e EnviarViabilidadeSefaz (IGNORA o indeferimento). O evento é despachado APÓS
 * o commit; a auditoria autoritativa é síncrona e independe dele.
 */
class IndeferirSemBapService
{
    public function __construct(
        private ViabilityRequestStateMachine $stateMachine,
        private AuditService $audit,
    ) {}

    /**
     * Indefere uma solicitação parada em aguardando_bap (prazo de atuação na
     * Junta vencido). Guarda de estado: só age sobre aguardando_bap — chamar
     * sobre outro estado é erro de programação (o caminho normal de decisão é o
     * FluxoExpressoService), então lança InvalidArgumentException sem criar nada.
     */
    public function indeferir(ViabilityRequest $request): ViabilityDecision
    {
        if ($request->status !== ViabilityRequestStatus::AguardandoBap) {
            throw new InvalidArgumentException(
                "Indeferimento por prazo BAP exige status aguardando_bap; a solicitação #{$request->id} está em {$request->status->value}.",
            );
        }

        $decision = DB::transaction(function () use ($request): ViabilityDecision {
            $decision = ViabilityDecision::create([
                'viability_request_id' => $request->id,
                'flow' => 'expresso',
                'outcome' => DecisionOutcome::Indeferida,
                'consolidated_result' => 'sem_atuacao_bap',
                'tvl_product_number' => null,
                'per_cnae' => [],
                'rules_versions' => [],
                'fundamentacao' => [],
                'reason' => 'indeferido sem atuação',
                'decided_by_user_id' => null,
                'decided_at' => now(),
            ]);

            $this->stateMachine->transition(
                $request,
                ViabilityRequestStatus::Indeferida,
                null,
                reason: 'indeferido sem atuação',
                publicLabel: ViabilityRequestStatus::Indeferida->publicLabel(),
            );

            $this->audit->log(
                logName: 'expresso',
                event: 'decisao',
                description: "Indeferimento por prazo BAP (sem atuação) da solicitação #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'outcome' => DecisionOutcome::Indeferida->value,
                    'consolidado' => 'sem_atuacao_bap',
                    'motivo' => 'indeferido sem atuação',
                    'bap_due_at' => $request->bap_due_at?->toIso8601String(),
                ],
                subject: $request,
                result: 'indeferida',
            );

            return $decision;
        });

        // APÓS o commit: comunica ao Regin e a SEFAZ ignora (RN-003) — Wave 4.
        ResultadoEmitido::dispatch($request, $decision);

        return $decision;
    }
}
