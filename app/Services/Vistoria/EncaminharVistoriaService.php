<?php

namespace App\Services\Vistoria;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\InspectionReferral;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisStatusStateMachine;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Encaminhamento à vistoria como handoff REAL (não é malha fina): o processo
 * em análise vai à caixa do setor de vistoria — setor de destino obrigatório,
 * eixo operacional a Vistoriar pela state machine e responsável desatribuído,
 * caindo em "Para distribuir" para o apoio distribuir a um vistoriador.
 *
 * A vistoria COMPÕE o processo (sem número novo): a origem (setor/analista) é
 * registrada no referral e a conclusão da ficha devolve o processo à origem
 * (devolverAoAnalista, chamado pelo InspectionService).
 */
class EncaminharVistoriaService
{
    public function __construct(
        private AnalysisStatusStateMachine $statusMachine,
        private AuditService $audit,
    ) {}

    /**
     * Executa o handoff: referral com a origem + troca de setor + desatribuição
     * + transição a Vistoriar, tudo numa transação, auditado (RN-002).
     */
    public function encaminhar(
        ViabilityRequest $processo,
        Sector $setorVistoria,
        string $motivo,
        User $ator,
    ): InspectionReferral {
        if ($processo->status !== ViabilityRequestStatus::EmAnalise) {
            throw EncaminharVistoriaException::foraDeAnalise($processo);
        }

        if (! $this->statusMachine->canTransition($processo->analysis_status, AnalysisStatus::Vistoriar)) {
            throw EncaminharVistoriaException::eixoNaoPermite($processo);
        }

        return DB::transaction(function () use ($processo, $setorVistoria, $motivo, $ator): InspectionReferral {
            $referral = InspectionReferral::create([
                'viability_request_id' => $processo->id,
                'encaminhado_por_user_id' => $ator->id,
                'setor_origem_id' => $processo->sector_id,
                'analista_origem_user_id' => $processo->assigned_user_id,
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => $motivo,
            ]);

            $processo->forceFill([
                'sector_id' => $setorVistoria->id,
                'assigned_user_id' => null,
                'assigned_at' => null,
            ])->save();

            $this->statusMachine->transition($processo, AnalysisStatus::Vistoriar, $ator, $motivo);

            $this->audit->log(
                logName: 'vistoria',
                event: 'vistoria-encaminhar',
                description: "Processo #{$processo->id} encaminhado à vistoria (setor #{$setorVistoria->id})",
                properties: [
                    'viability_request_id' => $processo->id,
                    'protocol_number' => $processo->protocol_number,
                    'inspection_referral_id' => $referral->id,
                    'setor_origem_id' => $referral->setor_origem_id,
                    'analista_origem_user_id' => $referral->analista_origem_user_id,
                    'setor_vistoria_id' => $setorVistoria->id,
                    'motivo' => $motivo,
                ],
                subject: $processo,
            );

            return $referral;
        });
    }

    /**
     * Retorno automático da vistoria concluída: com referral registrado, o
     * processo volta ao setor e ao analista de origem e o eixo operacional
     * retorna a EmAnalise (aresta Vistoriado → EmAnalise da state machine).
     * Sem referral (vistoria marcada manualmente), não faz nada — o retorno
     * é manual pelo dropdown.
     */
    public function devolverAoAnalista(ViabilityRequest $processo, User $vistoriador): void
    {
        $referral = InspectionReferral::query()
            ->where('viability_request_id', $processo->id)
            ->latest('id')
            ->first();

        if ($referral === null) {
            return;
        }

        DB::transaction(function () use ($processo, $referral, $vistoriador): void {
            $processo->forceFill([
                'sector_id' => $referral->setor_origem_id ?? $processo->sector_id,
                'assigned_user_id' => $referral->analista_origem_user_id,
                'assigned_at' => now(),
            ])->save();

            $this->statusMachine->transition(
                $processo,
                AnalysisStatus::EmAnalise,
                $vistoriador,
                'Vistoria concluída — retorno à análise de origem.',
            );

            $this->audit->log(
                logName: 'vistoria',
                event: 'vistoria-devolver',
                description: "Processo #{$processo->id} devolvido à análise após a conclusão da vistoria",
                properties: [
                    'viability_request_id' => $processo->id,
                    'inspection_referral_id' => $referral->id,
                    'setor_origem_id' => $referral->setor_origem_id,
                    'analista_origem_user_id' => $referral->analista_origem_user_id,
                ],
                subject: $processo,
            );
        });
    }
}
