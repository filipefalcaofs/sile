<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Envio MANUAL de um processo à análise técnica (tela T06 — relatório de teste
 * SEDUR). É o gatilho de retaguarda que leva um processo à FILA da análise, no
 * EIXO OPERACIONAL (AnalysisStatus), reusando os MESMOS blocos do encaminhamento
 * automático do fluxo expresso (FluxoExpressoService::encaminharAnalise):
 * AnalysisStatusStateMachine (transição null→para_distribuir + timeline +
 * auditoria RN-002), AnalysisSlaService (materializa o prazo da etapa de
 * distribuição) e o evento de domínio EncaminhadoParaAnalise (gatilho da
 * pré-análise, after-commit).
 *
 * Diferente do expresso — que só encaminha a partir de `protocolada` —, este
 * serviço NÃO toca o status canônico (ViabilityRequestStatus) nem exige um
 * status de origem: por isso QUALQUER status pode ser enviado (OPEN-F-2), e vale
 * indistintamente para sede e abrigado (OPEN-F-3 — não há ramo por tipo).
 *
 * IDEMPOTÊNCIA (CA-E-03): sob Cache::lock por processo, recarrega e checa se o
 * eixo operacional já foi iniciado (`analysis_status !== null`). Se já está na
 * análise, é NO-OP — não regride o status, não cria nova tramitação, não audita
 * o envio e não redispara o evento. Devolve `false`; um envio efetivo devolve
 * `true`.
 */
class EnviarParaAnaliseService
{
    public function __construct(
        private AnalysisStatusStateMachine $stateMachine,
        private AnalysisSlaService $sla,
        private AuditService $audit,
    ) {}

    /**
     * Envia o processo à fila de análise de forma idempotente. Devolve `true`
     * quando o envio foi efetivado agora e `false` quando o processo já estava
     * na análise (no-op).
     */
    public function enviar(ViabilityRequest $request, ?User $actor = null): bool
    {
        $ttl = (int) config('sile.expresso.lock.ttl_segundos', 10);

        return Cache::lock("analise:enviar:{$request->id}", $ttl)->block(
            $ttl,
            fn (): bool => $this->enviarSobLock($request, $actor),
        );
    }

    private function enviarSobLock(ViabilityRequest $request, ?User $actor): bool
    {
        $request->refresh();

        // Idempotência: o eixo operacional já foi iniciado (qualquer situação de
        // análise/distribuição/convite/vistoria). Não duplica a tramitação.
        if ($request->analysis_status !== null) {
            return false;
        }

        DB::transaction(function () use ($request, $actor): void {
            // Transição inicial do eixo operacional (null → para_distribuir): a
            // máquina grava a timeline interna + auditoria da transição (RN-002).
            $this->stateMachine->transition(
                $request,
                AnalysisStatus::ParaDistribuir,
                $actor,
                reason: 'Envio manual para análise (gestão)',
            );

            // Materializa o prazo da fila (etapa distribuição) — espelha o
            // encaminhamento do expresso. Colunas fora do fillable → forceFill.
            $startedAt = now();
            $request->forceFill([
                'analysis_stage' => AnalysisStage::Distribuicao,
                'analysis_stage_started_at' => $startedAt,
                'analysis_due_at' => $this->sla->dueAtFor(AnalysisStage::Distribuicao, $startedAt),
            ])->save();

            // Auditoria dedicada do envio manual (RN-002) — além da auditoria da
            // transição já gravada pela state machine.
            $this->audit->log(
                logName: 'analise',
                event: 'enviar-analise',
                description: "Envio manual à análise técnica do processo #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'status_origem' => $request->status->value,
                    'is_virtual_office' => (bool) $request->is_virtual_office,
                ],
                subject: $request,
            );
        });

        // APÓS o commit: gatilho da pré-análise (listener auto-descoberto). Só
        // envios efetivados geram efeitos; a auditoria já está gravada.
        EncaminhadoParaAnalise::dispatch($request);

        return true;
    }
}
