<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Caixa do setor (HU-080/081): distribuição (gestor) e assunção (analista) dos
 * processos em análise. O processo cai na caixa do setor (sector_id) e é
 * atribuído a um analista do setor SEM sair da caixa (RN-004 — modelo SAPS que
 * cobre férias/ausências: qualquer analista do setor mantém visibilidade e pode
 * assumir). Quem distribui em qualquer setor (o apoio) entrega a qualquer
 * analista, e o processo passa para o setor do analista. A atribuição recalcula o SLA para a etapa de análise (HU-144, fonte
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
        private AnalysisStatusStateMachine $status,
    ) {}

    /**
     * Atribui o processo a um analista do setor (o gestor distribui — RN-003).
     * Lança DistribuicaoException se o processo não está em caixa ou o analista
     * não pertence ao setor; nesse caso nada é gravado.
     */
    public function distribuir(ViabilityRequest $request, User $analista, ?User $ator = null, ?int $setorEscolhido = null): void
    {
        $ator ??= $analista;

        $this->atribuir($request, $analista, $ator, 'distribuir', "Processo distribuído a {$analista->name}", $this->distribuiEmQualquerSetorPara($ator, $request), $setorEscolhido);
    }

    /**
     * Distribui em LOTE (RN-007): aplica distribuir a cada processo, auditando
     * por item e isolando falhas — uma falha não aborta as demais.
     *
     * @param  iterable<int, ViabilityRequest>  $requests
     * @return array{ok: int, falhas: list<array{viability_request_id: int, motivo: string}>}
     */
    public function distribuirLote(iterable $requests, User $analista, ?User $ator = null, ?int $setorEscolhido = null): array
    {
        $ok = 0;
        $falhas = [];

        foreach ($requests as $request) {
            try {
                $this->distribuir($request, $analista, $ator, $setorEscolhido);
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
     * Distribuição em lote entre VÁRIOS analistas (Fase 3): cada grupo leva seus
     * processos ao seu analista, reusando o caminho de atribuição (SLA + auditoria
     * por processo) e isolando falhas por item. A decisão de quem recebe o quê é
     * do Apoio — este serviço apenas executa a alocação recebida.
     *
     * @param  list<array{analista: User, requests: iterable<int, ViabilityRequest>}>  $grupos
     * @return array{ok: int, falhas: list<array{viability_request_id: int, motivo: string}>}
     */
    public function distribuirMultiplo(array $grupos, User $ator): array
    {
        $ok = 0;
        $falhas = [];

        foreach ($grupos as $grupo) {
            $resumo = $this->distribuirLote($grupo['requests'], $grupo['analista'], $ator);
            $ok += $resumo['ok'];
            $falhas = array_merge($falhas, $resumo['falhas']);
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
        // Para conclusão o processo está na fila de quem conclui: qualquer analista do setor o pega.
        $paraConclusao = $request->analysis_status === AnalysisStatus::ParaConclusao;

        if (! $paraConclusao && $request->assigned_user_id !== null && (int) $request->assigned_user_id !== $analista->id) {
            throw DistribuicaoException::jaAtribuido($request);
        }

        $this->atribuir($request, $analista, $analista, 'assumir', "{$analista->name} assumiu o processo da caixa do setor", false, null);
    }

    /**
     * Redistribuição (apoio/gestor troca a analista responsável): só ANTES da
     * conclusão da análise e SEM recalcular o SLA — o prazo é do processo, não
     * da pessoa; trocar o responsável não zera atraso. Audita a troca com a
     * analista anterior e a nova.
     */
    public function redistribuir(ViabilityRequest $request, User $novaAnalista, User $ator, ?int $setorEscolhido = null): void
    {
        $setorDestino = $this->setorDeDestino($request, $novaAnalista, $this->distribuiEmQualquerSetor($ator), $setorEscolhido);

        $status = $request->analysis_status;

        if ($status === null || ! $status->permiteRedistribuicao()) {
            throw DistribuicaoException::analiseConcluida($request);
        }

        DB::transaction(function () use ($request, $novaAnalista, $ator, $setorDestino): void {
            $analistaAnterior = $request->assigned_user_id;
            $setorAnterior = $request->sector_id;

            $request->forceFill([
                'sector_id' => $setorDestino,
                'assigned_user_id' => $novaAnalista->id,
                'assigned_at' => now(),
            ])->save();

            $this->audit->log('analise', 'redistribuir', "Processo redistribuído para {$novaAnalista->name} (solicitação #{$request->id}).", [
                'viability_request_id' => $request->id,
                'protocol_number' => $request->protocol_number,
                'sector_id' => $request->sector_id,
                'setor_anterior_id' => $setorAnterior,
                'analista_anterior_id' => $analistaAnterior,
                'assigned_user_id' => $novaAnalista->id,
                'analista_nome' => $novaAnalista->name,
                'ator_id' => $ator->id,
            ], $request);

            $this->colocarNaCaixaDoAnalista($request, $novaAnalista, $ator);
        });
    }

    /**
     * Quem distribui em qualquer setor (o apoio) não fica preso aos próprios
     * setores nem ao setor do processo.
     */
    public function distribuiEmQualquerSetor(User $ator): bool
    {
        return $ator->can('distribuir-qualquer-setor');
    }

    /**
     * Para conclusão não fica preso ao setor: quem distribui entrega a qualquer
     * analista, e o processo passa para o setor dele. Nos demais casos vale a
     * regra do setor, salvo para quem distribui em qualquer setor.
     */
    public function distribuiEmQualquerSetorPara(User $ator, ViabilityRequest $request): bool
    {
        if ($this->distribuiEmQualquerSetor($ator)) {
            return true;
        }

        return $request->analysis_status === AnalysisStatus::ParaConclusao && $ator->can('distribuir-processos');
    }

    /**
     * Setor em que o processo fica depois da atribuição. Na regra do setor, é o
     * setor do processo, e o analista precisa estar nele. Em qualquer setor, o
     * processo passa para o setor do analista: o escolhido, o do processo se o
     * analista já está nele, ou o único setor do analista. Não grava nada.
     */
    public function setorDeDestino(ViabilityRequest $request, User $analista, bool $qualquerSetor, ?int $setorEscolhido = null): int
    {
        if (! $qualquerSetor) {
            $this->garantirVinculoDeSetor($request, $analista);

            return (int) $request->sector_id;
        }

        $setoresDoAnalista = $analista->sectors()->pluck('sectors.id')->map(fn ($id): int => (int) $id);

        if ($setorEscolhido !== null) {
            if (! $setoresDoAnalista->contains($setorEscolhido)) {
                throw DistribuicaoException::setorForaDoAnalista($analista, $request);
            }

            return $setorEscolhido;
        }

        if ($request->sector_id !== null && $setoresDoAnalista->contains((int) $request->sector_id)) {
            return (int) $request->sector_id;
        }

        return match ($setoresDoAnalista->count()) {
            0 => throw DistribuicaoException::analistaSemSetor($analista, $request),
            1 => $setoresDoAnalista->first(),
            default => throw DistribuicaoException::setorAmbiguo($analista, $request),
        };
    }

    /**
     * Núcleo da atribuição: resolve o setor de destino, grava assigned_user_id/
     * assigned_at e recalcula o SLA (etapa análise) via forceFill, sem alterar o
     * status (segue em_analise), e audita SÍNCRONO por processo (RN-006:
     * quem/quando/de qual setor). Na regra do setor o processo não sai da caixa.
     */
    private function atribuir(ViabilityRequest $request, User $analista, User $ator, string $evento, string $descricao, bool $qualquerSetor, ?int $setorEscolhido): void
    {
        $setorDestino = $this->setorDeDestino($request, $analista, $qualquerSetor, $setorEscolhido);
        $this->garantirVistoriadorEmVistoria($request, $analista);

        DB::transaction(function () use ($request, $analista, $ator, $evento, $descricao, $setorDestino): void {
            $startedAt = now();
            $setorAnterior = $request->sector_id;

            $request->forceFill([
                'sector_id' => $setorDestino,
                'assigned_user_id' => $analista->id,
                'assigned_at' => $startedAt,
            ]);

            // Para conclusão o processo já passou pela análise: trocar quem conclui não reinicia o prazo.
            if ($request->analysis_status !== AnalysisStatus::ParaConclusao) {
                $request->forceFill([
                    'analysis_stage' => AnalysisStage::Analise,
                    'analysis_stage_started_at' => $startedAt,
                    'analysis_due_at' => $this->sla->dueAtFor(AnalysisStage::Analise, $startedAt),
                ]);
            }

            $request->save();

            $this->audit->log('analise', $evento, "{$descricao} (solicitação #{$request->id}).", [
                'viability_request_id' => $request->id,
                'protocol_number' => $request->protocol_number,
                'sector_id' => $request->sector_id,
                'setor_anterior_id' => $setorAnterior,
                'assigned_user_id' => $analista->id,
                'analista_nome' => $analista->name,
                'ator_id' => $ator->id,
            ], $request);

            $this->colocarNaCaixaDoAnalista($request, $analista, $ator);
        });
    }

    /**
     * A atribuição tira o processo do eixo "para distribuir" e o deixa na
     * Caixa de entrada do analista, no status em que ele começa o trabalho.
     * Vistoria e convite já estão em outro eixo e não são reabertos.
     */
    private function colocarNaCaixaDoAnalista(ViabilityRequest $request, User $analista, User $ator): void
    {
        $passos = match ($request->analysis_status) {
            null => [AnalysisStatus::ParaDistribuir, AnalysisStatus::Encaminhado, AnalysisStatus::Analisar],
            AnalysisStatus::ParaDistribuir => [AnalysisStatus::Encaminhado, AnalysisStatus::Analisar],
            AnalysisStatus::Encaminhado => [AnalysisStatus::Analisar],
            default => [],
        };

        foreach ($passos as $proximo) {
            $motivo = match ($proximo) {
                AnalysisStatus::Encaminhado => "Encaminhado para {$analista->name}.",
                AnalysisStatus::Analisar => "Na caixa de entrada de {$analista->name}.",
                default => null,
            };

            $this->status->transition($request, $proximo, $ator, $motivo);
        }
    }

    /**
     * Processo no eixo de vistoria (Vistoriar/Vistoriado) só pode ser atribuído
     * a vistoriador (preencher-ficha-vistoria) — a distribuição/assunção é
     * específica nesse fluxo: quem recebe é quem abre e preenche a ficha.
     * Redistribuição em vistoria já é bloqueada pelo enum
     * (permiteRedistribuicao), então a regra vive no caminho de atribuição.
     */
    private function garantirVistoriadorEmVistoria(ViabilityRequest $request, User $analista): void
    {
        $emVistoria = in_array(
            $request->analysis_status,
            [AnalysisStatus::Vistoriar, AnalysisStatus::Vistoriado],
            true,
        );

        if ($emVistoria && ! $analista->can('preencher-ficha-vistoria')) {
            throw DistribuicaoException::alvoSemPermissaoDeVistoria($analista, $request);
        }
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
