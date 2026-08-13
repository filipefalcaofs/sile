---
phase: 10-analise-tecnica-sedur
plan: "10"
subsystem: analise
tags: [hu-085, hu-086, hu-087, hu-088, hu-089, decisao-humana, viability-decision, flow-analise-tecnica, rn-004, tvl, auditoria-sincrona, after-commit, encerramento, anti-fachada]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur
    plan: "09"
    provides: "AnalysisRecord FINALIZADA (status finalizada, per_cnae com status_escolhido por CNAE, conditions, parecer, engine_rules_versions) — a fonte do parecer/condicionantes da decisão"
  - phase: 10-analise-tecnica-sedur
    plan: "03"
    provides: "ViabilityRequestStateMachine estendida: em_analise→{deferida,indeferida} (decisão final = encerramento HU-089)"
  - phase: 10-analise-tecnica-sedur
    plan: "02"
    provides: "tvl_documents/analysis_records schema (não tocado aqui; consumido a jusante por 10-13)"
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "padrão de decisão a espelhar: DB::transaction (ViabilityDecision + transição + auditoria síncrona) + ResultadoEmitido after-commit + robustez idempotente (QueryException da unique)"
  - phase: 09-fluxo-expresso
    plan: "02"
    provides: "ViabilityDecision (flow/decided_by_user_id/per_cnae/rules_versions/fundamentacao jsonb) + DecisionOutcome + TvlNumberGenerator (número TVL concorrência-seguro)"
provides:
  - "App\\Services\\Analise\\AnaliseTecnicaDecisionService::decide(AnalysisRecord, User): AnaliseDecisionResult — decisão técnica HUMANA a partir da ficha finalizada (RN-004), reusando viability_decisions com flow 'analise_tecnica'"
  - "App\\Services\\Analise\\AnaliseDecisionResult (readonly): outcome (DecisionOutcome) + decision (ViabilityDecision) + emitted (bool)"
  - "Decisão humana grava decided_by_user_id=analista (≠ null) — distingue do fluxo expresso (null=sistema); número TVL só no deferimento; encerramento via transição em_analise→deferida|indeferida"
  - "ResultadoEmitido reusado após o commit (os 3 listeners da Fase 9 servem os 2 flows; Regin/SEFAZ seguem BLOQUEADOS honestos → Fase 13)"
affects: [10-13, 10-15, 10-18]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Reuso da ViabilityDecision para o 2º flow (analise_tecnica) sem reescrever a Fase 9 — mesma tabela/fonte do TVL/explicabilidade, fontes diferentes (ficha humana × resolver puro)"
    - "Anti-fachada INVERSA: o humano PODE deferir o caso PENDENTE (sem zona) com fundamentação própria da ficha — diferente do FluxoExpressoService, que recusa o pendente (jamais inventa decisão sem o dado real)"
    - "Decisão nasce da FICHA finalizada (status_escolhido por CNAE), não de um recomputo do motor — RN-004 lida sobre o que o analista registrou"
    - "Espelha FluxoExpressoService::emitir (transação grava+transiciona+audita síncrono; evento após o commit; QueryException da unique vira NO-OP idempotente) — zero dependência nova"

key-files:
  created:
    - app/Services/Analise/AnaliseTecnicaDecisionService.php
    - app/Services/Analise/AnaliseDecisionResult.php
    - tests/Feature/Analise/AnaliseTecnicaDecisionTest.php
    - tests/Feature/Analise/AnaliseTecnicaAuditoriaTest.php
  modified: []

key-decisions:
  - "RN-004 no service: DEFERE só quando TODAS as CNAEs da ficha têm status_escolhido='deferida'; QUALQUER outra escolha (indeferida, ainda em análise, ausente) INDEFERE — nunca defere por omissão (anti-fachada)"
  - "consolidated_result derivado da decisão humana: indeferida→nao_permitido; deferida→permitido_com_condicoes quando há condicionantes na ficha, senão permitido (coerente com o rótulo da ViabilityDecisionResource, sem inventar)"
  - "fundamentacao da decisão = união sem duplicar das fundamentações por CNAE da ficha (do motor OU do próprio analista no caso pendente); o parecer/condicionantes em prosa ficam na ficha (lidos pelo TVL 10-13), não duplicados na decisão"
  - "Guarda explícita request em em_analise (DomainException) ANTES de criar a decisão — impede que a decisão humana atue sobre processo protocolada (que é tarefa do expresso); a transição é o encerramento HU-089 (estado final)"
  - "ViabilityDecision.reason fica null no caminho normal (como no expresso); decided_by_user_id=analista (≠ null) é o que distingue a decisão humana"

patterns-established:
  - "Segundo produtor da ViabilityDecision (flow 'analise_tecnica') reusando integralmente DecisionOutcome/TvlNumberGenerator/ResultadoEmitido/ViabilityDecisionResource — a tela de retaguarda e o TVL servem os 2 flows sem ramificação"

# Metrics
duration: ~20min
completed: 2026-06-14
---

# Phase 10 Plan 10: Decisão técnica humana — AnaliseTecnicaDecisionService (HU-085/086/087/088/089)

**O fechamento da decisão HUMANA — a contraparte do FluxoExpressoService para os casos que a lei manda o humano decidir. `AnaliseTecnicaDecisionService::decide(record, analista)` conclui o processo a partir da FICHA FINALIZADA (não de um recomputo do motor): RN-004 — TODAS as CNAEs com `status_escolhido='deferida'` → DEFERE; QUALQUER outra escolha → INDEFERE. Numa `DB::transaction` (espelhando `FluxoExpressoService::emitir`) cria a `ViabilityDecision` na MESMA tabela da Fase 9 com `flow='analise_tecnica'` e `decided_by_user_id=analista` (≠ null — distingue da decisão automática, onde é null=sistema), `per_cnae`/`rules_versions`/`fundamentacao` VINDOS DA FICHA, número TVL (via `TvlNumberGenerator`) SÓ no deferimento; transiciona `em_analise→deferida|indeferida` (= encerramento HU-089, estado final) e AUDITA SÍNCRONO (`analise`/`decisao`, com `rules_version`, `por_cnae` e `decided_by`) — tudo antes do commit. O `ResultadoEmitido` é disparado SÓ após o commit (os 3 listeners da Fase 9 reusam — notificação HU-077; Regin HU-104 / SEFAZ HU-110 seguem BLOQUEADOS honestos → Fase 13). A auditoria autoritativa NÃO depende do evento (provado com `Event::fake`). Diferença CRÍTICA do expresso: o humano PODE DEFERIR o caso PENDENTE (sem a zona oficial) com fundamentação própria da ficha — é exatamente o caso que exige um humano (anti-fachada inversa). Rascunho NÃO decide (CA-03 → `DomainException`). Robustez idempotente: a `unique(viability_request_id)` da Fase 9 barra a 2ª decisão (QueryException → NO-OP, `emitted=false`). TDD estrito (RED→GREEN com evidência fresca): `AnaliseTecnicaDecisionTest` 4/4 + `AnaliseTecnicaAuditoriaTest` 3/3 (filtro do plano 7/7, 38 asserções). Suíte completa SQLite 1048/1048 (5281) — zero regressão. ZERO dependência nova; nenhuma reescrita da Fase 9. Serviço PURO (sem rota): o endpoint deferir/indeferir/encerrar é 10-15.**

## Performance
- **Duration:** ~20 min
- **Tasks:** 2 (decisão a partir da ficha + DTO; auditoria síncrona dedicada)
- **Files:** 4 criados (serviço + DTO + 2 testes), 0 modificados — ZERO dependência nova

## Contrato (assinaturas — insumo de 10-13/10-15/10-18)

### `App\Services\Analise\AnaliseTecnicaDecisionService`
```php
public function __construct(
    private ViabilityRequestStateMachine $stateMachine,
    private TvlNumberGenerator $tvl,
    private AuditService $audit,
) {}

// Conclui o processo a partir da ficha FINALIZADA. Exige ficha finalizada
// (rascunho → DomainException, CA-03) e processo em em_analise (senão DomainException).
public function decide(AnalysisRecord $record, User $analista): AnaliseDecisionResult;
```

Fluxo de `decide`:
1. `! $record->isFinalizada()` → **DomainException** (CA-03; rascunho não decide).
2. `$request = $record->viabilityRequest()->firstOrFail()`; `$request->status !== EmAnalise` → **DomainException** (só conclui processo em análise).
3. `per_cnae` da ficha vazio → **DomainException** (sem atividades para decidir — anti-fachada).
4. **outcome (RN-004):** DEFERE se TODAS as CNAEs têm `status_escolhido='deferida'`; QUALQUER outra → INDEFERE.
5. **`DB::transaction`:** `ViabilityDecision::create` (ver abaixo) → `stateMachine->transition(em_analise→deferida|indeferida)` (encerramento HU-089) → `audit->log('analise','decisao', result=outcome, rulesVersion, por_cnae+decided_by)`.
6. **catch `QueryException`** (unique `viability_request_id`): decisão já existe → NO-OP (`emitted=false`); sem decisão → rethrow (falha honesta).
7. **APÓS o commit:** `ResultadoEmitido::dispatch($request, $decision)`.

### `App\Services\Analise\AnaliseDecisionResult` (final readonly)
| Campo | Tipo | Conteúdo |
|---|---|---|
| `outcome` | `DecisionOutcome` | `Deferida` / `Indeferida` |
| `decision` | `ViabilityDecision` | A decisão criada (flow `analise_tecnica`) |
| `emitted` | `bool` | `true` se o `ResultadoEmitido` foi disparado nesta chamada (`false` na corrida idempotente) |

### O que a decisão grava (DB::transaction)
- **`ViabilityDecision::create`**: `flow='analise_tecnica'`; `outcome`; `consolidated_result` (indeferida→`nao_permitido`; deferida→`permitido_com_condicoes` se há condicionantes na ficha, senão `permitido`); `tvl_product_number=$this->tvl->generate()` **só no deferimento** (null no indeferimento); `per_cnae` = per_cnae DA FICHA; `rules_versions` = `engine_rules_versions` da ficha (`[]` no caso pendente); `fundamentacao` = união das fundamentações por CNAE da ficha; `reason=null`; **`decided_by_user_id=$analista->id`** (≠ null); `decided_at=now()`.
- **Transição** `em_analise → deferida|indeferida` (timeline + auditoria `solicitacoes/transicao`) — FINAL (= encerramento HU-089).
- **Auditoria SÍNCRONA HU-078**: `log_name='analise'`, `event='decisao'`, `result=deferida|indeferida`, `rules_version` representativa da ficha, `subject=$request`, properties `{viability_request_id, protocol_number, revision, outcome, tvl_product_number, decided_by, por_cnae}`.
- **APÓS o commit**: `ResultadoEmitido::dispatch($request, $decision)`.

## Diferença em relação ao fluxo expresso (decisão central do arquiteto)
| | FluxoExpressoService (Fase 9) | AnaliseTecnicaDecisionService (10-10) |
|---|---|---|
| Fonte da decisão | Resolver FRESCO (motor puro) | FICHA finalizada do analista |
| `flow` | `expresso` | `analise_tecnica` |
| `decided_by_user_id` | null (sistema) | **analista (≠ null)** |
| Caso PENDENTE (sem zona) | encaminha à análise SEM decidir/emitir | **DEFERE/indefere com fundamentação da ficha** (o humano decide) |
| Tabela / TVL / evento | ViabilityDecision / TvlNumberGenerator / ResultadoEmitido | **os MESMOS** (reuso, sem reescrita) |

## Mapa CA → teste (provado)
| HU / RN | Teste | Evidência |
|---|---|---|
| HU-086 RN-004 — defere se todas deferidas | `AnaliseTecnicaDecisionTest::test_defere_quando_todas_as_cnaes_estao_deferidas_na_ficha` | flow `analise_tecnica`, outcome deferida, TVL não-nulo, decided_by analista, status deferida, evento |
| HU-087 RN-004 — indefere se alguma indeferida | `...::test_indefere_quando_alguma_cnae_esta_indeferida_na_ficha` | outcome indeferida, sem TVL, status indeferida |
| Caso pendente — humano defere sem zona | `...::test_defere_o_caso_pendente_sem_zona_com_fundamentacao_da_ficha` | engine consolidado pendente → DEFERE com fundamentação da ficha |
| HU-086 CA-03 — bloqueio (ficha rascunho) | `...::test_recusa_decidir_quando_a_ficha_ainda_esta_em_rascunho` | DomainException; 0 decisões; status segue em_analise; evento não disparado |
| HU-078 — auditoria síncrona | `AnaliseTecnicaAuditoriaTest::test_auditoria_da_decisao_e_sincrona_e_independe_do_evento` | `analise`/`decisao` com rules_version + por_cnae + decided_by, com `Event::fake` |
| HU-089 — encerramento (transição final) | ambos os testes de decisão + `...::test_transicao_da_decisao_tambem_e_auditada` | em_analise→deferida|indeferida auditada |
| Decisão humana ≠ sistema | `...::test_decisao_registra_decided_by_do_analista_nao_nulo` | decided_by_user_id = analista (não null) |

## Task Commits
TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):
1. **Task 1: decisão a partir da ficha + ViabilityDecision + TVL + transição + ResultadoEmitido** — `a8c2719` (feat) — RED: 4 erros (`Target class [AnaliseTecnicaDecisionService] does not exist`) → GREEN: `AnaliseTecnicaDecisionTest` 4/4 (25 asserções).
2. **Task 2: auditoria SÍNCRONA dedicada (independente do evento; decided_by analista)** — `a98c9fb` (test) — `AnaliseTecnicaAuditoriaTest` 3/3 (13 asserções). A auditoria já é intrínseca à transação da Task 1; este teste PROVA a propriedade (isolamento via `Event::fake`).

## Decisions Made
- **RN-004 estrito (anti-fachada):** DEFERE só com TODAS as CNAEs `status_escolhido='deferida'`; qualquer outra escolha (indeferida, ainda em análise, ausente) INDEFERE — nunca defere por omissão.
- **`consolidated_result` derivado da decisão humana** (não de um recomputo): indeferida→`nao_permitido`; deferida→`permitido_com_condicoes` se há condicionantes na ficha, senão `permitido` — mantém o rótulo da `ViabilityDecisionResource` coerente sem inventar veredito.
- **`fundamentacao` = união das fundamentações por CNAE da ficha** (do motor OU do analista no caso pendente). O parecer/condicionantes em prosa ficam na FICHA (lidos pelo TVL 10-13), não duplicados na decisão.
- **Guarda `em_analise` ANTES de criar a decisão** — a decisão humana não atua sobre processo `protocolada` (tarefa do expresso); a transição é o encerramento HU-089 (estado final, anti-regressão garantida pela StateMachine).
- **`reason=null`, `decided_by_user_id=analista`** — o que distingue a decisão humana da automática (null=sistema).

## Deviations from Plan
Nenhum desvio de escopo. Plano executado como escrito (2 tasks; serviço + DTO + 2 feature tests com os cenários especificados). ZERO dependência nova; nenhuma reescrita da Fase 9.

Nota de execução (dentro do escopo): a auditoria síncrona (HU-078) é intrínseca à transação da decisão (Task 1, em `registrarDecisao`); o teste dedicado da Task 2 é uma prova de propriedade (auditoria gravada mesmo com `ResultadoEmitido` fakeado + `decided_by` do analista), não código de produção novo — coerente com a separação de tasks do plano.

## Authentication Gates
Nenhum — sem CLI/credencial externa neste plano (serviço puro, route-free).

## Issues Encountered (cross-plan)
- Waves paralelas (10-11 portal, 10-12 malha fina) na mesma working dir: NÃO toquei arquivos fora do meu escopo. Staging individual dos meus 4 arquivos (nunca `git add -A`). Arquivos `.cursor/` não versionados foram ignorados.
- `STATE.md` NÃO alterado (consolidação a cargo do orquestrador — instrução do user query; evita clobber entre executores concorrentes).
- A suíte completa subiu para 1048 testes (10-09 era 1034) por conta do trabalho commitado das waves paralelas — todas verdes (os 7 deste plano incluídos).

## Verification (evidência fresca)
- **RED Task 1:** `--filter=AnaliseTecnicaDecisionTest` → 4 erros (`Target class [...AnaliseTecnicaDecisionService] does not exist`). **GREEN:** 4/4 (25 asserções).
- **Task 2:** `--filter=AnaliseTecnicaAuditoriaTest` → 3/3 (13 asserções).
- **Filtro do plano:** `--filter="AnaliseTecnicaDecisionTest|AnaliseTecnicaAuditoriaTest"` → **7/7 (38 asserções)**.
- **`vendor/bin/pint --dirty --format agent`** → passed (em cada task).
- **Suíte completa SQLite:** `--exclude-group=postgis` → **1048/1048 (5281 asserções)** — zero regressão.
- **Greps de aceite:** `'analise_tecnica'` (linhas 26, 124) + `ResultadoEmitido::dispatch` (linha 102) + `Finalizada` (linhas 60, 62) presentes em `AnaliseTecnicaDecisionService.php`.

## Next Phase Readiness
- **10-13** (TVL PDF do deferimento humano): lê a `ViabilityDecision` (flow `analise_tecnica`, `tvl_product_number` não-nulo no deferimento) + as condicionantes/parecer da FICHA finalizada. O `TvlPdfService` serve os 2 flows pela mesma decisão.
- **10-15** (endpoint deferir/indeferir/encerrar): injeta `AnaliseTecnicaDecisionService::decide($record, $analista)` no controller gated por `analisar-processos`; traduz `DomainException` (rascunho/não-em-análise) em 422; o `AnaliseDecisionResult` informa o desfecho e se o evento foi disparado. Reusa `ViabilityDecisionResource` para o payload de leitura.
- **10-18** (golden/smoke da decisão humana): exercita defere/indefere/caso-pendente ponta a ponta sobre a ficha finalizada.
- **Bloqueio honesto mantido:** Regin/SEFAZ disparam via `ResultadoEmitido` (reuso dos listeners) mas seguem BLOQUEADOS (auditam pendência, nunca "enviado") → Fase 13. A decisão/TVL/auditoria são reais.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
