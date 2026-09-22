<?php

namespace App\Services\Expresso;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\DecisionOutcome;
use App\Enums\Fluxo;
use App\Enums\IntencaoAtividade;
use App\Enums\ResultadoViabilidade;
use App\Enums\SefazNotificationEvent;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\ExpressoQueda;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\Analise\AnalysisSlaService;
use App\Services\Auditoria\DecisionTraceBuilder;
use App\Services\EscritorioVirtual\AbrigadoResolver;
use App\Services\EscritorioVirtual\DesvincularInscricaoService;
use App\Services\Regin\ReginProtocoloSimulacaoService;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Motor de decisão do fluxo expresso (HU-073 a HU-078) — o CORE VALUE do Viabiliza.
 *
 * `decide()` é AUTORITATIVO: sob Cache::lock por solicitação + re-check de
 * status (idempotência), REEXECUTA o SolicitacaoViabilityResolver FRESCO —
 * nunca confia no simulation_snapshot pré-protocolo (orientativo, pode estar
 * stale). A partir do resolvido decide:
 *
 *  - toggle features.fluxo_expresso desligado → em_analise (degradação
 *    comunicada da funcionalidade acoplável, HU-014);
 *  - EXCLUSIVAMENTE exclusão de atividade, sem o CNAE gatilho da sede
 *    (RN-AA-03/RN-AA-05) → DEFERE automaticamente SEM consultar o
 *    enquadramento (nada de novo a avaliar) — exceção justificada ao
 *    anti-fachada, testada antes da resolução;
 *  - inelegível (algum CNAE encaminhado à análise pelo motor de risco) →
 *    em_analise (semi-expresso, HU-073/RN-008);
 *  - veredito consolidado pendente (sem a zona oficial — Quadro 10 indisponível)
 *    → em_analise SEM criar decisão e SEM evento (anti-fachada: o motor NÃO
 *    defere/indefere sem o dado real; liga sozinho quando a base entrar);
 *  - nao_permitido → INDEFERE; permitido(_com_condicoes) → DEFERE (RN-009),
 *    criando a ViabilityDecision imutável + número TVL (só no deferimento),
 *    transicionando pela StateMachine e auditando SÍNCRONO (HU-078) — tudo numa
 *    transação; o ResultadoEmitido é disparado SÓ após o commit.
 *
 * A auditoria autoritativa da decisão é síncrona e NÃO depende do evento (lição
 * da Fase 8): o evento é apenas a base desacoplada dos efeitos colaterais.
 */
class FluxoExpressoService
{
    public function __construct(
        private SolicitacaoViabilityResolver $resolver,
        private ViabilityRequestStateMachine $stateMachine,
        private TvlNumberGenerator $tvl,
        private AuditService $audit,
        private AnalysisSlaService $sla,
        private DecisionTraceBuilder $traceBuilder,
        private SedeEscritorioVirtualGatilho $gatilhoSede,
        private AbrigadoResolver $abrigadoResolver,
        private DesvincularInscricaoService $desvincular,
    ) {}

    /**
     * Decide a viabilidade da solicitação (deferir/indeferir/encaminhar) de forma
     * idempotente e autoritativa. Devolve o DecisionResult com o estado final, a
     * decisão criada (quando há) e se o ResultadoEmitido foi disparado.
     */
    public function decide(ViabilityRequest $request, ?User $actor = null): DecisionResult
    {
        $ttl = (int) config('sile.expresso.lock.ttl_segundos', 10);

        return Cache::lock("expresso:decisao:{$request->id}", $ttl)->block(
            $ttl,
            fn (): DecisionResult => $this->decidirSobLock($request, $actor),
        );
    }

    /**
     * Corpo da decisão executado já com o lock adquirido. RECARREGA o estado e
     * re-checa que ainda está protocolada (idempotência: se já saiu, não
     * redecide), aplica o toggle e a elegibilidade, e roteia para a análise ou a
     * emissão conforme o veredito consolidado FRESCO.
     */
    private function decidirSobLock(ViabilityRequest $request, ?User $actor): DecisionResult
    {
        $request->refresh();

        if ($request->status !== ViabilityRequestStatus::Protocolada) {
            return $this->resultadoIdempotente($request);
        }

        $toggleAtivo = (bool) Settings::get('features.fluxo_expresso', config('sile.features.fluxo_expresso', true));

        if (! $toggleAtivo) {
            return $this->encaminharAnalise($request, 'fluxo expresso desativado', $actor);
        }

        // RN-AA-03/RN-AA-05: solicitação EXCLUSIVAMENTE de exclusão de
        // atividade (todos os CNAEs marcados "excluir") defere
        // automaticamente, SEM consultar o enquadramento LOUOS/risco — nada
        // de novo vai ser exercido no local, então não há veredito
        // locacional a produzir. Testada ANTES da resolução do enquadramento
        // (resolver para descartar depois seria trabalho inútil e confuso de
        // ler) e é a ÚNICA exceção justificada ao anti-fachada: aqui não
        // falta dado, o caso é estruturalmente diferente (ausência de
        // atividade a licenciar, não degradação por dado indisponível).
        //
        // CUIDADO com a exclusão do CNAE GATILHO da sede (default
        // 8211-3/00): ela tem cascata própria (perda da condição de sede,
        // desvinculação da inscrição, notificação de terceiros — Task 3) e
        // NÃO cai neste ramo. `temCnaeGatilho` checa a presença do CNAE
        // gatilho no processo independentemente da intenção declarada, então
        // uma solicitação que o inclui (mesmo marcado para exclusão) segue
        // para o ramo seguinte, que decide com base na INTENÇÃO de exclusão
        // do gatilho (`excluiCnaeGatilho`), inclusive nas mistas.
        if ($request->exclusivamenteExclusao() && ! $this->gatilhoSede->temCnaeGatilho($request)) {
            return $this->deferirExclusao($request, $actor);
        }

        // RN-AA-04/RN-AA-07: exclusão do CNAE gatilho da sede, na inscrição
        // que TEM sede ativa — SEM exigir `exclusivamenteExclusao()`. Uma
        // solicitação MISTA (inclusão + exclusão) que marca o gatilho para
        // sair derruba a condição de sede do mesmo jeito (RN-AA-07: a regra
        // da RN-AA-04 se aplica INTEGRALMENTE também na mista); exigir
        // exclusividade aqui deixava a mista escapar dos dois ramos e cair
        // direto em `emitir()`, deferindo sem confirmação e sem cascata — o
        // C1 da revisão final. `excluiCnaeGatilho()` olha a INTENÇÃO
        // declarada (RN-AA-05b), não a mera presença do CNAE no processo.
        //
        // Excluir esse CNAE derruba a caracterização de sede — cascata de
        // maior consequência (desvincula a inscrição, derruba o vínculo de
        // todas as abrigadas do endereço), então exige confirmação EXPLÍCITA
        // do requerente antes de executar (anti-fachada:
        // `confirma_perda_condicao_sede === null`, "ainda não perguntado", NUNCA
        // equivale a "sim" — nem `false`, a recusa).
        if ($this->gatilhoSede->excluiCnaeGatilho($request)) {
            $lock = VirtualOfficeInscriptionLock::sedeAtiva((string) $request->property_registration);

            if ($lock !== null) {
                // C2 da revisão final: a cascata só pode derrubar a sede de
                // quem É a titular do vínculo. `deferirExclusaoDeSede()`
                // localizava o lock só pela inscrição, sem checar de quem é
                // — uma solicitação de OUTRA empresa na mesma inscrição
                // conseguia derrubar a sede alheia. Sem correspondência,
                // encaminha à análise com o motivo registrado; nunca executa.
                if (! $this->gatilhoSede->titularDoVinculo($request, $lock)) {
                    return $this->encaminharAnalise(
                        $request,
                        'exclusão do CNAE gatilho da sede pedida por solicitação que não é a titular do vínculo de sede — encaminhado para verificação',
                        $actor,
                    );
                }

                if ($request->confirma_perda_condicao_sede !== true) {
                    $mensagem = str_replace(
                        ':cnae',
                        $this->gatilhoSede->cnaeGatilhoFormatado(),
                        (string) Settings::get(
                            'analise.escritorio_virtual.mensagem_confirma_perda_sede',
                            config('sile.analise.escritorio_virtual.mensagem_confirma_perda_sede'),
                        ),
                    );

                    return $this->encaminharAnalise($request, $mensagem, $actor);
                }

                return $this->deferirExclusaoDeSede($request, $lock, $actor);
            }

            // I1 da revisão final: sem vínculo de sede ATIVO na inscrição, não
            // há condição de sede a perder — RN-EV-01 simplesmente não se
            // aplica. Sem isso, uma solicitação exclusivamente de exclusão
            // caía na resolução normal e passava por zoneamento (contradiz
            // RN-AA-03/05: exclusão defere automaticamente, sem zoneamento).
            // Só se aplica à exclusão PURA — a mista sem sede ativa segue
            // para a resolução normal, onde a parte de inclusão precisa
            // mesmo do enquadramento (fora do escopo desta correção).
            if ($request->exclusivamenteExclusao()) {
                // property_registration é nulável: sobre inscrição
                // desconhecida o honesto é encaminhar à análise com o motivo,
                // nunca deferir uma exclusão de sede sem saber qual sede.
                if (blank($request->property_registration)) {
                    return $this->encaminharAnalise(
                        $request,
                        'exclusão do CNAE gatilho sem inscrição imobiliária informada — encaminhado para verificação',
                        $actor,
                    );
                }

                return $this->deferirExclusao($request, $actor);
            }
        }

        // Reexecução FRESCA dos motores — a decisão é autoritativa, não o snapshot.
        $resolved = $this->resolver->resolve($request);

        // RN-041-A: Quadro 10 ou Quadro 11A vedam o uso no local. Isso
        // independe do risco — a análise só recebe o que ainda pode deferir.
        // O veto locacional vem ANTES do gate de alto risco (e-mail SEDUR
        // 21/09/2026, item 5): a legislação impossibilita o deferimento, então
        // o processo é indeferido expresso, nunca encaminhado à análise.
        if ($resolved->consolidado === ResultadoViabilidade::NaoPermitido->value) {
            return $this->emitir($request, $resolved, $actor);
        }

        // RN-041-B: alto risco — determinado pelo CNAE ou por pergunta
        // condicional que o eleve — nunca é decidido automaticamente, nem
        // deferido nem indeferido. Vai à análise com a fundamentação do
        // motor. Só chega aqui SEM veto locacional (o veto indeferiu acima).
        if ($resolved->temAltoRisco()) {
            return $this->encaminharAnalise($request, 'atividade de alto risco — análise técnica', $actor, $resolved);
        }

        if (! $resolved->elegivelExpresso()) {
            return $this->encaminharAnalise($request, 'atividade fora do fluxo expresso (análise técnica)', $actor, $resolved);
        }

        // Sem a zona (Quadro 10), o veredito é pendente — encaminha à análise SEM
        // decidir nem emitir. Anti-fachada: jamais um deferimento/indeferimento
        // inventado (a base oficial liga o caminho de decisão).
        //
        // Exceção honesta da homologação do motor de risco (simulação REGIN),
        // disponível apenas com features.simulacao_protocolo LIGADA:
        // o conjunto já foi classificado pelo Decreto; a zona oficial ainda
        // não está neste ambiente. Baixo/Médio segue o expresso (TVL) sem
        // fingir zoneamento. Processo REGIN de verdade continua bloqueado aqui.
        if ($resolved->consolidado === ResultadoViabilidade::Pendente->value
            && ! $this->bypassSimulacaoHomologacao($request)) {
            return $this->encaminharAnalise(
                $request,
                'veredito locacional pendente — zona urbanística pendente SEDUR',
                $actor,
                $resolved,
            );
        }

        // RN-EV-01/RN-C-04: CNAE gatilho (default 8211-3/00) + "será sede? =
        // Sim" não conclui no expresso — vai para análise humana (gatilho
        // parametrizável). O motivo registrado é o texto da flag do §2º do
        // art. 6º do Decreto 35.062/2021 (não a descrição interna do
        // gatilho): é isso que a operação lê na fila de análise, e a
        // informação de que foi o gatilho de sede já está na trilha de
        // auditoria por outros campos (resultado, consolidado, por_cnae).
        if ($this->gatilhoSede->aplica($request)) {
            $flag = (string) Settings::get(
                'analise.escritorio_virtual.flag_analise_sede',
                config('sile.analise.escritorio_virtual.flag_analise_sede'),
            );

            return $this->encaminharAnalise($request, $flag, $actor, $resolved);
        }

        if ($resolved->consolidado === ResultadoViabilidade::Pendente->value
            && $this->bypassSimulacaoHomologacao($request)) {
            return $this->emitir(
                $request,
                new ResolvedViability(
                    por_cnae: $resolved->por_cnae,
                    consolidado: ResultadoViabilidade::Permitido->value,
                    rules_versions: $resolved->rules_versions,
                    ponto: $resolved->ponto,
                    area_m2: $resolved->area_m2,
                ),
                $actor,
            );
        }

        return $this->emitir($request, $resolved, $actor);
    }

    /**
     * Re-check do lock: a solicitação já saiu de protocolada (decidida em outra
     * passada ou encaminhada). Devolve a decisão existente (sem redisparar o
     * evento) ou o estado atual, sem nova transição — no-op idempotente.
     */
    private function resultadoIdempotente(ViabilityRequest $request): DecisionResult
    {
        $decision = $request->decision()->first();

        if ($decision !== null) {
            return DecisionResult::decidida($decision, emitted: false);
        }

        return new DecisionResult($request->status, null, null, emitted: false);
    }

    /**
     * Bypass de homologação do motor de risco (simulação REGIN): só vale com
     * features.simulacao_protocolo LIGADA. Desligada (default), o processo de
     * simulação segue o fluxo normal — pendente vai à análise, sem exceção.
     */
    private function bypassSimulacaoHomologacao(ViabilityRequest $request): bool
    {
        return $request->origin === ViabilityRequestOrigin::Regin
            && $request->contingency_reason === ReginProtocoloSimulacaoService::CONTINGENCIA
            && Settings::enabled('simulacao_protocolo');
    }

    /**
     * Encaminha a solicitação à análise técnica (HU-073/HU-079): transição
     * protocolada→em_analise (timeline + auditoria de transição), MATERIALIZAÇÃO
     * do SLA da fila (HU-144 — analysis_due_at na etapa de distribuição, via
     * AnalysisSlaService) e auditoria SÍNCRONA da decisão (resultado 'analise',
     * com o motivo e — quando houve resolução — o por-CNAE e as versões de
     * regra), tudo numa transação. NÃO cria ViabilityDecision e NÃO dispara
     * ResultadoEmitido.
     *
     * APÓS o commit, dispara EncaminhadoParaAnalise (gatilho da pré-análise
     * HU-140, 10-08 — listener AUTO-DESCOBERTO): só encaminhamentos efetivados
     * geram efeitos. A auditoria autoritativa já está gravada na transação e NÃO
     * depende do evento (lição das Fases 8/9). O processo cai na caixa do setor
     * de triagem parametrizado (analise.setor_triagem_id — elo motor → caixa);
     * o ANALISTA fica nulo — a distribuição (HU-080/081) é uma ação humana
     * posterior na caixa do setor (apoio/gestor distribui, analista assume).
     */
    private function encaminharAnalise(
        ViabilityRequest $request,
        string $reason,
        ?User $actor,
        ?ResolvedViability $resolved = null,
    ): DecisionResult {
        DB::transaction(function () use ($request, $reason, $actor, $resolved): void {
            $this->stateMachine->transition(
                $request,
                ViabilityRequestStatus::EmAnalise,
                $actor,
                reason: $reason,
                publicLabel: ViabilityRequestStatus::EmAnalise->publicLabel(),
            );

            // HU-144: o prazo da fila nasce no encaminhamento (etapa distribuição).
            // Colunas fora do fillable → forceFill (escrita controlada pelo serviço).
            $startedAt = now();
            $request->forceFill([
                'sector_id' => $this->setorTriagemId(),
                'analysis_stage' => AnalysisStage::Distribuicao,
                'analysis_stage_started_at' => $startedAt,
                'analysis_due_at' => $this->sla->dueAtFor(AnalysisStage::Distribuicao, $startedAt),
                'analysis_status' => AnalysisStatus::ParaDistribuir,
            ])->save();

            $this->audit->log(
                logName: 'expresso',
                event: 'decisao',
                description: "Encaminhamento à análise técnica da solicitação #{$request->id}: {$reason}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'resultado' => 'analise',
                    'motivo' => $reason,
                    'sector_id' => $request->sector_id,
                    'consolidado' => $resolved?->consolidado,
                    'por_cnae' => $resolved !== null ? $this->perCnaeResumo($resolved) : [],
                ],
                subject: $request,
                result: 'analise',
                rulesVersion: $resolved !== null ? $this->rulesVersionRepresentativa($resolved->rules_versions) : null,
            );

            $this->capturarQueda($request, $reason, $resolved);
        });

        // APÓS o commit: gatilho da pré-análise (10-08). Sem listener no ambiente
        // atual é inerte; o ShouldDispatchAfterCommit do evento é defesa extra.
        EncaminhadoParaAnalise::dispatch($request);

        return DecisionResult::paraAnalise($reason);
    }

    /**
     * Setor de entrada da análise técnica (HU-014 — analise.setor_triagem_id):
     * enquanto o roteamento automático por CNAE/território não é definido pela
     * SEDUR, o motor deposita o processo na caixa do setor parametrizado. Sem
     * parâmetro — ou apontando para setor inexistente/inativo — o processo
     * segue SEM caixa (degradação honesta: nunca cair numa caixa inválida; a
     * atribuição manual na caixa do setor continua valendo).
     */
    private function setorTriagemId(): ?int
    {
        $id = Settings::get('analise.setor_triagem_id');

        if ($id === null) {
            return null;
        }

        $id = (int) $id;

        return Sector::query()->whereKey($id)->where('active', true)->exists()
            ? $id
            : null;
    }

    /**
     * Captura ESTRUTURADA da queda ao analista (HU-145), gravada na MESMA
     * transação do encaminhamento (ADITIVA — não altera a decisão/transição/
     * auditoria das Fases 9/10). Fecha o ciclo de melhoria do expresso medindo
     * sobre dado real: para cada CNAE que o motor encaminhou à análise, persiste
     * o gatilho semi-expresso (TipoGatilho), a dimensão decisiva e o motivo lidos
     * de `consulta_array.risco.encaminhamento` (shape do RiscoClassificationService).
     *
     * ANTI-FACHADA (RN-001): quando o motor degradou (`$resolved === null` —
     * toggle off; ou veredito pendente sem zona, em que `por_cnae` ainda traz o
     * encaminhamento real), grava-se a linha honesta: motor degradado vira 1
     * linha de nível-processo (cnae/tipo_gatilho/dimensao NULL) com o motivo do
     * roteamento; e CNAE sem gatilho de contexto registra `tipo_gatilho` NULL —
     * NUNCA um gatilho inventado.
     */
    private function capturarQueda(ViabilityRequest $request, string $reason, ?ResolvedViability $resolved): void
    {
        if ($resolved === null) {
            ExpressoQueda::create([
                'viability_request_id' => $request->id,
                'cnae' => null,
                'tipo_gatilho' => null,
                'dimensao' => null,
                'motivo' => $reason,
            ]);

            return;
        }

        foreach ($resolved->por_cnae as $item) {
            if (($item['fluxo'] ?? null) !== Fluxo::Analise->value) {
                continue;
            }

            $encaminhamento = $item['consulta_array']['risco']['encaminhamento'] ?? [];

            ExpressoQueda::create([
                'viability_request_id' => $request->id,
                'cnae' => $item['cnae'] ?? null,
                'tipo_gatilho' => $encaminhamento['gatilhos_acionados'][0]['codigo'] ?? null,
                'dimensao' => $encaminhamento['dimensao_decisiva'] ?? null,
                'motivo' => $encaminhamento['motivo'] ?? $reason,
            ]);
        }
    }

    /**
     * Defere automaticamente a solicitação exclusivamente de exclusão de
     * atividade (RN-AA-03/RN-AA-05), SEM enquadramento: cria a
     * ViabilityDecision imutável com per_cnae/fundamentação próprios do caso
     * (sem ConsultaViabilidadeResult — nenhum motor rodou), gera o número TVL
     * (RN-007, todo deferimento tem produto), transiciona pela StateMachine e
     * AUDITA SÍNCRONO, tudo numa transação. O ResultadoEmitido é disparado
     * APÓS o commit — mesmo contrato de `emitir()`.
     *
     * Abrigado de escritório virtual (RN-EV-05): resolvido do MESMO jeito que
     * `emitir()` resolve. O vínculo de abrigado da inscrição já existe ANTES
     * da exclusão (sede ativa + CNAEs na Lista EV) — esta decisão não o cria,
     * só o REFLETE; omitir is_virtual_office_tenant/virtual_office_hq_tvl_number
     * aqui faria o processo sumir do relatório de sede×abrigados e da tela
     * (RelatorioSedeEscritorioVirtualService/ProcessoResource leem esses
     * campos da decisão, não da inscrição).
     *
     * Robustez idempotente igual a `emitir()`: corrida rara que escape do
     * Cache::lock vira NO-OP via a unique(viability_request_id).
     */
    private function deferirExclusao(ViabilityRequest $request, ?User $actor): DecisionResult
    {
        $abrigado = $this->abrigadoResolver->resolve($request);

        try {
            $decision = DB::transaction(function () use ($request, $actor, $abrigado): ViabilityDecision {
                $cnaesExcluidos = $request->cnaesParaExcluir();

                $decision = ViabilityDecision::create([
                    'viability_request_id' => $request->id,
                    'flow' => 'expresso',
                    'outcome' => DecisionOutcome::Deferida,
                    'consolidated_result' => ResultadoViabilidade::Permitido->value,
                    'is_virtual_office_tenant' => $abrigado !== null,
                    'virtual_office_hq_tvl_number' => $abrigado['hq_tvl_number'] ?? null,
                    'tvl_product_number' => $this->tvl->generate(),
                    'per_cnae' => $cnaesExcluidos
                        ->map(fn (Cnae $cnae): array => [
                            'cnae' => $cnae->code,
                            'cnae_formatado' => $cnae->formatted_code,
                            'intencao' => IntencaoAtividade::Excluir->value,
                        ])
                        ->values()
                        ->all(),
                    'rules_versions' => [],
                    'fundamentacao' => [
                        'RN-AA-03/RN-AA-05 — exclusão de atividade defere automaticamente, sem enquadramento',
                    ],
                    'decision_trace' => null,
                    'reason' => 'exclusão de atividade — deferimento automático, sem consulta de enquadramento',
                    'decided_by_user_id' => $actor?->id,
                    'decided_at' => now(),
                ]);

                $this->stateMachine->transition(
                    $request,
                    ViabilityRequestStatus::Deferida,
                    $actor,
                    publicLabel: ViabilityRequestStatus::Deferida->publicLabel(),
                );

                $this->audit->log(
                    logName: 'expresso',
                    event: 'decisao',
                    description: "Deferimento automático de exclusão de atividade da solicitação #{$request->id}",
                    properties: [
                        'viability_request_id' => $request->id,
                        'protocol_number' => $request->protocol_number,
                        'outcome' => DecisionOutcome::Deferida->value,
                        'consolidado' => $decision->consolidated_result,
                        'tvl_product_number' => $decision->tvl_product_number,
                        'cnaes_excluidos' => $cnaesExcluidos->pluck('code')->all(),
                    ],
                    subject: $request,
                    result: DecisionOutcome::Deferida->value,
                    rulesVersion: null,
                );

                return $decision;
            });
        } catch (QueryException $e) {
            $existente = $request->decision()->first();

            if ($existente !== null) {
                return DecisionResult::decidida($existente, emitted: false);
            }

            throw $e;
        }

        // APÓS o commit: só decisões efetivadas geram efeitos.
        ResultadoEmitido::dispatch($request, $decision);

        return DecisionResult::decidida($decision, emitted: true);
    }

    /**
     * Defere a exclusão CONFIRMADA do CNAE gatilho da sede (RN-AA-04): mesma
     * base de `deferirExclusao()` (decisão sem enquadramento, TVL, transição,
     * auditoria síncrona, tudo numa transação), mas esta solicitação NUNCA é
     * abrigada da sede que ela mesma está desativando — `is_virtual_office_tenant`
     * fica sempre falso, sem consultar o AbrigadoResolver. Coerente com a
     * verificação de titularidade em `gatilhoSede->titularDoVinculo()`: quem chega aqui É a
     * titular do vínculo que está desativando, então nunca é abrigada dele.
     *
     * A cascata de desvinculação (RN-EV-06) só roda APÓS o commit da decisão,
     * chamando o serviço COMPARTILHADO `DesvincularInscricaoService` — nunca
     * reimplementada aqui (RN-EV-06 do motor proíbe duplicar a lógica). Isolar
     * a chamada fora da transação da decisão é o mesmo motivo pelo qual o
     * próprio serviço isola o envio à SEFAZ do resto: uma falha na cascata
     * (rede, notificação) não pode desfazer, num rollback, uma decisão já
     * efetivada e commitada.
     */
    private function deferirExclusaoDeSede(ViabilityRequest $request, VirtualOfficeInscriptionLock $lock, ?User $actor): DecisionResult
    {
        try {
            $decision = DB::transaction(function () use ($request, $actor, $lock): ViabilityDecision {
                $cnaesExcluidos = $request->cnaesParaExcluir();

                $decision = ViabilityDecision::create([
                    'viability_request_id' => $request->id,
                    'flow' => 'expresso',
                    'outcome' => DecisionOutcome::Deferida,
                    'consolidated_result' => ResultadoViabilidade::Permitido->value,
                    'is_virtual_office_tenant' => false,
                    'virtual_office_hq_tvl_number' => null,
                    'tvl_product_number' => $this->tvl->generate(),
                    'per_cnae' => $cnaesExcluidos
                        ->map(fn (Cnae $cnae): array => [
                            'cnae' => $cnae->code,
                            'cnae_formatado' => $cnae->formatted_code,
                            'intencao' => IntencaoAtividade::Excluir->value,
                        ])
                        ->values()
                        ->all(),
                    'rules_versions' => [],
                    'fundamentacao' => [
                        'RN-AA-04 — exclusão do CNAE gatilho da sede, confirmada pelo requerente, retira a condição de sede',
                    ],
                    'decision_trace' => null,
                    'reason' => 'exclusão do CNAE gatilho da sede — confirmação explícita do requerente para a perda da condição',
                    'decided_by_user_id' => $actor?->id,
                    'decided_at' => now(),
                ]);

                $this->stateMachine->transition(
                    $request,
                    ViabilityRequestStatus::Deferida,
                    $actor,
                    publicLabel: ViabilityRequestStatus::Deferida->publicLabel(),
                );

                $this->audit->log(
                    logName: 'expresso',
                    event: 'decisao',
                    description: "Deferimento automático de exclusão do CNAE gatilho da sede da solicitação #{$request->id}",
                    properties: [
                        'viability_request_id' => $request->id,
                        'protocol_number' => $request->protocol_number,
                        'outcome' => DecisionOutcome::Deferida->value,
                        'consolidado' => $decision->consolidated_result,
                        'tvl_product_number' => $decision->tvl_product_number,
                        'cnaes_excluidos' => $cnaesExcluidos->pluck('code')->all(),
                        'sede_viability_request_id' => $lock->sede_viability_request_id,
                    ],
                    subject: $request,
                    result: DecisionOutcome::Deferida->value,
                    rulesVersion: null,
                );

                return $decision;
            });
        } catch (QueryException $e) {
            $existente = $request->decision()->first();

            if ($existente !== null) {
                return DecisionResult::decidida($existente, emitted: false);
            }

            throw $e;
        }

        // APÓS o commit: cascata compartilhada (RN-EV-06) — desvincula o lock,
        // notifica cada abrigado e registra a comunicação reprocessável à
        // SEFAZ. NÃO reimplementada aqui.
        $this->desvincular->desvincular(
            $lock,
            "exclusão do CNAE gatilho ({$this->gatilhoSede->cnaeGatilho()}) da sede, confirmada pelo requerente na solicitação #{$request->id}",
            $actor,
            SefazNotificationEvent::SedePerdeuCondicao,
        );

        ResultadoEmitido::dispatch($request, $decision);

        return DecisionResult::decidida($decision, emitted: true);
    }

    /**
     * Emite a decisão vinculante (HU-074/075/076, RN-009): nao_permitido →
     * INDEFERE; permitido(_com_condicoes) → DEFERE. Numa transação cria a
     * ViabilityDecision imutável (per_cnae/rules_versions/fundamentação), gera o
     * número TVL só no deferimento, transiciona pela StateMachine (timeline +
     * auditoria) e AUDITA SÍNCRONO a decisão (HU-078) — tudo antes do commit. O
     * ResultadoEmitido é disparado APÓS o commit.
     *
     * Robustez idempotente: se uma corrida rara escapar do Cache::lock (ex.:
     * cache não compartilhado), a unique(viability_request_id) barra a 2ª
     * decisão; a QueryException vira NO-OP — recarrega a decisão real já gravada
     * e devolve sem redisparar o evento (não é fachada: a decisão existe).
     */
    private function emitir(ViabilityRequest $request, ResolvedViability $resolved, ?User $actor): DecisionResult
    {
        $outcome = $resolved->consolidado === ResultadoViabilidade::NaoPermitido->value
            ? DecisionOutcome::Indeferida
            : DecisionOutcome::Deferida;

        // Abrigado de escritório virtual (RN-EV-05): a inscrição tem sede ativa e
        // os CNAEs estão na Lista EV — o produto referencia o nº TVL da sede.
        $abrigado = $this->abrigadoResolver->resolve($request);

        try {
            $decision = DB::transaction(function () use ($request, $resolved, $actor, $outcome, $abrigado): ViabilityDecision {
                $decision = ViabilityDecision::create([
                    'viability_request_id' => $request->id,
                    'flow' => 'expresso',
                    'outcome' => $outcome,
                    'consolidated_result' => $resolved->consolidado,
                    'is_virtual_office_tenant' => $abrigado !== null,
                    'virtual_office_hq_tvl_number' => $abrigado['hq_tvl_number'] ?? null,
                    'tvl_product_number' => $outcome === DecisionOutcome::Deferida ? $this->tvl->generate() : null,
                    'per_cnae' => $this->perCnae($resolved),
                    'rules_versions' => $resolved->rules_versions,
                    'fundamentacao' => $this->fundamentacaoConsolidada($resolved),
                    'decision_trace' => $this->decisionTrace($resolved),
                    'reason' => null,
                    'decided_by_user_id' => $actor?->id,
                    'decided_at' => now(),
                ]);

                $destino = $outcome === DecisionOutcome::Deferida
                    ? ViabilityRequestStatus::Deferida
                    : ViabilityRequestStatus::Indeferida;

                $this->stateMachine->transition($request, $destino, $actor, publicLabel: $destino->publicLabel());

                $this->audit->log(
                    logName: 'expresso',
                    event: 'decisao',
                    description: "Decisão expressa ({$outcome->value}) da solicitação #{$request->id}",
                    properties: [
                        'viability_request_id' => $request->id,
                        'protocol_number' => $request->protocol_number,
                        'outcome' => $outcome->value,
                        'consolidado' => $resolved->consolidado,
                        'tvl_product_number' => $decision->tvl_product_number,
                        'por_cnae' => $this->perCnaeResumo($resolved),
                    ],
                    subject: $request,
                    result: $outcome->value,
                    rulesVersion: $this->rulesVersionRepresentativa($resolved->rules_versions),
                );

                return $decision;
            });
        } catch (QueryException $e) {
            $existente = $request->decision()->first();

            if ($existente !== null) {
                return DecisionResult::decidida($existente, emitted: false);
            }

            throw $e;
        }

        // APÓS o commit: só decisões efetivadas geram efeitos (notificação, Regin,
        // SEFAZ). A auditoria autoritativa já foi gravada na transação.
        ResultadoEmitido::dispatch($request, $decision);

        return DecisionResult::decidida($decision, emitted: true);
    }

    /**
     * Por-CNAE completo persistido na decisão (RN-005/RN-009): veredito
     * locacional, encaminhamento de risco e fundamentação legal de cada CNAE —
     * sem o objeto ConsultaViabilidadeResult (só o que é auditável e serializável).
     *
     * @return list<array<string, mixed>>
     */
    private function perCnae(ResolvedViability $resolved): array
    {
        return array_map(static fn (array $item): array => [
            'cnae' => $item['cnae'],
            'cnae_formatado' => $item['cnae_formatado'],
            'is_primary' => $item['is_primary'],
            'tendencia' => $item['tendencia'],
            'tendencia_label' => $item['tendencia_label'],
            'fluxo' => $item['fluxo'],
            // Código TLL da planilha (ramo resolvido) — insumo do cálculo do DAM
            // (HU-071 RN-004) no envio à SEFAZ, sem recomputar o motor.
            'codigo_tll' => $item['consulta']->risco->encaminhamento['tll'] ?? null,
            'fundamentacao' => $item['consulta']->fundamentacao(),
        ], $resolved->por_cnae);
    }

    /**
     * decision_trace ADITIVO da decisão expressa (HU-099 RN-004/RN-005): o
     * snapshot passo a passo por CNAE, montado pelo DecisionTraceBuilder a partir
     * do consulta_array JÁ em memória ($resolved) — sem recomputar o motor e sem
     * mudar o veredito. É a fonte que a explicabilidade (12-05) projeta.
     *
     * @return list<array<string, mixed>>
     */
    private function decisionTrace(ResolvedViability $resolved): array
    {
        return array_map(
            fn (array $item): array => $this->traceBuilder->cnaeExpresso($item['consulta_array'], [
                'cnae' => $item['cnae'],
                'cnae_formatado' => $item['cnae_formatado'],
                'is_primary' => $item['is_primary'],
                'ponto' => $resolved->ponto,
            ]),
            $resolved->por_cnae,
        );
    }

    /**
     * Fundamentação legal consolidada (RN-005): união sem duplicar das
     * referências de todos os CNAEs (já produzidas pelos motores LOUOS/risco) —
     * nunca inventada.
     *
     * @return list<string>
     */
    private function fundamentacaoConsolidada(ResolvedViability $resolved): array
    {
        $referencias = [];

        foreach ($resolved->por_cnae as $item) {
            $referencias = [...$referencias, ...$item['consulta']->fundamentacao()];
        }

        return array_values(array_unique($referencias));
    }

    /**
     * Por-CNAE resumido para a auditoria (código, tendência locacional e
     * encaminhamento de risco) — explicabilidade compacta da decisão (RN-005).
     *
     * @return list<array<string, mixed>>
     */
    private function perCnaeResumo(ResolvedViability $resolved): array
    {
        return array_map(static fn (array $item): array => [
            'cnae' => $item['cnae'],
            'tendencia' => $item['tendencia'],
            'fluxo' => $item['fluxo'],
        ], $resolved->por_cnae);
    }

    /**
     * Versão de regra representativa da decisão (RN-005) para a coluna
     * rules_version da auditoria: a primeira versão real aplicada, na ordem em
     * que governa o veredito (Quadro 10 → risco_tratamento → 11A → risco → território). O
     * mapa completo de versões fica na ViabilityDecision.
     *
     * @param  array<string, array<string, ?string>>  $rulesVersions
     */
    private function rulesVersionRepresentativa(array $rulesVersions): ?string
    {
        $ordem = [
            ['louos', 'quadro10'],
            ['louos', 'risco_tratamento'],
            ['louos', 'quadro11a'],
            ['risco', 'municipal'],
            ['risco', 'sanitario'],
            ['territorio', 'zona'],
            ['territorio', 'bairro'],
        ];

        foreach ($ordem as [$grupo, $chave]) {
            $versao = $rulesVersions[$grupo][$chave] ?? null;

            if (is_string($versao) && $versao !== '') {
                return $versao;
            }
        }

        return null;
    }
}
