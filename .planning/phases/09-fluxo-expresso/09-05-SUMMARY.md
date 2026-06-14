---
phase: 09-fluxo-expresso
plan: 05
subsystem: expresso
tags: [hu-073, hu-074, hu-075, hu-076, hu-078, fluxo-expresso, motor-decisao, cache-lock, idempotencia, auditoria-sincrona, after-commit, anti-fachada, rn-009, expresso]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "02"
    provides: "ViabilityDecision (model imutável 1:1) + DecisionOutcome + TvlNumberGenerator + ViabilityRequestStateMachine estendida (protocolada→{deferida,indeferida,em_analise,...})"
  - phase: 09-fluxo-expresso
    plan: "03"
    provides: "Evento ResultadoEmitido (ShouldDispatchAfterCommit, request+decision)"
  - phase: 09-fluxo-expresso
    plan: "04"
    provides: "SolicitacaoViabilityResolver::resolve → ResolvedViability (por_cnae[consulta], consolidado, rules_versions, elegivelExpresso())"
  - phase: 08-solicitacao-viabilidade
    plan: "(protocolo)"
    provides: "ViabilityRequest + ViabilityRequestStatus + AuditService + padrão de transação+evento (ProtocolarSolicitacaoService) + PostgisTestCase"
provides:
  - "FluxoExpressoService::decide(ViabilityRequest, ?User): DecisionResult — o MOTOR de decisão autoritativo (Cache::lock + re-check), reexecuta o resolver FRESCO e defere/indefere/encaminha"
  - "DecisionResult (DTO final readonly): status final + ?decision + ?reason + emitted (se ResultadoEmitido foi disparado)"
  - "Degradação CRÍTICA honesta: sem zona (consolidado pendente) → em_analise SEM decisão e SEM evento (anti-fachada)"
  - "Auditoria SÍNCRONA da decisão (logName 'expresso', event 'decisao', result deferida|indeferida|analise, rules_version) — independe do evento (HU-078)"
  - "ResultadoEmitido disparado SÓ após o commit, só quando defere/indefere"
affects: [09-06, 09-07, 09-08, 09-09, 09-10, 09-11]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Motor de decisão idempotente: Cache::lock por agregado + re-check de status dentro do lock (a 2ª passada recai no re-check e devolve a decisão existente sem redisparar o evento) + unique(viability_request_id)/unique(tvl) como defesa final no pgsql"
    - "Decisão AUTORITATIVA: reexecuta o resolver FRESCO dentro do lock — nunca confia no simulation_snapshot pré-protocolo (orientativo/stale)"
    - "Transação espelhando ProtocolarSolicitacaoService: validar/rotear fora, gravar+transicionar+auditar SÍNCRONO dentro, evento APÓS o commit; auditoria autoritativa não depende do evento (lição Fase 8)"
    - "Robustez idempotente sem fachada: QueryException de unique vira NO-OP (recarrega a decisão real já gravada, emitted=false), nunca deixa o job falhar nem inventa resultado"

key-files:
  created:
    - app/Services/Expresso/FluxoExpressoService.php
    - app/Services/Expresso/DecisionResult.php
    - tests/Feature/Expresso/FluxoExpressoElegibilidadeTest.php
    - tests/Feature/Expresso/FluxoExpressoDecisaoTest.php
    - tests/Feature/Expresso/FluxoExpressoAuditoriaTest.php
    - tests/Feature/Expresso/FluxoExpressoIdempotenciaPostgisTest.php
  modified: []

key-decisions:
  - "Mapeamento RN-009 mora no service (não no enum): nao_permitido→Indeferida; permitido/permitido_com_condicoes→Deferida; pendente→sem decisão (em_analise). O resolver já consolidou o pior caso."
  - "rules_version representativa da auditoria = primeira versão real aplicada na ordem que governa o veredito (Quadro 10→7→11/11A→risco→território); o mapa COMPLETO de versões fica na ViabilityDecision (RN-005)."
  - "encaminharAnalise também AUDITA síncrono ('expresso'/'decisao', result 'analise') além da transição ('solicitacoes'/'transicao') — toda saída do motor deixa trilha (RN-002)."
  - "Robustez idempotente detecta o NO-OP pela EXISTÊNCIA da decisão após a QueryException (portável SQLite/pgsql), não por SQLSTATE; sem decisão, a exceção propaga (job falha honesto)."
  - "decided_by_user_id = actor?->id (null = sistema, fluxo automático); reason null no caminho normal de decisão."

patterns-established:
  - "Anti-fachada do motor: sem o dado real (zona), o veredito é pendente e o motor encaminha à análise SEM decidir/emitir — provado com assertNotDispatched(ResultadoEmitido) e assertDatabaseCount('viability_decisions', 0)."

# Metrics
duration: ~25min
completed: 2026-06-14
---

# Phase 9 Plan 05: FluxoExpressoService — o Motor de Decisão Automática (NÚCLEO da fase)

**O CORE VALUE do SILE entregue: `FluxoExpressoService::decide(request)` é o motor real que, sob `Cache::lock("expresso:decisao:{id}")` + re-check de status (idempotência), REEXECUTA o `SolicitacaoViabilityResolver` FRESCO (autoritativo — nunca o `simulation_snapshot` pré-protocolo) e decide: toggle off → `em_analise`; algum CNAE fora do expresso → `em_analise` (semi-expresso, HU-073/RN-008); veredito consolidado `pendente` (sem a zona — Quadro 10) → `em_analise` SEM criar decisão e SEM disparar evento (degradação CRÍTICA anti-fachada — o motor liga sozinho quando a base oficial entrar); `nao_permitido` → INDEFERE; `permitido(_com_condicoes)` → DEFERE (RN-009). No deferir/indeferir, numa `DB::transaction` cria a `ViabilityDecision` imutável (per_cnae/rules_versions/fundamentação), gera o número TVL só no deferimento, transiciona pela `ViabilityRequestStateMachine` (timeline + auditoria) e AUDITA SÍNCRONO a decisão (HU-078, com rules_version) — tudo antes do commit; o `ResultadoEmitido` é disparado SÓ após o commit. A auditoria autoritativa NÃO depende do evento (provado com `Event::fake`). A idempotência é real (lock + re-check + `unique(viability_request_id)`/`unique(tvl)`), provada em `@group postgis` (1 decisão / 1 TVL / 1 evento). TDD estrito (RED→GREEN com evidência fresca): Elegibilidade 4/4, Decisão 4/4, Auditoria 2/2 (SQLite) + Idempotência 1/1 (Postgres real). Suíte completa SQLite 877/877 (4526 asserções) + grupo postgis 21/21 (121 asserções). ZERO dependência nova.**

## Performance
- **Duration:** ~25 min
- **Tasks:** 3 (elegibilidade/roteamento; defere/indefere+TVL+auditoria+evento; idempotência postgis)
- **Files modified:** 6 criados — ZERO dependência nova

## Contrato para os planos seguintes (assinaturas exatas)

### `App\Services\Expresso\FluxoExpressoService`

```php
public function decide(ViabilityRequest $request, ?User $actor = null): DecisionResult
```

- **Idempotente e autoritativo:** roda dentro de `Cache::lock("expresso:decisao:{$request->id}", config('sile.expresso.lock.ttl_segundos', 10))->block(...)`. Dentro do lock RECARREGA (`refresh()`) e re-checa `status === Protocolada`; se já saiu, devolve a decisão existente (ou o estado atual) com `emitted=false`, SEM redecidir.
- **Reexecução FRESCA:** `app(SolicitacaoViabilityResolver::class)->resolve($request)` — nunca o `simulation_snapshot`.
- **Roteamento:**
  - `Settings::get('features.fluxo_expresso', config(...))` false → `encaminharAnalise('fluxo expresso desativado')`.
  - `! $resolved->elegivelExpresso()` → `encaminharAnalise('atividade fora do fluxo expresso (análise técnica)')` (semi-expresso, HU-073).
  - `$resolved->consolidado === ResultadoViabilidade::Pendente->value` → `encaminharAnalise('veredito locacional pendente — zona urbanística pendente SEDUR')` — **SEM decisão, SEM evento** (anti-fachada).
  - senão → emissão: `nao_permitido` → `DecisionOutcome::Indeferida`; `permitido`/`permitido_com_condicoes` → `DecisionOutcome::Deferida`.

### `App\Services\Expresso\DecisionResult` (final readonly)

| Campo | Tipo | Conteúdo |
|---|---|---|
| `status` | `ViabilityRequestStatus` | Estado final (`em_analise` / `deferida` / `indeferida`) |
| `decision` | `?ViabilityDecision` | A decisão criada (null quando encaminha à análise) |
| `reason` | `?string` | Motivo do encaminhamento à análise (null quando decide) |
| `emitted` | `bool` | `true` SE o `ResultadoEmitido` foi disparado nesta chamada |

Helpers: `DecisionResult::paraAnalise(string $reason)` (status `EmAnalise`, decision null, emitted false); `DecisionResult::decidida(ViabilityDecision $decision, bool $emitted = true)` (status derivado de `isDeferida()`).

### O que a emissão grava (DB::transaction)

- **`ViabilityDecision::create`**: `flow='expresso'`, `outcome` (deferida/indeferida), `consolidated_result` = `$resolved->consolidado`, `tvl_product_number` = `$this->tvl->generate()` **só no deferimento** (null no indeferimento), `per_cnae` (cnae/cnae_formatado/is_primary/tendencia/tendencia_label/fluxo/fundamentacao por CNAE), `rules_versions` = `$resolved->rules_versions`, `fundamentacao` (união sem duplicar das fundamentações dos CNAEs), `reason=null`, `decided_by_user_id` = `$actor?->id` (null=sistema), `decided_at=now()`.
- **Transição** pela `StateMachine`: `protocolada → deferida|indeferida` (timeline + auditoria `solicitacoes/transicao`).
- **Auditoria SÍNCRONA HU-078**: `audit->log(logName: 'expresso', event: 'decisao', result: 'deferida'|'indeferida', rulesVersion: <representativa>, subject: $request, properties: [viability_request_id, protocol_number, outcome, consolidado, tvl_product_number, por_cnae resumido])`.
- **APÓS o commit**: `ResultadoEmitido::dispatch($request, $decision)`.

### Auditoria do encaminhamento à análise

`audit->log(logName: 'expresso', event: 'decisao', result: 'analise', rulesVersion: <representativa|null>, subject: $request, properties: [..., 'motivo' => $reason, 'consolidado', 'por_cnae'])` + a transição `solicitacoes/transicao` (`protocolada → em_analise`). NÃO dispara `ResultadoEmitido`.

## Como o 09-06 consome (job/comando)

`DecidirFluxoExpressoJob` (ShouldQueue) e `expresso:decidir {solicitacao}` chamam `app(FluxoExpressoService::class)->decide($request)`. `decide()` já é idempotente (lock + re-check + unique) e captura a corrida rara como NO-OP — o job pode reprocessar com segurança (`expresso:reavaliar` = rede de segurança). O `DecisionResult` informa o desfecho e se o evento foi disparado.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: elegibilidade + roteamento (lock/re-check/toggle/semi-expresso/sem-zona)** — `f64aae2` (feat) — RED: 4 erros (`Target class [FluxoExpressoService] does not exist`) → GREEN: `FluxoExpressoElegibilidadeTest` 4/4 (24 asserções). Emissão deixada como stub explícito.
2. **Task 2: defere/indefere + ViabilityDecision + TVL + auditoria síncrona + ResultadoEmitido** — `8693668` (feat) — RED: 6 erros (stub "implementada na Task 2") → GREEN: `FluxoExpressoDecisaoTest|FluxoExpressoAuditoriaTest` 6/6 (44 asserções).
3. **Task 3: idempotência sob concorrência real** — `b657e1f` (test) — `FluxoExpressoIdempotenciaPostgisTest` 1/1 (10 asserções) no Postgres real (`sile-pgsql`).

**Plan metadata:** `docs(09-05)` (este SUMMARY + STATE).

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-073 RN-008 — toggle off / semi-expresso → análise | FluxoExpressoElegibilidadeTest | em_analise, sem decisão, sem evento |
| HU-073 CA-03 / anti-fachada — sem zona → análise SEM evento | FluxoExpressoElegibilidadeTest (CRÍTICO) | assertNotDispatched + assertDatabaseCount 0 |
| HU-074 / RN-009 — defere quando permitido (+TVL) | FluxoExpressoDecisaoTest | outcome deferida, tvl não-nulo, evento |
| HU-074 / RN-006 — permitido_com_condicoes defere | FluxoExpressoDecisaoTest | consolidated_result permitido_com_condicoes |
| HU-075 / RN-009 — indefere quando nao_permitido | FluxoExpressoDecisaoTest | outcome indeferida, tvl NULL, evento |
| HU-076 / RN-005 — per_cnae/rules_versions/fundamentação | FluxoExpressoDecisaoTest | persistidos na decisão |
| HU-078 CA-02 — auditoria síncrona com versões | FluxoExpressoAuditoriaTest | Activity 'expresso'/'decisao' com Event::fake |
| HU-076 — idempotência (1 decisão/1 TVL/1 evento) | FluxoExpressoIdempotenciaPostgisTest | @group postgis, Postgres real |

## Decisions Made

- **Mapeamento RN-009 no service**, não no enum `DecisionOutcome` (mínimo): a consolidação já vem do resolver (pior caso); o service só mapeia `nao_permitido→Indeferida`, `permitido(_com_condicoes)→Deferida`, `pendente→sem decisão`.
- **rules_version representativa da auditoria** = primeira versão real aplicada na ordem que governa o veredito (Quadro 10→7→11/11A→risco→território); o mapa COMPLETO fica em `viability_decisions.rules_versions` (RN-005) — a coluna `rules_version` da Activity é uma referência compacta, não a fonte única.
- **encaminharAnalise audita síncrono** (`'expresso'/'decisao'`, result `'analise'`) além da transição — toda saída do motor deixa trilha (RN-002), inclusive os caminhos que não emitem.
- **NO-OP idempotente detectado pela existência da decisão** após a `QueryException` (portável SQLite/pgsql); sem decisão, a exceção propaga (o job falha honesto). O caminho normal continua protegido por lock + re-check.
- **Evento após o commit, fora da transação** (`ResultadoEmitido::dispatch` após `DB::transaction` retornar) + `ShouldDispatchAfterCommit` como defesa adicional.

## Deviations from Plan

None — plano executado exatamente como escrito (3 tasks; service + DTO + 4 feature tests com os cenários especificados). A única correção foi numa asserção de teste própria durante o GREEN da Task 2 (`assertNotDispatched`→`assertDispatched` no teste de auditoria: com `Event::fake` o disparo É registrado; o ponto do teste — auditoria síncrona presente mesmo com os listeners fakeados — segue íntegro), não no código de produção.

## Issues Encountered

- **Semântica do `Event::fake` no teste de auditoria:** a primeira escrita asseriu `assertNotDispatched` por engano — o fake REGISTRA o disparo (só impede os listeners reais). Corrigido para `assertDispatched`; a prova de que a auditoria NÃO depende do evento permanece (a Activity existe mesmo com o evento fakeado, pois é gravada SÍNCRONA na transação, não por listener).
- **`@group postgis` exige o container:** rodado contra `sile-pgsql` (porta 5433) após `php artisan geo:preparar-banco-de-testes`; sem o servidor o PostgisTestCase faz skip honesto (e o CI com `POSTGIS_TESTS_REQUIRED=true` transforma em falha).

## Verification (evidência fresca)

- **RED Task 1:** `--filter=FluxoExpressoElegibilidadeTest` → 4 erros (`Target class [FluxoExpressoService] does not exist`). **GREEN:** 4/4 (24 asserções).
- **RED Task 2:** `--filter="FluxoExpressoDecisaoTest|FluxoExpressoAuditoriaTest"` → 6 erros (stub) → **GREEN:** 6/6 (44 asserções).
- **Task 3 (@group postgis):** `--group=postgis --filter=FluxoExpressoIdempotenciaPostgisTest` → 1/1 (10 asserções), `assertSame('pgsql', ...)` confirma o engine real.
- **Filtro do plano (SQLite):** `--filter="FluxoExpressoElegibilidadeTest|FluxoExpressoDecisaoTest|FluxoExpressoAuditoriaTest"` → **10/10 (68 asserções)**.
- **Suíte completa SQLite:** `--exclude-group=postgis` → **877 testes, 877 passaram** (4526 asserções) — zero regressão (baseline 867 do 09-03/04 → +10 deste plano).
- **Grupo postgis completo:** `--group=postgis` → **21/21 (121 asserções)** com `sile-pgsql` healthy.
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).
- **Greps de aceite:** `Cache::lock` + re-check `status !== Protocolada` + `Pendente` + `DB::transaction` + `logName: 'expresso'` + `tvl->generate` + `ResultadoEmitido::dispatch` (após o commit) presentes no service.

## Next Phase Readiness

- **09-06** (job + comando): chama `decide()` (idempotente; reprocesso seguro). Usa `expresso.fila`/`expresso.job` (09-01) e o `DecisionResult`.
- **09-07/08/09** (efeitos): listeners AUTO-DESCOBERTOS no `ResultadoEmitido` (notificação HU-077, Regin HU-104, SEFAZ HU-110 — SÓ deferimento). A auditoria da decisão já é síncrona; os listeners só fazem efeitos colaterais.
- **09-10** (HU-134 dormente): reusa a emissão para o indeferimento sem atuação BAP; `aguardando_bap→indeferida` já no mapa.
- **09-11** (retaguarda): exibe a `ViabilityDecision` (outcome/label, consolidated_result, tvl_product_number, fundamentacao, per_cnae).
- **Bloqueio honesto mantido:** sem a zona oficial (Quadro 10/SEDUR) o consolidado é `pendente` → o motor roteia para `em_analise` SEM decidir/emitir; liga sozinho quando a base entrar (muda a carga, não a lógica). Provado em teste (anti-fachada).

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
