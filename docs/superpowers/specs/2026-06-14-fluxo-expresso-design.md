# Spec de Design — Fase 9: Fluxo Expresso (EP09)

**Data:** 2026-06-14
**Status:** Aprovado (brainstorming via agents analista-negocio + arquiteto-tecnico)
**Fontes:** análise de negócio + arquitetura (sessão 2026-06-14), ROADMAP Phase 9, HUs EP09 (HU-073..078, HU-134).

## Problema

É o **core value do produto**: avaliar todo protocolo automaticamente e **deferir/indeferir quando a lei permite**, com decisão fundamentada e auditável, sem análise humana. Consome o protocolo da Fase 8 (evento `SolicitacaoProtocolada`) e os motores reais (LOUOS Fase 5 / risco Fase 6, via `ConsultaViabilidadeService` Fase 7).

## Decisão de escopo (os dois agents convergiram)

**Núcleo entregável AGORA (sem fachada):** o MOTOR DE DECISÃO real — elegibilidade (HU-073) → deferir/indeferir automático (HU-074/075) → emitir resultado interno (HU-076, parte real) → auditar (HU-078) → notificar por e-mail (HU-077). Roda os motores reais e produz parecer fundamentado.

**Degradação honesta herdada (CRÍTICA):** sem a zona (Quadro 10, bloqueado SEDUR), o motor LOUOS consolida `pendente` para o caminho geocodificado. Uma solicitação cujo veredito **não fecha** NÃO é deferida nem indeferida — transita `protocolada → em_analise` (Fase 10) com motivo auditado. Hoje, sem zona, **a maioria das solicitações por endereço cai honestamente em análise**. Isso NÃO é fachada: o motor de decisão é real, testado e auditado; quando a SEDUR entregar a zona, as decisões automáticas fluem **sem mudança de código — muda a carga, não a lógica**. (Registrar essa expectativa no STATE/ROADMAP para a gestão não ler "expresso não defere nada" como falha.)

**BLOQUEADO → Fase 13 (degrada honesto, registrado, NUNCA simulado):**
- Comunicar parecer ao **Regin/Junta** (HU-076 SC3 / HU-104) e enviar deferimento à **SEFAZ** (HU-110): atrás de contrato com binding indisponível; a decisão é tomada/registrada/auditada de verdade e a transmissão fica registrada como **pendente** (outbox/listener que audita `result: 'bloqueado'`).
- **HU-134** (indeferir por prazo BAP): a rotina + parâmetro + estado `aguardando_bap` existem, mas **DORMENTES** — nada coloca processos em `aguardando_bap` hoje (a origem `regin`/vinculação BAP está bloqueada). Atua só quando o conector Regin (Fase 13) alimentar o relógio.

**Ajuste sobre o TVL:** o **número de produto TVL** no deferimento (HU-076 RN-007) é **registro interno** (não integração) → entra agora (gerador interno espelhando `ProtocolNumberGenerator`). O **PDF/TVL** (HU-132) é Fase 10. Deferiu → gera/registra número; não gera PDF, não entrega ao cidadão.

**HU-077 (notificar):** e-mail complementar **sem anexo de TVL** (canal oficial é Regin/SEFAZ); reusa a infra de e-mail (`EmailLog`). Canais plenos (WhatsApp, in-app) são EP11.

## Arquitetura

### Fluxo de ponta a ponta

```
SolicitacaoProtocolada (Fase 8, after-commit)
  → AvaliarFluxoExpresso (listener AUTO-DESCOBERTO, NÃO Event::listen) → DecidirFluxoExpressoJob (fila, retry/backoff)
     → FluxoExpressoService::decide(request):
        Cache::lock("expresso:decisao:{id}") + re-check status protocolada (idempotência)
        toggle features.fluxo_expresso off → em_analise (degradação comunicada)
        SolicitacaoViabilityResolver::resolve(request)  [reusa ConsultaViabilidadeService por CNAE]
        elegibilidade (HU-073): algum CNAE fora do expresso (alto/gatilho/não-classificado) → em_analise
        veredito consolidado == pendente (sem zona) → em_analise   [SEM ResultadoEmitido]
        == nao_permitido → INDEFERE (HU-075)  |  permitido(_com_condicoes) → DEFERE (HU-074)
        DB::transaction: cria ViabilityDecision + número TVL (defere) + transição StateMachine + AUDITORIA síncrona (HU-078)
        after-commit: ResultadoEmitido
          → NotificarResultadoExpresso (HU-077, e-mail, sem TVL)
          → ComunicarResultadoRegin (HU-104, contrato BLOQUEADO → audita pendência)
          → EnviarViabilidadeSefaz (HU-110, só deferida, BLOQUEADO → audita pendência)

Scheduler (Fase 3.1):
  expresso:reavaliar → rede de segurança: protocoladas sem decisão → DecidirFluxoExpressoJob
  expresso:indeferir-sem-bap (HU-134) → aguardando_bap vencidas → indefere (DORMENTE até Regin)
```

### Gatilho e orquestração
- **Listener auto-descoberto** `AvaliarFluxoExpresso` no `SolicitacaoProtocolada` (o docblock do evento já lista esse gancho), que **despacha** `DecidirFluxoExpressoJob` (`ShouldQueue`, tries/timeout/backoff) — não decide inline (mantém o protocolo rápido; decisão roda motores + precisa de resiliência da Fase 3.1). Scheduler `expresso:reavaliar` = rede de segurança/reprocesso (não gatilho principal). Comando `expresso:decidir {solicitacao}` para evidência/reprocesso.
- **NÃO registrar listeners via `Event::listen`** (lição da Fase 8 — duplica a auditoria). Auto-descoberta é o registro único.

### Reuso sem recomputar (decisão AUTORITATIVA, não snapshot)
- Extrair `SolicitacaoViabilityResolver` (de `SimulacaoSolicitacaoService::simulate`): itera CNAEs (principal+complementares) via `ConsultaViabilidadeService::consultarPorPontoConhecido`, propaga o veredito do motor LOUOS, consolida pior caso, devolve `por_cnae[]` + consolidado + `rules_versions` — **sem persistir**. Consumido por: `SimulacaoSolicitacaoService` (snapshot ORIENTATIVO, Fase 8 — manter testes verdes) e `FluxoExpressoService` (decisão AUTORITATIVA). Cada item `por_cnae` carrega o `ConsultaViabilidadeResult` (lê `risco.encaminhamento.fluxo` p/ elegibilidade e `vereditoLocacional().resultado` p/ decisão numa passada).
- **A decisão vinculante REEXECUTA os motores frescos** (entrada/regras/versões da época da decisão) — NÃO confia no `simulation_snapshot` pré-protocolo (orientativo, pode estar `markSimulationStale`). HU-078 RN-005 exige a versão da regra da decisão. O snapshot pode ser exibido/comparado, nunca decidir.

### Regras de decisão
- **Elegibilidade (HU-073):** elegível só se **todos** os CNAEs têm `Fluxo::Expresso` (enum `app/Enums/Fluxo.php`; risco já entrega via `risco.mapa_encaminhamento`: baixo_a/baixo_b → expresso; alto/gatilho/não-classificado → analise). Qualquer CNAE em `analise` → inelegível → `em_analise` (semi-expresso, motivo auditado, RN-008).
- **Consolidação (RN-009, confirmada no SAPS):** todos `permitido`/`permitido_com_condicoes` → defere; algum `nao_permitido` → indefere o processo inteiro. `pendente` → análise.

### Máquina de estados (ADICIONAR ao mapa, sem tocar o protocolo)
- `protocolada → {cancelada, em_analise, deferida, indeferida, aguardando_bap}`; `aguardando_bap → {deferida, indeferida, em_analise}`. `em_pendencia` fica gancho (EP11). Cada transição já grava timeline + auditoria RN-002. A `StateMachine` não abre transação (o `FluxoExpressoService` controla).

### Idempotência + persistência (HU-076)
- `Cache::lock("expresso:decisao:{id}")` + re-check `status===protocolada` dentro do lock (garantia real). Tabela dedicada IMUTÁVEL **`viability_decisions`** (1:1 com request): `flow` (expresso), `outcome` (deferida/indeferida), `consolidated_result`, `tvl_product_number` (unique, só defere), `per_cnae` jsonb, `rules_versions` jsonb (RN-005), `fundamentacao` jsonb, `reason`, `decided_by_user_id` (null=sistema), `decided_at` (append-only). É a fonte do PDF/TVL (Fase 10/HU-132) e da explicabilidade (Fase 12/HU-099). `TvlNumberGenerator` (TVL-AAAA-NNNNNN, lockForUpdate+unique; teste de concorrência `@group postgis`).

### Segundo evento de domínio `ResultadoEmitido`
- `app/Events/ResultadoEmitido` (`ShouldDispatchAfterCommit`, carrega `ViabilityRequest` + `ViabilityDecision`), disparado após o commit. 3 listeners AUTO-DESCOBERTOS (`ShouldQueue`): `NotificarResultadoExpresso` (HU-077), `ComunicarResultadoRegin` (HU-104 bloqueado), `EnviarViabilidadeSefaz` (HU-110 bloqueado, **só deferida** — HU-134 RN-003).
- **A auditoria autoritativa (HU-078) é SÍNCRONA na transação** — NÃO depende do evento (lição da Fase 8). O evento é só efeitos colaterais desacoplados (mantém a Fase 9 independente das Fases 10/11/13).

### Contratos bloqueados → Fase 13 (binding indisponível, padrão `PropertyRegistryLookup`)
- `app/Services/Regin/ReginParecerNotifier` + `UnavailableReginParecerNotifier` (lança `ReginUnavailableException`).
- `app/Services/Sefaz/SefazViabilidadeGateway` + `UnavailableSefazViabilidadeGateway`.
- `app/Services/Regin/BapRegistry` + `UnavailableBapRegistry` (`findLinkage` retorna null — nada entra em `aguardando_bap` hoje).
- Bindings no `AppServiceProvider::register()`. Os listeners capturam a exceção e **auditam pendência** (`integracoes`, `result: 'bloqueado'`), nunca sucesso falso.
- **HU-134 dormente**: `expresso:indeferir-sem-bap` (parametrizável `expresso.bap.prazo_horas`=48, idempotente `withoutOverlapping`/`onOneServer`) varre `aguardando_bap` vencidas e indefere (`reason: 'indeferido sem atuação'`, `ResultadoEmitido` → Regin, **sem** SEFAZ). Em produção é no-op (varre zero) até o Regin (Fase 13) alimentar `bap_due_at`. Seam `BusinessDeadlineCalculator` para dias úteis (HU-137 feriados pendente — usar horas-calendário + parâmetro até lá).

### Notificação (HU-077)
- `App\Notifications\ResultadoExpressoNotification` (mail, `ShouldQueue`), reusa `EmailLog`/padrão `VerifyEmailQueued`/`LogNotificationSent`. **Sem anexo de TVL.** Assunto/texto parametrizáveis. Toggle `features.notificacao_resultado_expresso` (off → não envia, audita). Canais plenos → EP11.

## Parametrização (HU-014) e testes

**Parâmetros novos** (ParameterSeeder + fallback config/sile.php): `features.fluxo_expresso`, `features.notificacao_resultado_expresso`, `expresso.bap.prazo_horas` (48), `expresso.notificacao.assunto_deferida`/`_indeferida`, `expresso.tvl.prefixo`/`expresso.tvl.padding`. **Reusa** `risco.mapa_encaminhamento` (elegibilidade — não criar novo). Constantes técnicas (timeout do lock, cadência do scheduler, fila, tries/backoff) em `config/sile.php`, fora do catálogo.

**Testes (TDD):**
- Elegibilidade HU-073: baixo→decide; alto/gatilho/não-classificado → em_analise auditado (SQLite + fake SpatialRepository).
- Decisão HU-074/075/RN-009: todos permitido → deferida + TVL + ResultadoEmitido; um nao_permitido → indeferida.
- CRÍTICO (zona pendente): sem zona → veredito pendente → em_analise, **SEM** ResultadoEmitido (anti-fachada).
- Idempotência HU-076: concorrência `@group postgis` → 1 decisão/TVL/evento.
- Auditoria HU-078: rules_version + payload por CNAE dentro da transação (independe do evento).
- Listeners: sem duplicação (CONTAGEM); SEFAZ ignora indeferimento; Regin/SEFAZ auditam pendência sem simular.
- Scheduler: reavaliar redecide órfã; indeferir-sem-bap indefere aguardando_bap vencida (seed) e é no-op sem BAP.
- Comando `expresso:decidir`.

## Waves (para o gsd-planner)

| Wave | Conteúdo |
|---|---|
| **1 — Fundação** | parâmetros HU-014 + permissão ‖ schema (viability_decisions, tvl_sequences, colunas bap_due_at/bap_linked_at) + ViabilityDecision/factory + TvlNumberGenerator + enum DecisionOutcome + transições na StateMachine ‖ contratos Regin/Sefaz/Bap + Unavailable + bindings + evento ResultadoEmitido |
| **2 — Núcleo de decisão** | SolicitacaoViabilityResolver (extração do SimulacaoSolicitacaoService, testes Fase 8 verdes) + FluxoExpressoService (HU-073/074/075, Cache::lock, auditoria síncrona HU-078, TVL) |
| **3 — Gatilho** | listener AvaliarFluxoExpresso (auto-descoberto) + DecidirFluxoExpressoJob + comando scheduler expresso:reavaliar |
| **4 — Efeitos ResultadoEmitido** | NotificarResultadoExpresso + ResultadoExpressoNotification (HU-077) ‖ ComunicarResultadoRegin (HU-104) ‖ EnviarViabilidadeSefaz (HU-110) |
| **5 — HU-134 BAP** | comando expresso:indeferir-sem-bap + parâmetro + degradação dormente + testes com seed |
| **6 — UI retaguarda** | tela de resultado expresso no console (lista + detalhe da ViabilityDecision); cidadão acompanha via Regin (sem PDF) |
| **7 — Fechamento** | seeds dev (incl. seed de zona fictícia SÓ dev/teste para demonstrar deferimento navegável) + expresso:decidir + golden/smoke + verificação integral (@group postgis) + checkpoint humano |

## Pendências SEDUR (registrar, default parametrizável, não travar)
- Política do "médio risco" (decreto não tem médio; default baixo_b → expresso).
- Resposta de condicionante-pergunta não capturada na Fase 8 → interpretação: condicionante não respondida → análise (não elegível).
- `permitido_com_condicoes` no expresso → interpretação: defere com condições (condicionantes no parecer/TVL); confirmar.
- Número de produto TVL: sequência interna (confirmar se há numeração oficial do SAPS).
- Prazo BAP 48h (parametrizável; ativação bloqueada → Fase 13).
- HU-137 (feriados) para contagem em dias úteis do BAP — seam pronto, horas-calendário até lá.
- **Expectativa de gestão:** sem a zona (Quadro 10), o expresso roteia a maioria para análise — o motor liga sozinho quando a base oficial entrar.

## Bloqueios externos → Fase 13
- Regin/Junta (HU-104, HU-133), SEFAZ (HU-110), vinculação BAP (HU-134 ativa) — contratos prontos, bindings indisponíveis honestos. HU-132 (TVL PDF) é Fase 10.
