---
phase: 12-auditoria-e-compliance
plan: 02
subsystem: database
tags: [decision-trace, explicabilidade, hu-099, viability-decisions, auditoria, jsonb]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    provides: "FluxoExpressoService::emitir + ResolvedViability (por_cnae[].consulta_array) — fonte do trace na decisão automática"
  - phase: 10-analise-tecnica
    provides: "AnaliseTecnicaDecisionService + AnalysisRecord (engine_snapshot/per_cnae) — fonte do trace na decisão humana"
  - phase: 07-consulta-viabilidade
    provides: "ConsultaViabilidadeResult::toArray (consulta_array: entrada/enquadramento/risco/veredito_locacional/versoes)"
provides:
  - "Coluna ADITIVA viability_decisions.decision_trace (jsonb nullable, imutável por convenção)"
  - "DecisionTraceBuilder: montador puro do trace passo a passo por CNAE (fonte única do shape)"
  - "decision_trace gravado nos DOIS pontos de escrita (expresso + análise técnica), sem recomputo"
  - "Contrato do caso legado: decision_trace null = decisão honesta a degradar na projeção"
affects: [12-05-explicabilidade, 12-09-ui-explicabilidade, decision-explanation-service]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Forward snapshot: gravar o trace JÁ em memória no momento da decisão (RN-005), nunca recomputar na leitura"
    - "Builder puro compartilhado (DecisionTraceBuilder) como fonte única do shape entre os dois pontos de escrita"
    - "Enriquecimento estritamente ADITIVO: só acrescenta coluna/snapshot, sem tocar a lógica de decisão (anti-regressão)"

key-files:
  created:
    - "database/migrations/2026_06_15_081450_add_decision_trace_to_viability_decisions.php"
    - "app/Services/Auditoria/DecisionTraceBuilder.php"
    - "tests/Feature/Auditoria/DecisionTraceEnrichmentTest.php"
  modified:
    - "app/Models/ViabilityDecision.php"
    - "app/Services/Expresso/FluxoExpressoService.php"
    - "app/Services/Analise/AnaliseTecnicaDecisionService.php"

key-decisions:
  - "decision_trace é uma LISTA (um item por CNAE, na ordem da decisão), cada item com {cnae, cnae_formatado, is_primary, origem, passos[]}"
  - "Passo com shape uniforme {passo, titulo, registrado, entrada, resultado_parcial, motivo, versao_regra} (+ fundamentacao em consolidacao e decisao_humana)"
  - "DecisionTraceBuilder (app/Services/Auditoria) extraído como fonte única do shape — garante que expresso e análise técnica produzem o MESMO contrato que a 12-05 projeta"
  - "origem ∈ {motor, analista}; passo.registrado=false marca o passo do motor ausente na decisão humana sem snapshot (FA-01) — honesto, nunca inventado"

patterns-established:
  - "Forward snapshot da explicabilidade: o trace nasce na escrita da decisão, a leitura (12-05) é projeção pura"
  - "Ordem canônica dos passos: entrada → risco → louos.quadro7 → quadro10 → quadro11 → quadro11a → consolidacao → desfecho (+ decisao_humana)"

# Metrics
duration: ~22min
completed: 2026-06-15
---

# Phase 12 Plan 02: Fundação da explicabilidade (decision_trace forward) Summary

**Coluna aditiva `viability_decisions.decision_trace` (jsonb nullable, imutável) gravada nos dois pontos de escrita da decisão (fluxo expresso e análise técnica humana) a partir do snapshot JÁ em memória, via `DecisionTraceBuilder` puro — sem recomputo (RN-005), sem mudar veredito, com as suítes 9/10 verdes.**

## Performance

- **Duration:** ~22 min
- **Started:** 2026-06-15T05:08:00-03:00
- **Completed:** 2026-06-15T05:28:00-03:00
- **Tasks:** 3
- **Files modified:** 6 (3 criados, 3 alterados)

## Accomplishments
- Migration ADITIVA `decision_trace` (jsonb nullable, default null) — sem alterar nenhuma coluna existente; `down()` dropa só a coluna.
- `DecisionTraceBuilder` puro e determinístico: monta o trace passo a passo por CNAE a partir do `consulta_array`/ficha, sem consultar banco nem chamar motor.
- `FluxoExpressoService::emitir` grava `decision_trace` do `$resolved->por_cnae[].consulta_array` (origem `motor`).
- `AnaliseTecnicaDecisionService::registrarDecisao` grava `decision_trace` da ficha (origem `analista`): reusa os passos do `engine_snapshot` e acrescenta a decisão do analista; caso pendente sem motor marca os passos do motor como "não registrado".
- Anti-regressão 9/10 VERDE (SQLite + `@group postgis`); decisão legada degrada honesta (`decision_trace` null).

## Task Commits

1. **Task 1: Migration decision_trace + cast/Fillable** — `c4adc04` (feat)
2. **Task 2: Enriquecimento no FluxoExpressoService + DecisionTraceBuilder** — `ab6c426` (feat)
3. **Task 3: Enriquecimento na AnaliseTecnicaDecisionService + caso legado** — `15f55c0` (feat)

_Cada task seguiu TDD (RED confirmado por "Failed asserting that null is of type array" antes do GREEN)._

## Shape do `decision_trace` (contrato para 12-05 / 12-09)

`decision_trace` é uma **lista** com **um item por CNAE**, na ordem da decisão. Cada item:

```jsonc
{
  "cnae": "8888881",
  "cnae_formatado": "8888-8/81",
  "is_primary": true,
  "origem": "motor",            // "motor" (expresso/pré-análise) | "analista" (decisão humana)
  "passos": [ /* passos na ordem canônica */ ]
}
```

### Passo — shape uniforme

Todo passo tem o mesmo contrato (a projeção 12-05 pode iterar genericamente):

```jsonc
{
  "passo": "louos.quadro10",     // id estável (ver lista abaixo)
  "titulo": "LOUOS — Quadro 10 (permissão na zona)",
  "registrado": true,            // false = passo do motor não snapshotado nesta decisão ("não registrado")
  "entrada": { /* o que entrou no passo */ },
  "resultado_parcial": { /* o que o motor produziu, sem motivo/versao_regra */ },
  "motivo": null,                // motivo do passo (ex.: quadro indisponível) — como o motor gravou
  "versao_regra": "lei-9148-2016-quadro10"  // versão da regra aplicada (RN-005); null quando inexistente
}
```

`consolidacao` e `decisao_humana` acrescentam o campo `fundamentacao: string[]`.

### Ordem canônica dos passos (`passo`)

| `passo` | Origem do dado | `resultado_parcial` | `motivo` / `versao_regra` |
|---|---|---|---|
| `entrada` | consulta_array.entrada + meta.ponto | `null` | `null` / `null` |
| `risco` | consulta_array.risco | `{municipal, sanitario, encaminhamento}` | `encaminhamento.motivo` / versão da dimensão decisiva |
| `louos.quadro7` | enquadramento.quadro7 | quadro sem motivo/versao_regra (`{status, grupo, subgrupo}`) | `quadro.motivo` / `quadro.versao_regra` |
| `louos.quadro10` | enquadramento.quadro10 | `{status, permissao, condicionante_ref}` | idem |
| `louos.quadro11` | enquadramento.quadro11 | `{status, condicoes}` | idem |
| `louos.quadro11a` | enquadramento.quadro11a | `{status, condicoes}` | idem |
| `consolidacao` | veredito_locacional + fundamentacao | `{resultado, label}` | `veredito.motivo` / `null` |
| `desfecho` | veredito_locacional + risco.encaminhamento | `{tendencia, tendencia_label, fluxo}` | `null` / `null` |
| `decisao_humana` (só `origem=analista`) | ficha per_cnae | `{status_escolhido}` | `null`; `entrada.status_sugerido` + `fundamentacao[]` |

### Como cada ponto de escrita monta

- **Expresso (`origem=motor`):** `DecisionTraceBuilder::cnaeExpresso(consulta_array, meta)` por CNAE de `$resolved->por_cnae` (meta = cnae/cnae_formatado/is_primary + `$resolved->ponto`). Passos `entrada…desfecho`.
- **Análise técnica (`origem=analista`):** `DecisionTraceBuilder::cnaeAnaliseTecnica(fichaItem, consultaArray|null, meta)` por CNAE da ficha (`per_cnae`). O `consultaArray` vem indexado de `engine_snapshot.por_cnae[].consulta`:
  - **com snapshot:** passos `entrada…desfecho` (do motor) **+** `decisao_humana`.
  - **sem snapshot (FA-01/pendente):** `entrada` + passos do motor com `registrado=false` e `motivo="não registrado nesta decisão"` **+** `decisao_humana`.

### Caso legado (decisão anterior a esta fase)

`decision_trace` fica `null`. A coluna é nullable e a decisão segue válida; a degradação honesta ("não registrado nesta decisão") é responsabilidade da **projeção 12-05** — este plano nunca inventa nem reexecuta o motor.

## Files Created/Modified
- `database/migrations/..._add_decision_trace_to_viability_decisions.php` — coluna aditiva jsonb nullable.
- `app/Models/ViabilityDecision.php` — `decision_trace` no `#[Fillable]` e cast `'array'`.
- `app/Services/Auditoria/DecisionTraceBuilder.php` — montador puro do trace (fonte única do shape).
- `app/Services/Expresso/FluxoExpressoService.php` — `decision_trace` no `create` (método privado `decisionTrace`, builder injetado).
- `app/Services/Analise/AnaliseTecnicaDecisionService.php` — `decision_trace` no `create` (privados `decisionTrace`/`consultaPorCnaeDoSnapshot`, builder injetado).
- `tests/Feature/Auditoria/DecisionTraceEnrichmentTest.php` — caso expresso (ordem + versao_regra/motivo + resolver 1×), análise com motor, análise pendente sem motor, legado null.

## Decisions Made
- **Builder compartilhado em vez de método duplicado:** o `DecisionTraceBuilder` (puro, sem dependências) é a fonte única do shape, garantindo que os dois pontos de escrita produzam o MESMO contrato que a 12-05 projeta. Os serviços mantêm o método privado `decisionTrace(...)` do plano, delegando ao builder (injeção no construtor — container resolve sem binding).
- **Shape uniforme de passo** ({passo, titulo, registrado, entrada, resultado_parcial, motivo, versao_regra}) para a projeção iterar genericamente; `versao_regra`/`motivo` lidos de cada quadro tal como o motor gravou (RN-005), nunca inventados.
- **`registrado` (bool)** distingue passo snapshotado de passo ausente, materializando a degradação honesta dentro do próprio trace no caso humano sem motor.

## Deviations from Plan

### Itens adicionados além do `files_modified` declarado

**1. [Discrição do shape — CONTEXT.md] Criado `app/Services/Auditoria/DecisionTraceBuilder.php`**
- **Onde:** Task 2 (reusado na Task 3).
- **Por quê:** O `files_modified` previa só os 2 serviços com método privado `decisionTrace`. Para garantir o requisito central — expresso e análise técnica gravarem o MESMO shape que a 12-05 projeta — extraí um builder puro como fonte única, evitando drift entre os dois pontos de escrita. Os métodos privados `decisionTrace` continuam existindo (conforme o plano), delegando ao builder. A escolha do shape do decision_trace é explicitamente "Claude's Discretion" no CONTEXT.md.
- **Escopo:** Arquivo novo em diretório novo (`app/Services/Auditoria/`), sem colisão com 12-01 (AuditService/activity_log) nem 12-03 (ParameterSeeder/abuse_alerts). 12-05 (DecisionExplanationService, wave posterior) grupará no mesmo domínio.

---

**Total de desvios:** 1 (arquivo de apoio adicional, dentro da discrição de shape). Nenhuma mudança na lógica de decisão; zero dependência nova.
**Impacto no plano:** Reforça o objetivo (shape consistente para 12-05) sem ampliar escopo funcional.

## Issues Encountered
- **`pint --dirty` tocou `app/Models/AbuseAlert.php` (arquivo do 12-03 paralelo):** o `--dirty` formata tudo que está sujo no working tree, inclusive arquivos dos agentes paralelos. Resolvido escopando o Pint aos MEUS arquivos nas execuções seguintes e **nunca** fazendo `git add` desse arquivo (commits por-arquivo). A alteração foi puramente de formatação (idempotente), sem efeito sobre o 12-03.

## Verificação (evidência fresca)
- `php artisan migrate --no-interaction`: coluna aplicada (`Schema::hasColumn` → COLUNA_OK).
- `php artisan test --compact --filter="DecisionTraceEnrichmentTest"`: **4 passed, 56 assertions**.
- Filtro do plano `--filter="DecisionTraceEnrichmentTest|Expresso|AnaliseTecnica"`: **122 passed, 589 assertions** (inclui `@group postgis` do Expresso).
- Anti-regressão final (pós todas as mudanças):
  - SQLite `tests/Feature/Expresso/ tests/Feature/Analise/`: **290 passed, 1283 assertions**.
  - `@group postgis` `tests/Feature/Expresso/ tests/Feature/Analise/`: **7 passed, 44 assertions**.
- `vendor/bin/pint` (meus arquivos): passed.

## Next Phase Readiness
- **12-05 (DecisionExplanationService):** o shape está acima; a projeção é PURA (lê `decision_trace` + `rules_versions` + `fundamentacao`), nunca chama o motor. Decisão legada (`decision_trace` null) → projeção compacta marcando os passos como "não registrado nesta decisão".
- **12-09 (UI passo a passo):** consome o mesmo shape; `registrado=false` e `versao_regra=null` são os estados a renderizar honestamente.
- Sem blockers. Sem configuração externa necessária.

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*
