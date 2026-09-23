<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ConclusionOutcome;
use App\Enums\SefazNotificationEvent;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\EscritorioVirtual\DesvincularInscricaoService;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Tramitação "Conclusão de Análise": o analista escolhe o resultado
 * (Deferido, Indeferido, Desativado, Cassado ou Revogado). A malha fina
 * usa o mesmo serviço na baixa.
 *
 * Deferido e Indeferido passam pela decisão da ficha finalizada.
 * Desativado, Cassado e Revogado encerram o processo em análise ou
 * substituem um deferimento já emitido — sem gerar TVL e sem fingir
 * envio à SEFAZ. Se a inscrição é sede de escritório virtual, esses
 * três resultados (e o indeferimento) desvinculam pelo serviço real.
 */
class ConclusaoAnaliseService
{
    public function __construct(
        private AnaliseTecnicaDecisionService $decisao,
        private ViabilityRequestStateMachine $stateMachine,
        private AnalysisStatusStateMachine $analysisStatus,
        private DesvincularInscricaoService $desvincular,
        private AuditService $audit,
    ) {}

    public function concluir(ViabilityRequest $request, User $ator, ConclusionOutcome $resultado, ?string $parecer = null): void
    {
        $parecer = $this->parecer($parecer);
        $motivo = $this->motivo($resultado, $parecer);

        if ($request->status === ViabilityRequestStatus::Deferida && $resultado === ConclusionOutcome::Indeferido) {
            throw new DomainException(
                'Processo já deferido não pode ser indeferido. A conclusão usa Desativado, Cassado ou Revogado.',
            );
        }

        if ($request->status !== $resultado->status()) {
            if ($resultado->encerraDeferimento()) {
                $this->aplicarEncerramento($request, $ator, $resultado, $parecer);
            } else {
                $this->concluirPelaFicha($request, $ator, $resultado);
            }
        }

        $this->fecharEixo($request->fresh() ?? $request, $ator, $motivo);

        if ($resultado !== ConclusionOutcome::Deferido) {
            $this->desvincularSedeSeAtiva($request->fresh() ?? $request, $ator, $motivo);
        }
    }

    private function concluirPelaFicha(ViabilityRequest $request, User $ator, ConclusionOutcome $resultado): void
    {
        $record = $request->currentAnalysisRecord()->first();

        if ($record === null) {
            throw new DomainException('A ficha de análise ainda não foi criada para este processo.');
        }

        $this->decisao->decide($record, $ator, $resultado->decisionOutcome());
    }

    private function aplicarEncerramento(
        ViabilityRequest $request,
        User $ator,
        ConclusionOutcome $resultado,
        ?string $parecer,
    ): void {
        $origem = $request->status;
        $destino = $resultado->status();

        if (! in_array($origem, [ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::Deferida], true)) {
            throw new DomainException(
                "Desativado, Cassado e Revogado partem da análise em curso ou de um processo já deferido. Situação atual: {$origem->label()}.",
            );
        }

        if (! $this->stateMachine->canTransition($origem, $destino)) {
            throw new DomainException(
                "Não é possível concluir como {$resultado->label()} a partir de {$origem->label()}.",
            );
        }

        DB::transaction(function () use ($request, $ator, $resultado, $parecer, $destino): void {
            if ($request->status === ViabilityRequestStatus::EmAnalise && $request->decision()->doesntExist()) {
                ViabilityDecision::create([
                    'viability_request_id' => $request->id,
                    'flow' => 'analise_tecnica',
                    'outcome' => $resultado->decisionOutcome(),
                    'consolidated_result' => $resultado->value,
                    'tvl_product_number' => null,
                    'per_cnae' => [],
                    'rules_versions' => [],
                    'fundamentacao' => [],
                    'reason' => $parecer,
                    'decided_by_user_id' => $ator->id,
                    'decided_at' => now(),
                ]);
            }

            $this->stateMachine->transition(
                $request,
                $destino,
                $ator,
                reason: $this->motivo($resultado, $parecer),
                publicLabel: $destino->publicLabel(),
            );

            $this->audit->log(
                logName: 'analise',
                event: 'conclusao-analise',
                description: "Conclusão de análise ({$resultado->value}) do processo #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'resultado' => $resultado->value,
                    'parecer' => $parecer,
                    'decided_by' => $ator->id,
                ],
                subject: $request,
                result: $resultado->value,
            );
        });
    }

    private function fecharEixo(ViabilityRequest $request, User $ator, string $motivo): void
    {
        if ($request->analysis_status === AnalysisStatus::AnaliseConcluida) {
            return;
        }

        $pode = $this->analysisStatus->canTransition($request->analysis_status, AnalysisStatus::AnaliseConcluida);

        $this->analysisStatus->transition(
            $request,
            AnalysisStatus::AnaliseConcluida,
            $ator,
            $motivo,
            force: ! $pode,
        );
    }

    private function desvincularSedeSeAtiva(ViabilityRequest $request, User $ator, string $motivo): void
    {
        $lock = VirtualOfficeInscriptionLock::query()
            ->where('sede_viability_request_id', $request->id)
            ->where('active', true)
            ->first();

        if ($lock === null) {
            return;
        }

        $this->desvincular->desvincular($lock, $motivo, $ator, SefazNotificationEvent::SedePerdeuCondicao);
    }

    private function parecer(?string $parecer): ?string
    {
        $parecer = $parecer !== null ? trim($parecer) : null;

        return $parecer === '' ? null : $parecer;
    }

    private function motivo(ConclusionOutcome $resultado, ?string $parecer): string
    {
        $motivo = 'Conclusão de análise: '.$resultado->label();

        return $parecer === null ? $motivo : $motivo.'. '.$parecer;
    }
}
