---
phase: 09-fluxo-expresso
plan: 02
subsystem: database
tags: [postgresql, eloquent, enum, state-machine, jsonb, concurrency, sequence-generator, tvl, audit]

# Dependency graph
requires:
  - phase: 08-solicitacao-viabilidade
    provides: "ProtocolNumberGenerator (padrão lockForUpdate), ViabilityRequestStateMachine (mapa estendido), ViabilityRequest/ViabilityRequestStatus (ganchos de estado), protocol_sequences (padrão do contador), PostgisTestCase + @group postgis"
  - phase: 09-fluxo-expresso/09-01
    provides: "config/sile.php bloco expresso (fallback HU-014 expresso.tvl.*); o gerador tem default inline TVL/6, então NÃO depende do seeder"
provides:
  - "Tabela IMUTÁVEL viability_decisions (1:1, append-only) — fonte do PDF/TVL (Fase 10) e da explicabilidade (Fase 12)"
  - "Tabela tvl_sequences (contador por ano) + colunas dormentes bap_due_at/bap_linked_at em viability_requests (HU-134)"
  - "Enum DecisionOutcome (deferida/indeferida) com label()"
  - "Model ViabilityDecision (casts jsonb→array, relações, isDeferida) + ViabilityDecisionFactory (deferida default; states indeferida/semAtuacaoBap)"
  - "Model TvlSequence + TvlSequenceFactory"
  - "ViabilityRequest::decision() HasOne + casts bap_*→datetime"
  - "TvlNumberGenerator::generate(?int $year): string concorrência-seguro e parametrizado"
  - "ViabilityRequestStateMachine estendida: protocolada→{cancelada,em_analise,deferida,indeferida,aguardando_bap}; aguardando_bap→{deferida,indeferida,em_analise}"
affects: [09-05, 09-07, 09-08, 09-09, 09-10, 09-11, 09-12]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Model imutável 1:1 (unique FK) append-only, SEM HasAuditoria (auditoria síncrona no service, HU-078)"
    - "Gerador de número concorrência-seguro espelhando ProtocolNumberGenerator (lockForUpdate em transação + unique como defesa final)"
    - "Máquina de estados só ESTENDIDA (adição ao mapa) — anti-regressão da Fase 8 travada por teste"

key-files:
  created:
    - database/migrations/2026_06_14_163233_create_viability_decisions_table.php
    - database/migrations/2026_06_14_163233_create_tvl_sequences_table.php
    - database/migrations/2026_06_14_163233_add_bap_columns_to_viability_requests_table.php
    - app/Enums/DecisionOutcome.php
    - app/Models/ViabilityDecision.php
    - app/Models/TvlSequence.php
    - database/factories/ViabilityDecisionFactory.php
    - database/factories/TvlSequenceFactory.php
    - app/Services/Expresso/TvlNumberGenerator.php
    - tests/Feature/Expresso/ExpressoSchemaTest.php
    - tests/Feature/Expresso/ViabilityDecisionTest.php
    - tests/Feature/Expresso/TvlNumberGeneratorTest.php
    - tests/Feature/Expresso/TvlNumberGeneratorPostgisTest.php
  modified:
    - app/Models/ViabilityRequest.php
    - app/Services/Solicitacao/ViabilityRequestStateMachine.php
    - tests/Feature/Solicitacao/ViabilityRequestStateMachineTest.php

key-decisions:
  - "jsonb (não json) para per_cnae/rules_versions/fundamentacao — espelha viability_requests da Fase 8, portável SQLite/pgsql"
  - "bap_due_at/bap_linked_at FORA do fillable (gravados via forceFill, como protocol_number/status) — escritos pela rotina do BAP no Regin (Fase 13)"
  - "DecisionOutcome mínimo (2 casos + label); o mapeamento ResultadoViabilidade→outcome fica no FluxoExpressoService (09-05), onde mora a consolidação RN-009"
  - "ViabilityDecision NÃO usa HasAuditoria: a auditoria da decisão é síncrona no service (HU-078), não diff de atributos"

patterns-established:
  - "Decisão append-only 1:1: unique(viability_request_id) garante uma única decisão por processo"
  - "TVL único só no deferimento: tvl_product_number unique nullable (NULLs distintos no índice)"

# Metrics
duration: 18min
completed: 2026-06-14
---

# Phase 9 Plan 02: Fundação de Dados e Domínio da Decisão Expressa

**Tabela imutável `viability_decisions` (1:1, append-only) + `tvl_sequences` + relógio BAP dormente, enum `DecisionOutcome`, `TvlNumberGenerator` concorrência-seguro (lockForUpdate, provado em Postgres real) e a `ViabilityRequestStateMachine` estendida com os estados de decisão — sem tocar o protocolo da Fase 8.**

## Performance

- **Duration:** ~18 min
- **Started:** 2026-06-14T16:31:40Z
- **Completed:** 2026-06-14T16:49:00Z
- **Tasks:** 3
- **Files modified:** 16 (13 criados, 3 modificados)

## Accomplishments
- `viability_decisions` IMUTÁVEL 1:1 (unique `viability_request_id`; `tvl_product_number` unique nullable, só no deferimento; `per_cnae`/`rules_versions`/`fundamentacao` jsonb) — fonte do PDF/TVL (Fase 10) e da explicabilidade (Fase 12).
- `tvl_sequences` (year unique, last_number) + colunas dormentes `bap_due_at`/`bap_linked_at` (HU-134) em `viability_requests`.
- Enum `DecisionOutcome` + models `ViabilityDecision`/`TvlSequence` com factories e states; relação `ViabilityRequest::decision()`.
- `TvlNumberGenerator` concorrência-seguro e parametrizado (default inline `TVL`/`6`), unicidade em SQLite e concorrência real em `@group postgis`.
- `ViabilityRequestStateMachine` estendida (só ADIÇÃO) — Fase 8 sem regressão.

## Contrato para os planos seguintes (nomes EXATOS)

### Tabelas
- **`viability_decisions`**: `id`, `viability_request_id` (FK→viability_requests, cascadeOnDelete, **unique** 1:1), `flow` (string, default `'expresso'`), `outcome` (string `deferida`/`indeferida`), `consolidated_result` (string `permitido`/`permitido_com_condicoes`/`nao_permitido`), `tvl_product_number` (string **unique** nullable — só deferimento), `per_cnae` (jsonb), `rules_versions` (jsonb), `fundamentacao` (jsonb), `reason` (text nullable), `decided_by_user_id` (FK→users nullable, **null = sistema**), `decided_at` (timestamp), `created_at`/`updated_at`.
- **`tvl_sequences`**: `id`, `year` (integer **unique**), `last_number` (unsignedBigInteger default 0), timestamps.
- **`viability_requests`** ganhou: `bap_due_at` (timestamp nullable), `bap_linked_at` (timestamp nullable).

### Enum `App\Enums\DecisionOutcome` (string)
- `DecisionOutcome::Deferida` = `'deferida'`; `DecisionOutcome::Indeferida` = `'indeferida'`.
- `label(): string` → `'Deferida'` / `'Indeferida'`.
- O mapeamento `ResultadoViabilidade → DecisionOutcome` NÃO está aqui (decisão do 09-05): `permitido`/`permitido_com_condicoes`→Deferida; `nao_permitido`→Indeferida; `pendente`→sem decisão (vai para `em_analise`).

### Model `App\Models\ViabilityDecision`
- `$fillable`: `viability_request_id`, `flow`, `outcome`, `consolidated_result`, `tvl_product_number`, `per_cnae`, `rules_versions`, `fundamentacao`, `reason`, `decided_by_user_id`, `decided_at`.
- casts: `outcome`→`DecisionOutcome`; `per_cnae`/`rules_versions`/`fundamentacao`→`array`; `decided_at`→`datetime`.
- relações: `viabilityRequest(): BelongsTo`; `decidedBy(): BelongsTo` (User, nullable).
- helper: `isDeferida(): bool`.
- **Append-only / SEM HasAuditoria** (auditoria síncrona no service, HU-078).

### Model `App\Models\TvlSequence`
- `#[Fillable(['year', 'last_number'])]`; casts integer. Espelha `ProtocolSequence`.

### `App\Models\ViabilityRequest` (estendido)
- `decision(): HasOne` → `ViabilityDecision`.
- casts adicionados: `bap_due_at`→`datetime`, `bap_linked_at`→`datetime`.
- `bap_*` **fora do fillable** — gravar via `forceFill(...)->save()` (padrão de protocol_number/status).

### Factories
- **`ViabilityDecisionFactory`**: default = deferimento do sistema (`flow 'expresso'`, `outcome Deferida`, `consolidated_result 'permitido'`, `tvl_product_number 'TVL-{ano}-000001'`, jsonb plausíveis, `decided_by_user_id null`, `decided_at now()`, `viability_request_id` = `ViabilityRequest::factory()->protocoled()`). States: `indeferida()` (outcome Indeferida, `nao_permitido`, sem TVL) e `semAtuacaoBap()` (indeferida + `reason 'indeferido sem atuação'`).
- **`TvlSequenceFactory`**: `year` ano atual, `last_number` 0.

### `App\Services\Expresso\TvlNumberGenerator`
- `generate(?int $year = null): string` → `'{prefixo}-{AAAA}-{NNNNNN}'` (ex.: `TVL-2026-000001`).
- `tvl_sequences->lockForUpdate()` dentro de `DB::transaction`; NÃO grava em `viability_decisions` (só sequencia — o caller 09-05 usa o número dentro da transação da decisão).
- Parametrização: `Settings::get('expresso.tvl.prefixo', config('sile.expresso.tvl.prefixo', 'TVL'))` e `expresso.tvl.padding` (default 6).

### `App\Services\Solicitacao\ViabilityRequestStateMachine` — mapa FINAL
```
'rascunho'       => ['protocolada', 'cancelada']
'protocolada'    => ['cancelada', 'em_analise', 'deferida', 'indeferida', 'aguardando_bap']
'aguardando_bap' => ['deferida', 'indeferida', 'em_analise']
```
Decisão (deferida/indeferida) é FINAL (sem saída). `cancelada→*`, `protocolada→rascunho`, `rascunho→em_analise` permanecem INVÁLIDAS.

## Task Commits

1. **Task 1: migrations + ExpressoSchemaTest** — `6a85838` (feat)
2. **Task 2: DecisionOutcome + models/factories + transições da StateMachine** — `30d79c8` (feat)
3. **Task 3: TvlNumberGenerator (SQLite + @group postgis)** — `c2b25ff` (feat)

_Cada peça seguiu RED→GREEN com evidência fresca._

## Decisions Made
- **jsonb** (não `json`) para os campos estruturados, espelhando `viability_requests` da Fase 8 — portável e proven em SQLite.
- **`bap_*` fora do fillable** — escrita controlada via `forceFill` (consistente com `protocol_number`/`status`); o relógio do BAP é dormente até o Regin (Fase 13).
- **`DecisionOutcome` mínimo** (2 casos + `label()`); o `fromResultado` foi deliberadamente NÃO incluído — a consolidação RN-009 e o mapeamento vivem no `FluxoExpressoService` (09-05), evitando API especulativa não exercitada.
- **`ViabilityDecision` sem `HasAuditoria`** — a auditoria da decisão é síncrona no service (HU-078), não um diff automático.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Índice unique 1:1 de `viability_request_id` não era criado**
- **Found during:** Task 2 (teste `test_decisao_e_um_para_um_com_a_solicitacao` — esperava `QueryException`, nenhuma lançada).
- **Issue:** Na migration da Task 1, `->unique()` estava encadeado APÓS `->constrained()`, recaindo sobre a definição da FK e NÃO gerando o índice único da coluna (no SQLite a segunda decisão era aceita).
- **Fix:** Movido `->unique()` para ANTES de `->constrained()` (idioma correto do Laravel — aplica à coluna).
- **Files modified:** `database/migrations/2026_06_14_163233_create_viability_decisions_table.php`
- **Verification:** `ViabilityDecisionTest` verde (a 2ª decisão lança `QueryException`); `ExpressoSchemaTest` segue verde.
- **Committed in:** `30d79c8` (commit da Task 2).

---

**Total deviations:** 1 auto-fixed (1 bug). **Impacto:** correção necessária para a garantia 1:1 (RN-005/append-only). Sem scope creep.

## Issues Encountered
- **Bug de teste próprio (Task 2, StateMachine):** os novos testes em loop criavam múltiplas `protocoled()` com o mesmo `protocol_number` hardcoded no factory state → violação de unique. Corrigido dando número único por iteração (o que a máquina avalia é só o `status`). Resolvido dentro do mesmo ciclo RED→GREEN.
- **Falha transitória na 1ª suíte completa:** 10 erros em `SolicitacaoViabilityResolverTest` (escopo do 09-04, paralelo) por corrida com commits paralelos chegando durante os ~38s da run. Verificado que a classe existe (`class_exists`=true, PSR-4) e NÃO é impacto deste plano; reexecução após o trabalho paralelo aterrissar: **887/887 verde**.

## User Setup Required
None - sem configuração de serviço externo.

## Next Phase Readiness
- **09-05** (FluxoExpressoService): cria a `ViabilityDecision` + número TVL (via `TvlNumberGenerator`) + transição, tudo na transação da decisão; usa `DecisionOutcome` e a consolidação (RN-009) que NÃO está no enum.
- **09-07/08/09**: carregam a `ViabilityDecision` (relação `decision()`).
- **09-10**: usa a transição `aguardando_bap→indeferida` (HU-134 dormente) e grava `bap_*` via forceFill.
- **09-11**: exibe a decisão (outcome/label, consolidated_result, tvl_product_number, fundamentacao).
- ZERO dependência nova; concorrência do TVL provada em Postgres real (`@group postgis`).

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
