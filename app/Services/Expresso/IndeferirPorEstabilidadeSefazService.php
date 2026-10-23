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
 * Indeferimento por estabilidade sistêmica: a SEFAZ não respondeu à consulta da
 * viabilidade do CNPJ durante toda a janela de tentativas. É procedimental, sem
 * veredito locacional (consolidated_result 'estabilidade_sistemica'), e o parecer
 * orienta o requerente a cadastrar uma nova solicitação.
 */
class IndeferirPorEstabilidadeSefazService
{
    public function __construct(
        private ViabilityRequestStateMachine $stateMachine,
        private AuditService $audit,
        private DecisionTextCatalog $textos,
    ) {}

    public function indeferir(ViabilityRequest $request, ?string $motivoTecnico): ViabilityDecision
    {
        $parecer = $this->textos->get('justificativa.indeferimento.estabilidade_sistemica');

        $decision = DB::transaction(function () use ($request, $parecer, $motivoTecnico): ViabilityDecision {
            $decision = ViabilityDecision::create([
                'viability_request_id' => $request->id,
                'flow' => 'expresso',
                'outcome' => DecisionOutcome::Indeferida,
                'consolidated_result' => 'estabilidade_sistemica',
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
                reason: 'consulta de viabilidade à SEFAZ indisponível por toda a janela de tentativas',
                publicLabel: ViabilityRequestStatus::Indeferida->publicLabel(),
            );

            $this->audit->log(
                logName: 'expresso',
                event: 'decisao',
                description: "Indeferimento por estabilidade sistêmica da solicitação #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'outcome' => DecisionOutcome::Indeferida->value,
                    'consolidado' => 'estabilidade_sistemica',
                    'motivo' => $parecer,
                    'motivo_tecnico' => $motivoTecnico,
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
