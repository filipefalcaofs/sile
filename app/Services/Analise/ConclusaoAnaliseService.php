<?php

namespace App\Services\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ConclusionOutcome;
use App\Enums\ResultadoViabilidade;
use App\Enums\SefazNotificationEvent;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Listeners\ComunicarResultadoRegin;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\EscritorioVirtual\DesvincularInscricaoService;
use App\Services\Expresso\TvlNumberGenerator;
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
 *
 * Processo já finalizado pode ter o resultado trocado por qualquer outro.
 */
class ConclusaoAnaliseService
{
    public function __construct(
        private AnaliseTecnicaDecisionService $decisao,
        private AnalysisRecordService $fichas,
        private ViabilityRequestStateMachine $stateMachine,
        private AnalysisStatusStateMachine $analysisStatus,
        private DesvincularInscricaoService $desvincular,
        private AuditService $audit,
        private ComunicarResultadoRegin $regin,
        private TvlNumberGenerator $tvl,
    ) {}

    public function concluir(ViabilityRequest $request, User $ator, ConclusionOutcome $resultado, ?string $parecer = null, bool $exigirFichaSalva = false, bool $confirmarDivergencia = false, ?ValidadeDeferimento $validade = null): void
    {
        $this->decisao->exigirDistribuicaoDaConclusao($request, $ator);

        $parecer = $this->parecer($parecer);
        $motivo = $this->motivo($resultado, $parecer);

        if (! $confirmarDivergencia) {
            $this->avisarDivergencia($request, $resultado);
        }

        if ($exigirFichaSalva && $request->status === ViabilityRequestStatus::EmAnalise) {
            $this->exigirFichaSalva($request);
        }

        // Renovação tem tipo e validade próprios, no diálogo "Decidir processo".
        $validade = $request->isRenewal() ? null : $validade;

        if ($request->status !== $resultado->status()) {
            if ($this->jaFinalizado($request->status) && ! $this->encerramentoDeDeferido($request->status, $resultado)) {
                $this->revisarConclusao($request, $ator, $resultado, $parecer, $validade);
            } elseif ($resultado->encerraDeferimento()) {
                $this->aplicarEncerramento($request, $ator, $resultado, $parecer);
            } else {
                $this->concluirPelaFicha($request, $ator, $resultado, $parecer, $confirmarDivergencia, $validade);
            }
        } elseif ($resultado === ConclusionOutcome::Deferido && $validade !== null) {
            $this->atualizarValidade($request, $ator, $validade);
        }

        $this->fecharEixo($request->fresh() ?? $request, $ator, $motivo);

        if ($resultado !== ConclusionOutcome::Deferido) {
            $this->desvincularSedeSeAtiva($request->fresh() ?? $request, $ator, $motivo);
        }
    }

    /**
     * Deferir com atividade indeferida na ficha, ou indeferir sem nenhuma,
     * pede confirmação do analista em vez de barrar.
     *
     * @throws DivergenciaConclusaoException
     */
    private function avisarDivergencia(ViabilityRequest $request, ConclusionOutcome $resultado): void
    {
        if (! in_array($resultado, [ConclusionOutcome::Deferido, ConclusionOutcome::Indeferido], true)) {
            return;
        }

        $ficha = $request->currentAnalysisRecord()->first();

        if ($ficha === null) {
            return;
        }

        $atividades = array_values(array_filter($ficha->per_cnae ?? [], fn ($item): bool => is_array($item) && isset($item['cnae'])));

        if ($atividades === []) {
            return;
        }

        try {
            $this->decisao->recusarDecisaoContraditoria($atividades, $resultado->decisionOutcome());
        } catch (DomainException $e) {
            throw new DivergenciaConclusaoException($e->getMessage(), previous: $e);
        }
    }

    private function jaFinalizado(ViabilityRequestStatus $status): bool
    {
        return in_array($status, [
            ViabilityRequestStatus::Deferida,
            ViabilityRequestStatus::Indeferida,
            ViabilityRequestStatus::Desativado,
            ViabilityRequestStatus::Cassado,
            ViabilityRequestStatus::Revogado,
        ], true);
    }

    private function encerramentoDeDeferido(ViabilityRequestStatus $origem, ConclusionOutcome $resultado): bool
    {
        return $origem === ViabilityRequestStatus::Deferida && $resultado->encerraDeferimento();
    }

    /**
     * Processo já finalizado: quem tem a permissão de concluir pode trocar
     * o resultado por qualquer outro. A decisão existente é reescrita (uma
     * por processo) e a troca fica na linha do tempo e na auditoria.
     */
    private function revisarConclusao(ViabilityRequest $request, User $ator, ConclusionOutcome $resultado, ?string $parecer, ?ValidadeDeferimento $validade = null): void
    {
        DB::transaction(function () use ($request, $ator, $resultado, $parecer, $validade): void {
            $anterior = $request->status;
            $destino = $resultado->status();
            $decisao = $request->decision()->first();

            $consolidado = match ($resultado) {
                ConclusionOutcome::Deferido => ResultadoViabilidade::Permitido->value,
                ConclusionOutcome::Indeferido => ResultadoViabilidade::NaoPermitido->value,
                default => $resultado->value,
            };

            $dados = [
                'outcome' => $resultado->decisionOutcome(),
                'consolidated_result' => $consolidado,
                'reason' => $parecer ?? $decisao?->reason,
                'decided_by_user_id' => $ator->id,
                'decided_at' => now(),
                'tvl_kind' => $resultado === ConclusionOutcome::Deferido ? $validade?->tipo : null,
                'tvl_valid_until' => $resultado === ConclusionOutcome::Deferido ? $validade?->validoAte(now())?->toDateString() : null,
            ];

            if ($decisao === null) {
                $decisao = ViabilityDecision::create($dados + [
                    'viability_request_id' => $request->id,
                    'flow' => 'analise_tecnica',
                    'tvl_product_number' => null,
                    'per_cnae' => [],
                    'rules_versions' => [],
                    'fundamentacao' => [],
                ]);
            } else {
                $decisao->update($dados);
            }

            if ($resultado === ConclusionOutcome::Deferido && $decisao->tvl_product_number === null && ! $request->isRenewal()) {
                $decisao->update(['tvl_product_number' => $this->tvl->paraProcesso($request)]);
            }

            $this->stateMachine->transition(
                $request,
                $destino,
                $ator,
                reason: $this->motivo($resultado, $parecer),
                publicLabel: $destino->publicLabel(),
                force: true,
            );

            $this->audit->log(
                logName: 'analise',
                event: 'conclusao-analise',
                description: "Conclusão de análise revisada ({$anterior->value} → {$resultado->value}) do processo #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'resultado_anterior' => $anterior->value,
                    'resultado' => $resultado->value,
                    'parecer' => $parecer,
                    'decided_by' => $ator->id,
                ],
                subject: $request,
                result: $resultado->value,
            );
        });
    }

    private function concluirPelaFicha(ViabilityRequest $request, User $ator, ConclusionOutcome $resultado, ?string $parecer, bool $confirmarDivergencia = false, ?ValidadeDeferimento $validade = null): void
    {
        $record = $request->currentAnalysisRecord()->first();

        if ($record === null) {
            throw new DomainException('A ficha de análise ainda não foi criada para este processo.');
        }

        // A ficha salva e ainda em rascunho é finalizada aqui, ficando só para consulta.
        if (! $record->isFinalizada()) {
            $record = $this->fichas->finalizar($record, $ator);
        }

        $deferido = $resultado === ConclusionOutcome::Deferido;

        $this->decisao->decide(
            $record,
            $ator,
            $resultado->decisionOutcome(),
            $parecer,
            tvlKind: $deferido ? $validade?->tipo : null,
            tvlValidUntil: $deferido ? $validade?->validoAte(now())?->toDateString() : null,
            confirmarDivergencia: $confirmarDivergencia,
        );
    }

    /**
     * Processo já deferido concluído de novo como Deferido (ex.: malha fina
     * sobre o expresso): só o tipo e a validade mudam, na decisão existente,
     * sem novo TVL. A troca fica na auditoria.
     */
    private function atualizarValidade(ViabilityRequest $request, User $ator, ValidadeDeferimento $validade): void
    {
        $decisao = $request->decision()->first();

        if ($decisao === null) {
            return;
        }

        $anteriorTipo = $decisao->tvl_kind?->value;
        $anteriorValidoAte = $decisao->tvl_valid_until?->toDateString();
        $validoAte = $validade->validoAte(now())?->toDateString();

        $decisao->update([
            'tvl_kind' => $validade->tipo,
            'tvl_valid_until' => $validoAte,
        ]);

        $this->audit->log(
            logName: 'analise',
            event: 'validade-deferimento',
            description: "Tipo e validade da viabilidade atualizados no processo #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'tipo' => $validade->tipo->value,
                'valido_ate' => $validoAte,
                'anterior_tipo' => $anteriorTipo,
                'anterior_valido_ate' => $anteriorValidoAte,
                'decided_by' => $ator->id,
            ],
            subject: $request,
        );
    }

    /**
     * A conclusão de um processo em análise exige, no mínimo, uma ficha salva
     * pelo analista (a revisão que só nasceu pré-analisada pelo motor não conta).
     */
    private function exigirFichaSalva(ViabilityRequest $request): void
    {
        $salva = $request->analysisRecords()
            ->where(fn ($ficha) => $ficha
                ->whereNotNull('analyst_user_id')
                ->orWhere('status', AnalysisRecordStatus::Finalizada->value))
            ->exists();

        if (! $salva) {
            throw new DomainException('Salve ao menos uma ficha de análise antes de concluir o processo.');
        }
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

        $decisao = null;

        DB::transaction(function () use ($request, $ator, $resultado, $parecer, $destino, &$decisao): void {
            if ($request->status === ViabilityRequestStatus::EmAnalise && $request->decision()->doesntExist()) {
                $decisao = ViabilityDecision::create([
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

        // Encerrar sem decisão prévia é o único desfecho que a JUCEB ainda não
        // conhece: ela recebe o parecer (indeferido). Um processo já deferido
        // foi finalizado lá no deferimento e não é reenviado.
        if ($decisao !== null) {
            $this->regin->handle(new ResultadoEmitido($request, $decisao));
        }
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
