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
 * registrada no referral. Concluída a ficha, o processo fica Vistoriado com o
 * vistoriador, que o encaminha de volta à Caixa do Setor (encaminharAoSetor).
 * O retorno é ao setor de origem, sem responsável: o setor distribui de novo.
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

        // O eixo nasce em para_distribuir e o analista encaminha da ficha sem
        // passar pelo dropdown "Em análise". A aresta direta da state machine
        // só existe a partir de EmAnalise; dos estados anteriores o salto é
        // forçado e fica registrado na timeline.
        if (! $this->eixoAbertoParaVistoria($processo->analysis_status)) {
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

            $this->statusMachine->transition(
                $processo,
                AnalysisStatus::Vistoriar,
                $ator,
                $motivo,
                ! $this->statusMachine->canTransition($processo->analysis_status, AnalysisStatus::Vistoriar),
            );

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
     * O processo pode ir à vistoria em qualquer momento da análise, inclusive
     * em Para conclusão. Ficam de fora a análise já concluída, o convite
     * expirado e o processo que já está na vistoria.
     */
    private function eixoAbertoParaVistoria(?AnalysisStatus $status): bool
    {
        return ! in_array($status, [
            AnalysisStatus::AnaliseConcluida,
            AnalysisStatus::ConviteExpirado,
            AnalysisStatus::Vistoriar,
        ], true);
    }

    /**
     * Encaminha o processo da vistoria à Caixa do Setor a qualquer momento, com
     * a ficha concluída ou não, e ele sai da Caixa de Vistoria. Com a origem
     * registrada, volta ao setor de origem; sem ela (vistoria marcada à mão),
     * segue no setor atual. Fica sem responsável, para o setor distribuir a um
     * analista, ou já designado ao analista do setor indicado. Concluída a
     * ficha, o status continua Vistoriado até a analista retomar a análise.
     */
    public function encaminharAoSetor(ViabilityRequest $processo, User $ator, ?User $analista = null): void
    {
        if (! in_array($processo->analysis_status, [AnalysisStatus::Vistoriar, AnalysisStatus::Vistoriado], true)) {
            throw EncaminharVistoriaException::processoForaDeVistoriado(
                $processo,
                InspectionReferral::query()->where('viability_request_id', $processo->id)->exists(),
            );
        }

        $referral = InspectionReferral::query()
            ->where('viability_request_id', $processo->id)
            ->latest('id')
            ->first();

        $setorDeRetorno = $referral?->setor_origem_id ?? $processo->sector_id;

        if ($analista !== null && ! $this->analistaDoSetor($analista, $setorDeRetorno)) {
            throw EncaminharVistoriaException::analistaForaDoSetor($analista);
        }

        DB::transaction(function () use ($processo, $referral, $ator, $analista, $setorDeRetorno): void {
            $processo->forceFill([
                'sector_id' => $setorDeRetorno,
                'assigned_user_id' => $analista?->id,
                'assigned_at' => $analista !== null ? now() : null,
            ])->save();

            $referral?->forceFill(['devolvido_em' => now()])->save();

            if ($processo->analysis_status === AnalysisStatus::Vistoriar) {
                $this->retornarDaVistoriaAberta($processo, $ator, $analista);
            }

            $this->audit->log(
                logName: 'vistoria',
                event: 'vistoria-devolver',
                description: "Processo #{$processo->id} encaminhado à Caixa do Setor após a conclusão da vistoria",
                properties: [
                    'viability_request_id' => $processo->id,
                    'inspection_referral_id' => $referral?->id,
                    'setor_origem_id' => $referral?->setor_origem_id,
                    'analista_origem_user_id' => $referral?->analista_origem_user_id,
                    'ator_id' => $ator->id,
                    'analista_designado_id' => $analista?->id,
                    'analista_designado_nome' => $analista?->name,
                ],
                subject: $processo,
            );
        });
    }

    /**
     * Vistoria ainda aberta: o processo sai de Vistoriar para a fila do setor
     * (Para distribuir, ou Encaminhado ao analista indicado).
     */
    private function retornarDaVistoriaAberta(ViabilityRequest $processo, User $ator, ?User $analista): void
    {
        $this->statusMachine->transition(
            $processo,
            $analista !== null ? AnalysisStatus::Encaminhado : AnalysisStatus::ParaDistribuir,
            $ator,
            'Encaminhado de volta à Caixa do Setor antes de concluir a vistoria.',
            true,
        );
    }

    /**
     * Analistas do setor que recebe o processo de volta (o de origem da vistoria
     * ou, sem origem, o atual), para o encaminhamento já designar um deles.
     *
     * @return list<array{id: int, name: string}>
     */
    public function analistasDoRetorno(ViabilityRequest $processo): array
    {
        $referral = InspectionReferral::query()
            ->where('viability_request_id', $processo->id)
            ->latest('id')
            ->first();
        $setor = $referral?->setor_origem_id ?? $processo->sector_id;

        if ($setor === null) {
            return [];
        }

        return User::query()
            ->permission('analisar-processos')
            ->whereHas('sectors', fn ($setores) => $setores->where('sectors.id', $setor))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $usuario): array => ['id' => $usuario->id, 'name' => $usuario->name])
            ->all();
    }

    private function analistaDoSetor(User $analista, ?int $setor): bool
    {
        return $setor !== null
            && $analista->can('analisar-processos')
            && $analista->sectors()->where('sectors.id', $setor)->exists();
    }
}
