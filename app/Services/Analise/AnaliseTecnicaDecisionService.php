<?php

namespace App\Services\Analise;

use App\Enums\DecisionOutcome;
use App\Enums\ResultadoViabilidade;
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
use App\Support\Settings;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Decisão técnica HUMANA (HU-085 a HU-089) — a contraparte do FluxoExpressoService
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
 * transiciona em_analise→deferida|indeferida (= encerramento HU-089) e AUDITA
 * SÍNCRONO (HU-078) — tudo antes do commit. O ResultadoEmitido é disparado SÓ
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
    public function decide(AnalysisRecord $record, User $analista): AnaliseDecisionResult
    {
        if (! $record->isFinalizada()) {
            throw new DomainException(
                'A ficha de análise precisa estar Finalizada para concluir o processo (HU-086 CA-03 — rascunho não decide).',
            );
        }

        $request = $record->viabilityRequest()->firstOrFail();

        if ($request->status !== ViabilityRequestStatus::EmAnalise) {
            throw new DomainException(
                "A decisão técnica só conclui processos em análise; situação atual: {$request->status->value}.",
            );
        }

        $perCnae = $this->perCnaeDaFicha($record);

        if ($perCnae === []) {
            throw new DomainException('A ficha finalizada não possui atividades (CNAEs) para decidir.');
        }

        $outcome = $this->outcome($perCnae);

        try {
            $decision = DB::transaction(
                fn (): ViabilityDecision => $this->registrarDecisao($request, $record, $analista, $perCnae, $outcome),
            );
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

        // APÓS o commit: só a decisão efetivada gera efeitos (notificação HU-077;
        // Regin HU-104 / SEFAZ HU-110 bloqueados → Fase 13). A auditoria já está
        // gravada na transação, independente do evento.
        ResultadoEmitido::dispatch($request, $decision);

        return new AnaliseDecisionResult($outcome, $decision, emitted: true);
    }

    /**
     * Grava a decisão dentro da transação: ViabilityDecision (flow analise_tecnica,
     * decided_by analista, número TVL só no deferimento) → transição
     * em_analise→deferida|indeferida (encerramento HU-089) → auditoria SÍNCRONA
     * (HU-078). Tudo antes do commit.
     *
     * @param  list<array<string, mixed>>  $perCnae
     */
    private function registrarDecisao(
        ViabilityRequest $request,
        AnalysisRecord $record,
        User $analista,
        array $perCnae,
        DecisionOutcome $outcome,
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
            $fundamentacao[] = (string) Settings::get(
                'analise.escritorio_virtual.condicionante_sede',
                config('sile.analise.escritorio_virtual.condicionante_sede', ''),
            );
        }

        // Abrigado de escritório virtual (RN-EV-05): a inscrição tem sede ativa e
        // os CNAEs estão na Lista EV — o produto referencia o nº TVL da sede.
        $abrigado = $this->abrigadoResolver->resolve($request);

        $decision = ViabilityDecision::create([
            'viability_request_id' => $request->id,
            'flow' => 'analise_tecnica',
            'outcome' => $outcome,
            'consolidated_result' => $consolidated,
            'is_virtual_office_hq' => $isSede,
            'is_virtual_office_tenant' => $abrigado !== null,
            'virtual_office_hq_tvl_number' => $abrigado['hq_tvl_number'] ?? null,
            'tvl_product_number' => $outcome === DecisionOutcome::Deferida ? $this->tvl->generate() : null,
            'per_cnae' => $perCnae,
            'rules_versions' => $record->engine_rules_versions ?? [],
            'fundamentacao' => $fundamentacao,
            'decision_trace' => $this->decisionTrace($record, $perCnae),
            'reason' => null,
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
     * Desfecho da decisão (RN-004): DEFERE só quando TODAS as CNAEs da ficha estão
     * com status_escolhido 'deferida'; QUALQUER outra escolha (indeferida, ainda em
     * análise, ausente) INDEFERE o processo. Anti-fachada: nunca defere por omissão.
     *
     * @param  list<array<string, mixed>>  $perCnae
     */
    private function outcome(array $perCnae): DecisionOutcome
    {
        foreach ($perCnae as $item) {
            if (($item['status_escolhido'] ?? null) !== DecisionOutcome::Deferida->value) {
                return DecisionOutcome::Indeferida;
            }
        }

        return DecisionOutcome::Deferida;
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

    /**
     * per_cnae da ficha finalizada (a escolha do analista) — só itens válidos com
     * CNAE. É o que vai persistido na decisão (RN-005), não um recomputo do motor.
     *
     * @return list<array<string, mixed>>
     */
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
     * decision_trace ADITIVO da decisão HUMANA (HU-099 RN-004/RN-005): nasce da
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
     * explicabilidade compacta da decisão humana (HU-078).
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
