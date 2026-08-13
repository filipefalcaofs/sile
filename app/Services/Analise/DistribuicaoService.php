<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStage;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Caixa do setor (HU-080/081): distribuição (gestor) e assunção (analista) dos
 * processos em análise. O processo cai na caixa do setor (sector_id) e é
 * atribuído a um analista do setor SEM sair da caixa (RN-004 — modelo SAPS que
 * cobre férias/ausências: qualquer analista do setor mantém visibilidade e pode
 * assumir). A atribuição recalcula o SLA para a etapa de análise (HU-144, fonte
 * única AnalysisSlaService) e audita por processo de forma SÍNCRONA (RN-006 —
 * não depende de evento, lição das Fases 8/9). O lote (RN-007) itera item a
 * item, auditando cada um e isolando falhas.
 *
 * As colunas de atribuição/SLA (assigned_user_id, assigned_at, analysis_*) ficam
 * FORA do fillable do ViabilityRequest (10-02) — escrita controlada por este
 * serviço via forceFill.
 */
class DistribuicaoService
{
    public function __construct(
        private AnalysisSlaService $sla,
        private AuditService $audit,
    ) {}

    /**
     * Atribui o processo a um analista do setor (o gestor distribui — RN-003).
     * Lança DistribuicaoException se o processo não está em caixa ou o analista
     * não pertence ao setor; nesse caso nada é gravado.
     */
    public function distribuir(ViabilityRequest $request, User $analista, ?User $ator = null): void
    {
        $this->atribuir($request, $analista, $ator ?? $analista, 'distribuir', 'Processo distribuído a analista do setor');
    }

    /**
     * Distribui em LOTE (RN-007): aplica distribuir a cada processo, auditando
     * por item e isolando falhas — uma falha não aborta as demais.
     *
     * @param  iterable<int, ViabilityRequest>  $requests
     * @return array{ok: int, falhas: list<array{viability_request_id: int, motivo: string}>}
     */
    public function distribuirLote(iterable $requests, User $analista, ?User $ator = null): array
    {
        $ok = 0;
        $falhas = [];

        foreach ($requests as $request) {
            try {
                $this->distribuir($request, $analista, $ator);
                $ok++;
            } catch (DistribuicaoException $e) {
                $falhas[] = [
                    'viability_request_id' => $request->id,
                    'motivo' => $e->getMessage(),
                ];
            }
        }

        return ['ok' => $ok, 'falhas' => $falhas];
    }

    /**
     * O próprio analista do setor pega um processo da caixa para si (HU-081 —
     * registra responsabilidade técnica). Mesmo efeito da distribuição, com o
     * analista como ator e o evento de auditoria 'assumir'.
     */
    public function assumir(ViabilityRequest $request, User $analista): void
    {
        $this->atribuir($request, $analista, $analista, 'assumir', 'Analista assumiu o processo da caixa do setor');
    }

    /**
     * Núcleo da atribuição: valida o vínculo de setor, grava assigned_user_id/
     * assigned_at e recalcula o SLA (etapa análise) via forceFill, SEM alterar
     * sector_id (não sai da caixa) nem o status (segue em_analise), e audita
     * SÍNCRONO por processo (RN-006: quem/quando/de qual setor).
     */
    private function atribuir(ViabilityRequest $request, User $analista, User $ator, string $evento, string $descricao): void
    {
        $this->garantirVinculoDeSetor($request, $analista);

        DB::transaction(function () use ($request, $analista, $ator, $evento, $descricao): void {
            $startedAt = now();

            $request->forceFill([
                'assigned_user_id' => $analista->id,
                'assigned_at' => $startedAt,
                'analysis_stage' => AnalysisStage::Analise,
                'analysis_stage_started_at' => $startedAt,
                'analysis_due_at' => $this->sla->dueAtFor(AnalysisStage::Analise, $startedAt),
            ])->save();

            $this->audit->log('analise', $evento, "{$descricao} (solicitação #{$request->id}).", [
                'viability_request_id' => $request->id,
                'protocol_number' => $request->protocol_number,
                'sector_id' => $request->sector_id,
                'assigned_user_id' => $analista->id,
                'ator_id' => $ator->id,
            ], $request);
        });
    }

    /**
     * Garante que o processo está numa caixa de setor e que o analista pertence a
     * esse setor (RN-004 — atribuição dentro da caixa; HU-081 — só analista
     * vinculado assume/é distribuído). Não muta nada; só valida.
     */
    private function garantirVinculoDeSetor(ViabilityRequest $request, User $analista): void
    {
        if ($request->sector_id === null) {
            throw DistribuicaoException::semSetor($request);
        }

        $pertence = $analista->sectors()->where('sectors.id', $request->sector_id)->exists();

        if (! $pertence) {
            throw DistribuicaoException::analistaForaDoSetor($analista, $request);
        }
    }
}
