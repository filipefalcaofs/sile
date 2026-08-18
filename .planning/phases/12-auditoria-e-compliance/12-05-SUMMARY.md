---
phase: 12-auditoria-e-compliance
plan: 05
subsystem: backend
tags: [explicabilidade, hu-099, decision-trace, projecao-pura, rn-005, auditoria]

# Dependency graph
requires:
  - phase: 12-auditoria-e-compliance (12-02)
    provides: "viability_decisions.decision_trace (jsonb) + DecisionTraceBuilder — shape passo a passo por CNAE que esta projeção consome"
  - phase: 09-fluxo-expresso
    provides: "ResultadoExpressoController::show (detalhe imutável da ViabilityDecision)"
  - phase: 10-analise-tecnica
    provides: "ProcessoController::show (detalhe do processo com decisão/ficha/timeline)"
provides:
  - "DecisionExplanationService::explain(ViabilityDecision): array — projeção PURA do decision_trace (RN-005), legado degrada honesto"
  - "DecisionExplanationResource — shape de apresentação (por_cnae[].passos[] + flag registrado, fundamentacao, rules_versions, legado, desfecho)"
  - "Prop ADITIVA 'explicacao' no show de ResultadoExpresso e Processo, sob consultar-solicitacoes (sem rota/permissão nova)"
affects: [12-07-personal-data-processo-show, 12-10-ui-explicabilidade]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Projeção pura (read model): explain() é só transformação de leitura — sem dependência do motor, garantindo RN-005 por construção"
    - "Shape de passo uniforme idêntico ao trace (registrado/entrada/resultado_parcial/motivo/versao_regra) — a UI 12-10 itera trace e legado genericamente"
    - "Degradação honesta do legado: passos do motor sem snapshot marcados 'não registrado nesta decisão', nunca inventados nem recomputados"
    - "Exposição estritamente ADITIVA: prop nova no show, sem tocar gates, auditoria nem demais props (anti-regressão)"

key-files:
  created:
    - "app/Services/Auditoria/DecisionExplanationService.php"
    - "app/Http/Resources/DecisionExplanationResource.php"
    - "tests/Feature/Auditoria/DecisionExplanationTest.php"
  modified:
    - "app/Http/Controllers/Gestao/ResultadoExpressoController.php"
    - "app/Http/Controllers/Gestao/ProcessoController.php"

key-decisions:
  - "explain() NÃO injeta nada do motor (sem resolver/LOUOS/risco no construtor) — RN-005 garantida por construção, provada por SPY do resolver com 0 chamadas"
  - "Preserva a ordem do trace (o builder 12-02 já grava na ordem canônica) em vez de reordenar — projeção fiel, não inventa ordenação"
  - "Legado projeta entrada + consolidação/desfecho a partir de per_cnae (dado realmente gravado) e marca risco + Quadros LOUOS como 'não registrado'"
  - "Resource é fino e defensivo (array_values nas listas) para o SSR não quebrar com per_cnae associativo legado; é o ponto de shape que a UI 12-10 consome"
  - "ProcessoController::show envia 'explicacao' = null quando não há decisão (anti-fachada) — 12-07 serializa sobre o MESMO método (marcação personal_data)"

patterns-established:
  - "Read model de explicabilidade: a 12-02 grava o trace na decisão; a 12-05 projeta sem recomputar — fonte é o registro, jamais a reexecução"

# Metrics
duration: ~20min
completed: 2026-06-15
---

# Phase 12 Plan 05: Explicabilidade passo a passo (projeção pura) Summary

**`DecisionExplanationService::explain()` projeta o `decision_trace` gravado na 12-02 (+ `rules_versions` + `fundamentacao`) na visualização passo a passo por CNAE na ordem canônica, SEM nunca chamar o motor (RN-005 — SPY do resolver com 0 chamadas); decisão legada (trace null) degrada honesta marcando os passos do motor como "não registrado nesta decisão". A explicação é exposta como prop ADITIVA `explicacao` no `show` de ResultadoExpresso e Processo, sob `consultar-solicitacoes` (sem rota/permissão nova), com 8/8 testes verdes e as suítes ResultadoExpresso/Processo sem regressão.**

## Performance

- **Duration:** ~20 min
- **Tasks:** 2
- **Files:** 5 (3 criados, 2 alterados)

## Accomplishments

- `DecisionExplanationService::explain(ViabilityDecision $decision): array` — projeção PURA: lê `decision_trace`/`per_cnae`/`rules_versions`/`fundamentacao` e devolve a explicação ordenada. Zero dependência do motor (RN-005 por construção).
- `DecisionExplanationResource` — normaliza o shape de apresentação para a UI 12-10, com listas defensivas (SSR-safe).
- Prop ADITIVA `explicacao` no `ResultadoExpressoController::show` (sempre presente — o show já garante decisão via `abort_unless`) e no `ProcessoController::show` (null quando não há decisão — anti-fachada).
- TDD estrito: RED confirmado pelo motivo certo em cada task (classe inexistente → `Property [explicacao] does not exist`), depois GREEN.
- Mudança estritamente aditiva: gates, auditoria e demais props inalterados; suítes ResultadoExpresso/Processo continuam verdes.

## Task Commits

1. **Task 1: DecisionExplanationService (projeção pura) + Resource** — `7f18419` (feat)
2. **Task 2: prop `explicacao` no show de ResultadoExpresso e Processo** — `0aa69ab` (feat)

## Assinatura de `explain()` (contrato para 12-10/12-07)

```php
DecisionExplanationService::explain(ViabilityDecision $decision): array
```

Retorno:

```jsonc
{
  "legado": false,                 // true quando decision_trace é null
  "desfecho": {
    "outcome": "deferida",
    "outcome_label": "Deferida",
    "consolidated_result": "permitido",
    "consolidated_result_label": "Permitido"
  },
  "por_cnae": [ /* um bloco por CNAE, na ordem registrada */ ],
  "fundamentacao": ["Lei nº 9.148/2016 (LOUOS) — Quadro 7", "..."],
  "rules_versions": { "louos": { "quadro7": "...", "quadro10": "..." }, "risco": {}, "territorio": {} }
}
```

Bloco por CNAE:

```jsonc
{
  "cnae": "8888881",
  "cnae_formatado": "8888-8/81",
  "is_primary": true,
  "origem": "motor",               // "motor" | "analista" | null (legado)
  "passos": [ /* shape uniforme abaixo, na ordem canônica */ ]
}
```

### Shape de passo (idêntico ao trace 12-02 — itera genericamente)

```jsonc
{
  "passo": "louos.quadro10",
  "titulo": "LOUOS — Quadro 10 (permissão na zona)",
  "registrado": true,              // false = passo do motor sem snapshot ("não registrado nesta decisão")
  "entrada": { /* ... */ },
  "resultado_parcial": { /* ... */ },
  "motivo": null,
  "versao_regra": "lei-9148-2016-quadro10"  // null quando não registrado
}
```

`consolidacao` e `decisao_humana` acrescentam `fundamentacao: string[]`.

### Ordem canônica projetada

`entrada → risco → louos.quadro7 → louos.quadro10 → louos.quadro11 → louos.quadro11a → consolidacao → desfecho` (+ `decisao_humana` quando `origem=analista`). A 12-05 **preserva** a ordem que o `DecisionTraceBuilder` (12-02) gravou — não reordena nem recomputa.

### Caso LEGADO (`decision_trace` null)

`legado=true`. `por_cnae` é montado de `per_cnae`: `entrada` registrada (cnae/cnae_formatado/fluxo), `consolidacao`/`desfecho` registrados quando há `tendencia` no item (resultado/label/fundamentação), e `risco` + `louos.quadro7/10/11/11a` marcados `registrado=false`, `motivo="não registrado nesta decisão"`, `versao_regra=null`. Nada inventado, nada reexecutado.

## DecisionExplanationResource (shape de apresentação)

Envolve o array de `explain()` (nunca a decisão crua) e o resolve para a prop Inertia, garantindo `array_values` em todas as listas (SSR-safe). Saída: `{ legado, desfecho, por_cnae[].passos[], fundamentacao, rules_versions }`. A flag `registrado` por passo é o estado-chave a renderizar (passo registrado vs. "não registrado nesta decisão").

## Props `explicacao` adicionadas

- **`ResultadoExpressoController::show`** → `Inertia::render('gestao/resultados-expresso/show', [... , 'explicacao' => (new DecisionExplanationResource($this->explanations->explain($decision)))->resolve()])`. Sempre presente (o show aborta 404 sem decisão). Service injetado no construtor (2º parâmetro).
- **`ProcessoController::show`** → prop `explicacao` inserida **entre `processo` e `timeline`**, com `null` quando `$viabilityRequest->decision` é null. Service injetado no construtor (3º parâmetro). Gate `consultar-solicitacoes`, auditoria `consulta-processo` e demais props (`timeline`, `geo`) inalterados.

## Files Created/Modified

- `app/Services/Auditoria/DecisionExplanationService.php` — projeção pura + legado honesto (sem dependência do motor).
- `app/Http/Resources/DecisionExplanationResource.php` — shape de apresentação SSR-safe.
- `app/Http/Controllers/Gestao/ResultadoExpressoController.php` — service injetado + prop `explicacao` no show.
- `app/Http/Controllers/Gestao/ProcessoController.php` — service injetado + prop `explicacao` (null sem decisão) no show.
- `tests/Feature/Auditoria/DecisionExplanationTest.php` — 8 testes (projeção/ordem/versao/motivo, SPY=0 RN-005, legado honesto, resource shape, 2× show ResultadoExpresso, 2× show Processo).

## Deviations from Plan

Nenhum. Plano executado exatamente como escrito: apenas os 5 arquivos de `files_modified`, sem dependência nova, sem arquivos de apoio extras.

## Issues Encountered

- **`pint --dirty` tocaria arquivos dos irmãos paralelos (12-04/12-06):** o `--dirty` formata todo o working tree, que contém arquivos não-commitados de 12-04 (`AuditTrailQueryService`, `ActivityResource`, `AuditoriaConsultaTest`) e 12-06 (`AbusoDetectarCommand`, `routes/console.php`). Resolvido escopando o Pint exclusivamente aos MEUS 5 arquivos e fazendo `git add` por-arquivo (nunca `-A`/`.`).
- **Suíte completa não executada isoladamente:** durante o paralelismo, a descoberta automática do PHPUnit incluiria arquivos de teste ainda em andamento dos irmãos (ex.: `AuditoriaConsultaTest` do 12-04), poluindo o resultado. Validei meu escopo via filtro do plano + fundação 12-02 + pontos de exposição. Este plano não toca `routes/gestao.php`, `config/sile.php`, `AppServiceProvider` nem `routes/console.php` (sem conflito com 12-04/12-06); o service autowira sem binding.

## Verificação (evidência fresca)

- `vendor/bin/pint` (meus 5 arquivos): **passed** (sem pendências).
- `php artisan test --compact --filter="DecisionExplanationTest|ResultadoExpresso|Processo"`: **90 passed, 504 assertions** (inclui anti-regressão ResultadoExpresso/Processo).
- `php artisan test --compact --filter="DecisionExplanationTest"`: **8 passed, 100 assertions** — inclui o SPY do `SolicitacaoViabilityResolver` com **0 chamadas** (RN-005).
- `php artisan test --compact --filter="DecisionTraceEnrichmentTest"` (fundação 12-02): **4 passed, 56 assertions** (trace intacto).
- Lints (5 arquivos): **0 erros**.

## Next Phase Readiness

- **12-07 (personal_data no ProcessoController::show):** depende deste plano. A prop `explicacao` já está no `show`, inserida entre `processo` e `timeline`; o service é o 3º parâmetro do construtor. A 12-07 só precisa acrescentar a marcação `personal_data` no `$this->audit->log(...)` do mesmo método — mudança ortogonal à prop.
- **12-10 (UI de explicabilidade):** consome a prop `explicacao` (shape acima). Estados a renderizar honestamente: `legado=true`, `registrado=false` (passo "não registrado nesta decisão") e `versao_regra=null`. O componente itera `por_cnae[].passos[]` genericamente (trace e legado têm o mesmo shape). Integração na trilha (12-04) é por link ao detalhe — sem acoplamento ao AuditoriaController.
- Sem blockers. Sem configuração externa.

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*
