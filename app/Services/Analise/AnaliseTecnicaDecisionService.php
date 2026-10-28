<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ResultadoViabilidade;
use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\Auditoria\DecisionTraceBuilder;
use App\Services\EscritorioVirtual\AbrigadoResolver;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;
use App\Services\Expresso\TvlNumberGenerator;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Decisão técnica HUMANA (HU-081 a HU-085) — a contraparte do FluxoExpressoService
 * para os casos que a lei manda o humano decidir. `decide()` conclui o processo a
 * partir da FICHA FINALIZADA do analista (NÃO do resolver puro): defere quando
 * TODAS as CNAEs estão deferidas na ficha e indefere quando QUALQUER uma está
 * indeferida (RN-004), gravando na MESMA viability_decisions da Fase 9 com
 * flow='analise_tecnica' e decided_by_user_id=analista (≠ null — distingue do
 * fluxo automático, onde é null=sistema).
 *
 * Diferença CRÍTICA em relação ao expresso: o humano PODE DEFERIR o caso PENDENTE
 * (sem a zona oficial — Quadro 10 indisponível) com fundamentação própria da
 * ficha; é exatamente o caso que exige um humano. A decisão nasce da ficha, não
 * de um veredito consolidado do motor.
 *
 * A transação espelha FluxoExpressoService::emitir: cria a ViabilityDecision
 * (per_cnae/rules_versions/fundamentação DA FICHA; número TVL só no deferimento),
 * transiciona em_analise→deferida|indeferida (= encerramento HU-085) e AUDITA
 * SÍNCRONO (HU-074) — tudo antes do commit. O ResultadoEmitido é disparado SÓ
 * após o commit (os 3 listeners da Fase 9 reusam; Regin/SEFAZ seguem bloqueados
 * honestos → Fase 13). A auditoria autoritativa NÃO depende do evento.
 */
class AnaliseTecnicaDecisionService
{
    public function __construct(
        private ViabilityRequestStateMachine $stateMachine,
        private TvlNumberGenerator $tvl,
        private AuditService $audit,
        private DecisionTraceBuilder $traceBuilder,
        private SedeEscritorioVirtualGatilho $sedeGatilho,
        private AbrigadoResolver $abrigadoResolver,
        private AnalysisStatusStateMachine $analysisStatus,
    ) {}

    /**
     * Conclui o processo a partir da ficha finalizada. Exige a revisão FINALIZADA
     * (rascunho não decide — CA-03) e o processo em em_analise. Devolve o
     * AnaliseDecisionResult com o desfecho, a decisão criada e se o evento foi
     * disparado.
     *
     * @throws DomainException quando a ficha não está finalizada, o processo não
     *                         está em análise ou a ficha não tem CNAEs para decidir
     */
    public function decide(
        AnalysisRecord $record,
        User $analista,
        ?DecisionOutcome $escolhido = null,
        ?string $parecer = null,
        ?TvlKind $tvlKind = null,
        ?string $tvlValidUntil = null,
        ?bool $condicionantesAtendidos = null,
        bool $confirmarDivergencia = false,
    ): AnaliseDecisionResult {
        if (! $record->isFinalizada()) {
            throw new DomainException(
                'A ficha de análise precisa estar Finalizada para concluir o processo (rascunho não decide).',
            );
        }

        $request = $record->viabilityRequest()->firstOrFail();

        if ($request->status !== ViabilityRequestStatus::EmAnalise) {
            throw new DomainException(
                "A decisão técnica só conclui processos em análise; situação atual: {$request->status->value}.",
            );
        }

        $this->exigirDistribuicaoDaConclusao($request, $analista);

        $perCnae = $this->perCnaeDaFicha($record);

        if ($perCnae === []) {
            throw new DomainException('A ficha finalizada não possui atividades (CNAEs) para decidir.');
        }

        if ($escolhido !== null && ! in_array($escolhido, [DecisionOutcome::Deferida, DecisionOutcome::Indeferida], true)) {
            throw new DomainException('Deferido e indeferido são os únicos resultados lidos da ficha.');
        }

        $derivado = $this->outcome($perCnae);

        if (! $confirmarDivergencia) {
            $this->recusarDecisaoContraditoria($perCnae, $escolhido);
        }

        if ($request->isRenewal() && $condicionantesAtendidos === false) {
            $escolhido = DecisionOutcome::Indeferida;
        }

        $outcome = $escolhido ?? $derivado;

        if ($request->isRenewal() && $outcome === DecisionOutcome::Deferida && ($tvlKind === null || blank($tvlValidUntil))) {
            throw new DomainException(
                'Informe o tipo (provisória ou definitiva) e a nova data de validade para deferir a renovação.',
            );
        }

        $justificativa = $outcome === DecisionOutcome::Indeferida
            ? $this->justificativaDoIndeferimento($record, $perCnae, $parecer)
            : $this->parecerDoDeferimento($parecer);

        try {
            $decision = DB::transaction(function () use ($request, $record, $analista, $perCnae, $outcome, $justificativa, $tvlKind, $tvlValidUntil): ViabilityDecision {
                $decision = $this->registrarDecisao(
                    $request,
                    $record,
                    $analista,
                    $perCnae,
                    $outcome,
                    $justificativa,
                    $tvlKind,
                    $tvlValidUntil,
                );

                $this->fecharEixo($request->fresh() ?? $request, $analista, $outcome);

                return $decision;
            });
        } catch (QueryException $e) {
            // Robustez idempotente (mesma defesa da Fase 9): se uma corrida rara
            // escapar, a unique(viability_request_id) barra a 2ª decisão — recarrega
            // a real já gravada e devolve sem redisparar o evento (não é fachada).
            $existente = $request->decision()->first();

            if ($existente !== null) {
                return new AnaliseDecisionResult($existente->outcome, $existente, emitted: false);
            }

            throw $e;
        }

        // APÓS o commit: só a decisão efetivada gera efeitos (notificação HU-073;
        // Regin HU-100 / SEFAZ HU-103 bloqueados → Fase 13). A auditoria já está
        // gravada na transação, independente do evento.
        ResultadoEmitido::dispatch($request, $decision);

        return new AnaliseDecisionResult($outcome, $decision, emitted: true);
    }

    /**
     * Processo em Para conclusão é distribuído antes de concluir: só quem o
     * recebeu (conclusao_encaminhada_para_user_id) conclui.
     *
     * @throws DomainException
     */
    public function exigirDistribuicaoDaConclusao(ViabilityRequest $request, User $ator): void
    {
        if ($request->analysis_status === AnalysisStatus::ParaConclusao
            && $request->conclusao_encaminhada_para_user_id !== $ator->id) {
            throw new DomainException('Processo na aba Analisados: distribua-o antes. Só quem o recebeu pode concluí-lo.');
        }
    }

    /**
     * Decidido o processo, o status de tramitação passa a "Análise concluída"
     * (com o resultado, no rótulo), por qualquer caminho de decisão da análise.
     */
    private function fecharEixo(ViabilityRequest $request, User $analista, DecisionOutcome $outcome): void
    {
        if ($request->analysis_status === AnalysisStatus::AnaliseConcluida) {
            return;
        }

        $this->analysisStatus->transition(
            $request,
            AnalysisStatus::AnaliseConcluida,
            $analista,
            'Análise concluída: '.$outcome->label().'.',
            force: ! $this->analysisStatus->canTransition($request->analysis_status, AnalysisStatus::AnaliseConcluida),
        );
    }

    /**
     * Grava a decisão dentro da transação: ViabilityDecision (flow analise_tecnica,
     * decided_by analista, número TVL só no deferimento) → transição
     * em_analise→deferida|indeferida (encerramento HU-085) → auditoria SÍNCRONA
     * (HU-074). Tudo antes do commit.
     *
     * @param  list<array<string, mixed>>  $perCnae
     */
    private function registrarDecisao(
        ViabilityRequest $request,
        AnalysisRecord $record,
        User $analista,
        array $perCnae,
        DecisionOutcome $outcome,
        ?string $justificativa,
        ?TvlKind $tvlKind = null,
        ?string $tvlValidUntil = null,
    ): ViabilityDecision {
        // Sede de escritório virtual (RN-EV-02/03/04): deferida + o analista
        // confirmou sede na ficha + o processo tem o CNAE gatilho (8211-3/00).
        // Só aí trava a inscrição e o produto carrega a condicionante EV.
        $isSede = $outcome === DecisionOutcome::Deferida
            && $record->is_virtual_office_hq === true
            && $this->sedeGatilho->temCnaeGatilho($request);

        $consolidated = $this->consolidatedResult($outcome, $record);
        $fundamentacao = $this->fundamentacao($perCnae);

        if ($isSede) {
            $consolidated = ResultadoViabilidade::PermitidoComCondicoes->value;
            $fundamentacao[] = (string) config('viabiliza.analise.escritorio_virtual.condicionante_sede', '');
        }

        // Abrigado de escritório virtual (RN-EV-05): a inscrição tem sede ativa e
        // os CNAEs estão na Lista EV — o produto referencia o nº TVL da sede.
        $abrigado = $this->abrigadoResolver->resolve($request);

        $decision = ViabilityDecision::create([
            'viability_request_id' => $request->id,
            'analysis_record_id' => $record->id,
            'flow' => 'analise_tecnica',
            'outcome' => $outcome,
            'consolidated_result' => $consolidated,
            'is_virtual_office_hq' => $isSede,
            'is_virtual_office_tenant' => $abrigado !== null,
            'virtual_office_hq_tvl_number' => $abrigado['hq_tvl_number'] ?? null,
            'tvl_product_number' => $outcome === DecisionOutcome::Deferida && ! $request->isRenewal()
                ? $this->tvl->paraProcesso($request)
                : null,
            'tvl_kind' => $outcome === DecisionOutcome::Deferida ? $tvlKind : null,
            'tvl_valid_until' => $outcome === DecisionOutcome::Deferida ? $tvlValidUntil : null,
            'per_cnae' => $perCnae,
            'rules_versions' => $record->engine_rules_versions ?? [],
            'fundamentacao' => $fundamentacao,
            'decision_trace' => $this->decisionTrace($record, $perCnae),
            'reason' => $justificativa,
            'decided_by_user_id' => $analista->id,
            'decided_at' => now(),
        ]);

        $destino = $outcome === DecisionOutcome::Deferida
            ? ViabilityRequestStatus::Deferida
            : ViabilityRequestStatus::Indeferida;

        $this->stateMachine->transition($request, $destino, $analista, publicLabel: $destino->publicLabel());

        if ($isSede) {
            // Trava a inscrição pela sede + marca a categoria derivada (RN-EV-03).
            $request->forceFill(['is_virtual_office' => true])->save();

            VirtualOfficeInscriptionLock::create([
                'property_registration' => $request->property_registration,
                'sede_viability_request_id' => $request->id,
                'active' => true,
                'locked_at' => now(),
            ]);
        }

        $this->audit->log(
            logName: 'analise',
            event: 'decisao',
            description: "Decisão técnica ({$outcome->value}) do processo #{$request->id} (revisão {$record->revision})",
            properties: [
                'viability_request_id' => $request->id,
                'protocol_number' => $request->protocol_number,
                'revision' => $record->revision,
                'outcome' => $outcome->value,
                'tvl_product_number' => $decision->tvl_product_number,
                'decided_by' => $analista->id,
                'sede_escritorio_virtual' => $isSede,
                'por_cnae' => $this->perCnaeResumo($perCnae),
            ],
            subject: $request,
            result: $outcome->value,
            rulesVersion: $this->rulesVersionRepresentativa($record->engine_rules_versions),
        );

        return $decision;
    }

    /**
     * Rótulos das atividades marcadas como indeferidas na ficha.
     *
     * @param  list<array<string, mixed>>  $perCnae
     * @return list<string>
     */
    private function indeferidas(array $perCnae): array
    {
        $rotulos = [];

        foreach ($perCnae as $item) {
            if (($item['status_escolhido'] ?? null) === DecisionOutcome::Indeferida->value) {
                $rotulo = trim((string) ($item['cnae_formatado'] ?? $item['cnae'] ?? ''));
                $rotulos[] = $rotulo !== '' ? $rotulo : 'atividade sem código';
            }
        }

        return $rotulos;
    }

    /**
     * Decisão coerente com a ficha (RN-004): deferir exige NENHUMA atividade
     * indeferida; indeferir exige PELO MENOS UMA. Atividade sem marcação não
     * bloqueia — só o indeferimento é marcado explicitamente. A mensagem lista
     * as atividades em conflito (erro acionável na própria tela).
     *
     * @param  list<array<string, mixed>>  $perCnae
     *
     * @throws DomainException quando a decisão pedida contradiz as marcações
     */
    public function recusarDecisaoContraditoria(array $perCnae, ?DecisionOutcome $escolhido): void
    {
        $indeferidas = $this->indeferidas($perCnae);

        if ($escolhido === DecisionOutcome::Deferida && $indeferidas !== []) {
            throw new DomainException(
                'Não é possível deferir: há atividade indeferida na ficha ('.implode(', ', $indeferidas).'). Desmarque o indeferimento ou escolha Indeferido.',
            );
        }

        if ($escolhido === DecisionOutcome::Indeferida && $indeferidas === []) {
            throw new DomainException(
                'Não é possível indeferir: nenhuma atividade está marcada como indeferida na ficha. Marque ao menos uma atividade como indeferida ou escolha Deferido.',
            );
        }
    }

    /**
     * Desfecho da decisão (RN-004): INDEFERE quando QUALQUER atividade da ficha
     * está marcada como indeferida; senão DEFERE. Atividade sem marcação não
     * indefere — o analista só precisa marcar o que indefere.
     *
     * @param  list<array<string, mixed>>  $perCnae
     */
    private function outcome(array $perCnae): DecisionOutcome
    {
        return $this->indeferidas($perCnae) === [] ? DecisionOutcome::Deferida : DecisionOutcome::Indeferida;
    }

    /**
     * Veredito consolidado coerente com a decisão humana (alimenta o rótulo da
     * ViabilityDecisionResource): indeferida → nao_permitido; deferida →
     * permitido_com_condicoes quando há condicionantes na ficha, senão permitido.
     */
    private function consolidatedResult(DecisionOutcome $outcome, AnalysisRecord $record): string
    {
        if ($outcome === DecisionOutcome::Indeferida) {
            return ResultadoViabilidade::NaoPermitido->value;
        }

        return $this->temCondicionantes($record)
            ? ResultadoViabilidade::PermitidoComCondicoes->value
            : ResultadoViabilidade::Permitido->value;
    }

    /**
     * Há condicionantes na ficha (consolidadas ou por CNAE)? Define
     * permitido_com_condicoes no deferimento — sem inventar nada além do registrado.
     */
    private function temCondicionantes(AnalysisRecord $record): bool
    {
        if (! empty($record->conditions)) {
            return true;
        }

        foreach ($record->per_cnae ?? [] as $item) {
            if (is_array($item) && ! empty($item['condicionantes'])) {
                return true;
            }
        }

        return false;
    }

    private function parecerDoDeferimento(?string $parecer): ?string
    {
        $parecer = trim((string) $parecer);

        return $parecer === '' ? null : $parecer;
    }

    /**
     * per_cnae da ficha finalizada (a escolha do analista) — só itens válidos com
     * CNAE. É o que vai persistido na decisão (RN-005), não um recomputo do motor.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * Justificativa do indeferimento humano, visível ao requerente: o parecer
     * informado na conclusão, senão o parecer da ficha, senão as justificativas
     * das atividades indeferidas. Sem nenhuma, o indeferimento é recusado.
     *
     * @param  list<array<string, mixed>>  $perCnae
     */
    private function justificativaDoIndeferimento(AnalysisRecord $record, array $perCnae, ?string $parecer): string
    {
        foreach ([$parecer, $record->parecer] as $texto) {
            if (is_string($texto) && trim($texto) !== '') {
                return trim($texto);
            }
        }

        $porAtividade = [];

        foreach ($perCnae as $item) {
            $justificativa = $item['justificativa'] ?? null;

            if (($item['status_escolhido'] ?? null) === DecisionOutcome::Indeferida->value && is_string($justificativa) && trim($justificativa) !== '') {
                $porAtividade[] = 'CNAE '.($item['cnae_formatado'] ?? $item['cnae']).': '.trim($justificativa);
            }
        }

        if ($porAtividade === []) {
            throw new DomainException(
                'Informe o parecer com o motivo do indeferimento (visível ao requerente) antes de concluir.',
            );
        }

        return implode("\n\n", $porAtividade);
    }

    private function perCnaeDaFicha(AnalysisRecord $record): array
    {
        $itens = [];

        foreach ($record->per_cnae ?? [] as $item) {
            if (is_array($item) && isset($item['cnae'])) {
                $itens[] = $item;
            }
        }

        return $itens;
    }

    /**
     * decision_trace ADITIVO da decisão HUMANA (HU-095 RN-004/RN-005): nasce da
     * FICHA (não recomputa o motor). Por CNAE, reaproveita os passos do motor do
     * engine_snapshot (quando a ficha foi pré-analisada) e acrescenta a decisão do
     * analista; no caso pendente sem motor (FA-01), registra a entrada + a decisão
     * e marca os passos do motor como "não registrado" — honesto, nunca inventado.
     *
     * @param  list<array<string, mixed>>  $perCnae
     * @return list<array<string, mixed>>
     */
    private function decisionTrace(AnalysisRecord $record, array $perCnae): array
    {
        $snapshot = is_array($record->engine_snapshot) ? $record->engine_snapshot : [];
        $ponto = is_array($snapshot['ponto'] ?? null) ? $snapshot['ponto'] : null;
        $consultaPorCnae = $this->consultaPorCnaeDoSnapshot($snapshot);

        return array_map(
            fn (array $item): array => $this->traceBuilder->cnaeAnaliseTecnica(
                $item,
                $consultaPorCnae[(string) ($item['cnae'] ?? '')] ?? null,
                [
                    'cnae' => $item['cnae'] ?? null,
                    'cnae_formatado' => $item['cnae_formatado'] ?? null,
                    'is_primary' => $item['is_primary'] ?? false,
                    'ponto' => $ponto,
                ],
            ),
            $perCnae,
        );
    }

    /**
     * Indexa por CNAE o consulta_array do snapshot do motor
     * (engine_snapshot.por_cnae[].consulta) — fonte dos passos do motor no trace
     * da decisão humana, sem recomputo.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, array<string, mixed>>
     */
    private function consultaPorCnaeDoSnapshot(array $snapshot): array
    {
        $indexado = [];

        foreach ($snapshot['por_cnae'] ?? [] as $item) {
            if (is_array($item) && isset($item['cnae']) && is_array($item['consulta'] ?? null)) {
                $indexado[(string) $item['cnae']] = $item['consulta'];
            }
        }

        return $indexado;
    }

    /**
     * Fundamentação legal consolidada da decisão (RN-005): união sem duplicar das
     * fundamentações por CNAE registradas na ficha (do motor ou do próprio
     * analista no caso pendente) — nunca inventada.
     *
     * @param  list<array<string, mixed>>  $perCnae
     * @return list<string>
     */
    private function fundamentacao(array $perCnae): array
    {
        $referencias = [];

        foreach ($perCnae as $item) {
            $fundamentacao = $item['fundamentacao'] ?? [];

            if (is_array($fundamentacao)) {
                $referencias = [...$referencias, ...$fundamentacao];
            }
        }

        return array_values(array_unique($referencias));
    }

    /**
     * Por-CNAE resumido para a auditoria (código + sugerido × escolhido) —
     * explicabilidade compacta da decisão humana (HU-074).
     *
     * @param  list<array<string, mixed>>  $perCnae
     * @return list<array<string, mixed>>
     */
    private function perCnaeResumo(array $perCnae): array
    {
        return array_map(static fn (array $item): array => [
            'cnae' => $item['cnae'] ?? null,
            'status_sugerido' => $item['status_sugerido'] ?? null,
            'status_escolhido' => $item['status_escolhido'] ?? null,
        ], $perCnae);
    }

    /**
     * Versão de regra representativa para a coluna rules_version da auditoria: a
     * primeira versão real das engine_rules_versions da ficha (o mapa completo vai
     * em viability_decisions.rules_versions). Null quando a ficha nasceu sem motor
     * (FA-01 / caso pendente).
     *
     * @param  array<string, mixed>|null  $rulesVersions
     */
    private function rulesVersionRepresentativa(?array $rulesVersions): ?string
    {
        foreach ($rulesVersions ?? [] as $grupo) {
            if (is_array($grupo)) {
                foreach ($grupo as $versao) {
                    if (is_string($versao) && $versao !== '') {
                        return $versao;
                    }
                }

                continue;
            }

            if (is_string($grupo) && $grupo !== '') {
                return $grupo;
            }
        }

        return null;
    }
}
