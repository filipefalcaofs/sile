<?php

namespace App\Services\Expresso;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Regra da SEDUR: pedido que só exclui atividades, sem mudança de endereço, é
 * deferido no expresso, sem taxa, nunca fica na análise. O processo que já está
 * na análise volta ao motor mesmo assumido: a atribuição é liberada e o convite
 * aberto é cancelado antes da decisão.
 */
class DeferirExclusaoAtividadeService
{
    public function __construct(
        private FluxoExpressoService $motor,
        private AuditService $audit,
    ) {}

    public function elegivel(ViabilityRequest $processo): bool
    {
        if ($processo->status !== ViabilityRequestStatus::EmAnalise
            || $processo->decision()->exists()
            || $processo->alteraEndereco()) {
            return false;
        }

        if ($processo->exclusivamenteExclusao()) {
            return true;
        }

        $comparacao = $processo->comparacao_cnaes;

        return is_array($comparacao)
            && ($comparacao['incluidas'] ?? null) === []
            && ($comparacao['invalidas'] ?? []) === []
            && ($comparacao['excluidas'] ?? []) !== [];
    }

    public function deferir(ViabilityRequest $processo): DecisionResult
    {
        DB::transaction(function () use ($processo): void {
            $this->liberarAtribuicao($processo);
            $this->cancelarConvitesAbertos($processo);
        });

        return $this->motor->decide($processo);
    }

    private function liberarAtribuicao(ViabilityRequest $processo): void
    {
        $analista = $processo->assigned_user_id;

        if ($analista === null && in_array($processo->analysis_status, [null, AnalysisStatus::ParaDistribuir], true)) {
            return;
        }

        $processo->forceFill([
            'assigned_user_id' => null,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
        ])->save();

        $this->audit->log(
            logName: 'analise',
            event: 'atribuicao-liberada',
            description: "Atribuição da solicitação #{$processo->id} liberada: só exclusão de atividade, deferida no expresso",
            properties: ['viability_request_id' => $processo->id, 'analista_anterior_id' => $analista],
            subject: $processo,
        );
    }

    private function cancelarConvitesAbertos(ViabilityRequest $processo): void
    {
        $parecer = 'Cancelado: pedido só de exclusão de atividade, deferido no expresso.';

        foreach ($processo->pendencies()->where('status', AnalysisPendencyStatus::Aberta)->get() as $convite) {
            $convite->forceFill([
                'status' => AnalysisPendencyStatus::Cancelada,
                'parecer' => $parecer,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => null,
            ])->save();

            $this->audit->log(
                logName: 'analise',
                event: 'convite-cancelado',
                description: "Convite #{$convite->id} cancelado pelo deferimento da exclusão de atividade da solicitação #{$processo->id}",
                properties: ['viability_request_id' => $processo->id, 'analysis_pendency_id' => $convite->id, 'parecer' => $parecer],
                subject: $processo,
            );
        }
    }
}
