<?php

namespace App\Services\Analise;

use App\Models\FineMeshReferral;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Malha fina (HU-136): encaminhamento humano PROVOCADO de qualquer processo para
 * revisão/auditoria interna. É ORTOGONAL ao status — liga a flag in_fine_mesh e
 * grava uma linha em fine_mesh_referrals, SEM transicionar o status (NÃO chama a
 * StateMachine): atinge QUALQUER status, inclusive deferida/indeferida (RN-001,
 * corrige o bug legado que recusava deferidos), porque é flag + tabela, não
 * estado — não muda o desfecho, só sinaliza revisão. O motivo é OBRIGATÓRIO
 * (RN-002) e cada ação audita SÍNCRONO por processo. É repetível (cada chamada é
 * uma nova linha) e em lote (RN-004 — mesmo motivo, auditoria por processo,
 * falhas isoladas). Distinta do semi-expresso (gatilho automático) e da caixa de
 * entrada (chegada automática) — RN-003.
 *
 * Serviço PURO (sem rota): o endpoint de encaminhar (incl. lote da consulta) é
 * wired em 10-15. A flag in_fine_mesh fica FORA do fillable do ViabilityRequest
 * (10-02) — escrita controlada aqui via forceFill.
 */
class MalhaFinaService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    /**
     * Encaminha um processo à malha fina. Motivo OBRIGATÓRIO (RN-002): string
     * vazia (ou só espaços) lança MalhaFinaException e NADA é gravado. Funciona em
     * qualquer status (RN-001) — não valida nem transiciona o status.
     */
    public function encaminhar(ViabilityRequest $request, User $ator, string $motivo): FineMeshReferral
    {
        return $this->registrar($request, $ator, $this->motivoObrigatorio($motivo));
    }

    /**
     * Caminho de SISTEMA (aditivo, HU-149): encaminha um processo à malha fina SEM
     * ator humano (referred_by_user_id = null) quando um detector de abuso supera o
     * limiar. Espelha registrar() — cria o fine_mesh_referrals, liga in_fine_mesh
     * via forceFill e audita por processo (marcador ator=sistema, RN-002), SEM
     * transicionar o status (RN-001 — ortogonal). NÃO altera os caminhos humanos
     * (encaminhar/encaminharLote/registrar). Motivo é obrigatório (RN-002).
     */
    public function encaminharSistema(ViabilityRequest $request, string $motivo): FineMeshReferral
    {
        $motivo = $this->motivoObrigatorio($motivo);

        return DB::transaction(function () use ($request, $motivo): FineMeshReferral {
            /** @var FineMeshReferral $referral */
            $referral = $request->fineMeshReferrals()->create([
                'referred_by_user_id' => null,
                'reason' => $motivo,
                'resolved_at' => null,
            ]);

            $request->forceFill(['in_fine_mesh' => true])->save();

            $this->audit->log('analise', 'malha-fina-encaminhar', "Processo encaminhado à malha fina pelo sistema (solicitação #{$request->id}).", [
                'viability_request_id' => $request->id,
                'protocol_number' => $request->protocol_number,
                'fine_mesh_referral_id' => $referral->id,
                'status' => $request->status->value,
                'motivo' => $motivo,
                'ator' => 'sistema',
                'ator_id' => null,
            ], $request);

            return $referral;
        });
    }

    /**
     * Encaminha em LOTE (RN-004): o mesmo motivo aplicado a vários processos, com
     * auditoria POR processo e isolamento de falhas (uma não aborta as demais). O
     * motivo é validado uma vez (precondição do lote inteiro).
     *
     * @param  iterable<int, ViabilityRequest>  $requests
     * @return array{ok: int, falhas: list<array{viability_request_id: int, motivo: string}>}
     */
    public function encaminharLote(iterable $requests, User $ator, string $motivo): array
    {
        $motivo = $this->motivoObrigatorio($motivo);

        $ok = 0;
        $falhas = [];

        foreach ($requests as $request) {
            try {
                $this->registrar($request, $ator, $motivo);
                $ok++;
            } catch (Throwable $e) {
                $falhas[] = [
                    'viability_request_id' => $request->id,
                    'motivo' => $e->getMessage(),
                ];
            }
        }

        return ['ok' => $ok, 'falhas' => $falhas];
    }

    /**
     * Dá baixa em um encaminhamento (resolved_at = agora) SEM mexer no status. Ao
     * resolver o ÚLTIMO encaminhamento aberto do processo, baixa a flag
     * in_fine_mesh — mantendo o invariante "in_fine_mesh = existe encaminhamento
     * aberto". A baixa também é auditada por processo.
     */
    public function resolver(FineMeshReferral $referral, User $ator): void
    {
        DB::transaction(function () use ($referral, $ator): void {
            $referral->forceFill(['resolved_at' => now()])->save();

            $request = $referral->viabilityRequest;

            $aindaAberto = $request->fineMeshReferrals()->whereNull('resolved_at')->exists();

            if (! $aindaAberto) {
                $request->forceFill(['in_fine_mesh' => false])->save();
            }

            $this->audit->log('analise', 'malha-fina-resolver', "Encaminhamento à malha fina resolvido (solicitação #{$request->id}).", [
                'viability_request_id' => $request->id,
                'fine_mesh_referral_id' => $referral->id,
                'in_fine_mesh' => $request->in_fine_mesh,
                'ator_id' => $ator->id,
            ], $request);
        });
    }

    /**
     * Núcleo do encaminhamento: cria o fine_mesh_referrals e liga a flag
     * in_fine_mesh (forceFill — fora do fillable), SEM tocar no status (ortogonal),
     * auditando SÍNCRONO com o motivo e o status atual. Repetível por desenho.
     */
    private function registrar(ViabilityRequest $request, User $ator, string $motivo): FineMeshReferral
    {
        return DB::transaction(function () use ($request, $ator, $motivo): FineMeshReferral {
            /** @var FineMeshReferral $referral */
            $referral = $request->fineMeshReferrals()->create([
                'referred_by_user_id' => $ator->id,
                'reason' => $motivo,
                'resolved_at' => null,
            ]);

            $request->forceFill(['in_fine_mesh' => true])->save();

            $this->audit->log('analise', 'malha-fina-encaminhar', "Processo encaminhado à malha fina (solicitação #{$request->id}).", [
                'viability_request_id' => $request->id,
                'protocol_number' => $request->protocol_number,
                'fine_mesh_referral_id' => $referral->id,
                'status' => $request->status->value,
                'motivo' => $motivo,
                'ator_id' => $ator->id,
            ], $request);

            return $referral;
        });
    }

    /**
     * Normaliza e valida o motivo (RN-002): só espaços em branco também é vazio.
     */
    private function motivoObrigatorio(string $motivo): string
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw MalhaFinaException::motivoObrigatorio();
        }

        return $motivo;
    }
}
