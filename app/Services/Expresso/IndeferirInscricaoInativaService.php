<?php

namespace App\Services\Expresso;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Indeferimento por inscrição imobiliária que não está ativa na SEFAZ
 * Municipal. Como o indeferimento por prazo do BAP, é procedimental: não há
 * veredito locacional, então consolidated_result é o marcador
 * 'inscricao_inativa' e per_cnae e rules_versions ficam vazios. O parecer sai
 * do catálogo de textos (justificativa.indeferimento.inscricao_inativa).
 *
 * Decisão imutável, transição pela máquina de estados e auditoria síncrona na
 * mesma transação; o ResultadoEmitido só é disparado depois do commit.
 */
class IndeferirInscricaoInativaService
{
    public function __construct(
        private ViabilityRequestStateMachine $stateMachine,
        private AuditService $audit,
        private DecisionTextCatalog $textos,
    ) {}

    public function indeferir(ViabilityRequest $request, ?string $situacaoCadastral): ViabilityDecision
    {
        $parecer = $this->textos->get('justificativa.indeferimento.inscricao_inativa');

        $decision = DB::transaction(function () use ($request, $parecer, $situacaoCadastral): ViabilityDecision {
            $decision = ViabilityDecision::create([
                'viability_request_id' => $request->id,
                'flow' => 'expresso',
                'outcome' => DecisionOutcome::Indeferida,
                'consolidated_result' => 'inscricao_inativa',
                'tvl_product_number' => null,
                'per_cnae' => [],
                'rules_versions' => [],
                'fundamentacao' => [],
                'reason' => $parecer,
                'decided_by_user_id' => null,
                'decided_at' => now(),
            ]);

            $this->stateMachine->transition(
                $request,
                ViabilityRequestStatus::Indeferida,
                null,
                reason: 'inscrição imobiliária com pendência na SEFAZ Municipal',
                publicLabel: ViabilityRequestStatus::Indeferida->publicLabel(),
            );

            $this->audit->log(
                logName: 'expresso',
                event: 'decisao',
                description: "Indeferimento por inscrição imobiliária inativa da solicitação #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'outcome' => DecisionOutcome::Indeferida->value,
                    'consolidado' => 'inscricao_inativa',
                    'motivo' => $parecer,
                    'inscricao' => $request->property_registration,
                    'situacao_cadastral' => $situacaoCadastral,
                ],
                subject: $request,
                result: 'indeferida',
            );

            return $decision;
        });

        ResultadoEmitido::dispatch($request, $decision);

        return $decision;
    }
}
