---
phase: 15-relatorios-e-indicadores
plan: 07
subsystem: relatorios-quedas
tags: [hu-145, hu-131, expresso, queda, gatilho, tipo-gatilho, taxa-resposta, rn-001, rn-004, anti-regressao]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-01)
    provides: parâmetros relatorios.expresso.meta_taxa/janela_dias + índices de relatório
  - phase: 15-relatorios-e-indicadores (15-02)
    provides: contrato de exportação (ReportSource/ReportDefinition + ReportFilters)
  - phase: 09-fluxo-expresso
    provides: FluxoExpressoService::encaminharAnalise (ponto da captura aditiva) + ViabilityDecision/ViabilityRequestTransition
  - phase: 06-classificacao-de-risco
    provides: RiscoClassificationService (shape do encaminhamento) + TipoGatilho
  - phase: 10-analise-tecnica
    provides: ProcessoQueryService::filtered (drill-down) + AnalysisDivergence
provides:
  - "Model/migration/factory ExpressoQueda — registro estruturado da queda por CNAE (HU-145)"
  - "Captura ADITIVA em FluxoExpressoService::encaminharAnalise (tipo_gatilho/dimensao/motivo reais; gatilho NULL honesto quando degradado)"
  - "ExpressoQuedaService — taxa de resposta expressa + série temporal + ranking de motivos + drill-down"
  - "ExpressoQuedaReportSource — exportação das quedas filtradas (HU-131, personalData=false)"
affects: [15-06-http-telas, 15-09-dashboard-download, 14-ia-melhoria-continua]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Captura de domínio ADITIVA na MESMA transação do encaminhamento (não altera decisão/transição/auditoria — anti-regressão Fases 9/10)"
    - "Anti-fachada estrutural: tipo_gatilho/cnae NULL quando o motor degradou ($resolved null) ou não há gatilho de contexto — gatilho NUNCA inventado (RN-001)"
    - "Serviço de relatório route-free com agregação SQL (date() portável), espelhando IndicadoresViabilidadeService"
    - "Taxa com dois denominadores honestos: respondidas (viability_decisions flow=expresso/decided_by_user_id null) ÷ elegíveis (transições protocolada→{deferida|indeferida|em_analise})"

key-files:
  created:
    - database/migrations/2026_06_15_000004_create_expresso_quedas_table.php
    - app/Models/ExpressoQueda.php
    - database/factories/ExpressoQuedaFactory.php
    - app/Services/Relatorios/ExpressoQuedaService.php
    - app/Services/Relatorios/Export/Sources/ExpressoQuedaReportSource.php
    - tests/Feature/Relatorios/ExpressoQuedaCapturaTest.php
    - tests/Feature/Relatorios/ExpressoQuedaServiceTest.php
  modified:
    - app/Services/Expresso/FluxoExpressoService.php

key-decisions:
  - "Captura via método privado capturarQueda() chamado DENTRO da DB::transaction de encaminharAnalise, APÓS o audit->log — aditivo, mesma atomicidade, sem tocar a decisão/transição/SLA"
  - "Chaves lidas de consulta_array['risco']['encaminhamento']: gatilhos_acionados[0]['codigo'] (TipoGatilho) → tipo_gatilho; dimensao_decisiva → dimensao; motivo → motivo (fallback $reason)"
  - "Teste do gatilho usa um stub do SolicitacaoViabilityResolver: o wiring do gatilhosContexto chega só no EP07 (RiscoInput::paraCnae envia vazio hoje), então a extração do gatilho é exercitada com o shape REAL do RiscoResult — caminho honesto, sem fingir wiring inexistente"
  - "Elegíveis = transições protocolada→{deferida|indeferida|em_analise}: protocolada→deferida/indeferida só ocorre na auto-decisão expressa (emitir), protocolada→em_analise é a queda — denominador coerente com respondidas no mesmo eixo temporal"
  - "Série temporal por dia coerente com o headline (respondidas÷elegíveis do dia), janela = período do filtro OU relatorios.expresso.janela_dias (parametrizável)"
  - "meta_taxa é string no catálogo → cast para float; null/'' = meta não definida (RN-004), NUNCA inventada"
  - "drillDown reusa ProcessoQueryService::filtered (filtro SAPS completo) ∩ processos com queda (whereIn subquery em expresso_quedas) — entrada para as divergências analista×motor (AnalysisDivergence)"
  - "ExpressoQuedaFactory default usa protocolada SEM protocol_number fixo (nullable) para permitir count(N) sem colidir no único do protocolo"
  - "Migration 000004 validada ISOLADA (up/down) em pgsql via --path; migrate global NÃO rodado por causa das migrations pendentes da Fase 14 do usuário"

# Metrics
duration: ~20 min
completed: 2026-06-15
---

# Phase 15 Plan 07: Captura estruturada da queda do expresso (HU-145) Summary

**A queda ao analista vira DADO REAL e estruturado no momento em que ocorre: `ExpressoQueda` (cnae/tipo_gatilho/dimensao/motivo) gravada ADITIVAMENTE na transação de `encaminharAnalise` (Fases 9/10 intactas), com `tipo_gatilho` NULL honesto quando o motor degradou — e o `ExpressoQuedaService` mede sobre ela: taxa de resposta expressa (respondidas÷elegíveis), série temporal, ranking de motivos e drill-down, com meta parametrizável (RN-004) e degradação honesta em toda agregação.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-06-15T20:25:19Z
- **Completed:** 2026-06-15T20:44:40Z
- **Tasks:** 2 (cada uma com commit atômico)
- **Files created:** 7 (migration/model/factory + serviço + report source + 2 testes) + 1 modificado (FluxoExpressoService)

## Accomplishments

- **Captura estruturada (HU-145):** `ExpressoQueda` por CNAE encaminhado à análise, gravada na MESMA transação do encaminhamento — fecha o pré-requisito anti-fachada que faltava (antes só 3 categorias grossas iam para `transitions.reason`).
- **Anti-fachada (RN-001) honesta:** motor degradado (`$resolved === null`, toggle off) grava 1 linha de nível-processo com `tipo_gatilho`/`cnae`/`dimensao` NULL; CNAE caído sem gatilho de contexto grava `tipo_gatilho` NULL com o `cnae` — gatilho NUNCA inventado.
- **Anti-regressão Fases 9/10:** captura 100% aditiva — decisão, transição, SLA, auditoria e roteamento inalterados (`--filter=Expresso` 123/123, `--filter=FluxoExpresso` 20/20).
- **Relatório de quedas (HU-145):** `ExpressoQuedaService` (taxa, série temporal, ranking de motivos, drill-down) + `ExpressoQuedaReportSource` (export HU-131) — tudo por agregação SQL real, degradação honesta.

## Task Commits

1. **Task 1: tabela/model ExpressoQueda + captura ADITIVA em encaminharAnalise** — `5a35e88` (feat)
2. **Task 2: ExpressoQuedaService + ExpressoQuedaReportSource** — `5de0208` (feat)

_Plano executado em ciclo RED→GREEN por task; a captura é aditiva (sem refactor da decisão)._

## Onde/como a captura foi inserida (output do plano)

- **Local exato:** `FluxoExpressoService::encaminharAnalise`, DENTRO da `DB::transaction` existente, APÓS `$this->audit->log(...)` — nova chamada `$this->capturarQueda($request, $reason, $resolved)` no fecho da closure (mesma atomicidade da transição/auditoria).
- **Chaves lidas de `encaminhamento` (confirmadas no `RiscoClassificationService::resolveEncaminhamento` → `RiscoResult::toArray()['encaminhamento']`):** o array tem `fluxo`, `dimensao_decisiva`, `motivo`, `gatilhos_acionados[]` (cada item `{codigo, motivo}`). A captura lê de `$item['consulta_array']['risco']['encaminhamento']`:
  - `tipo_gatilho` ← `gatilhos_acionados[0]['codigo']` (valor de `TipoGatilho`) `?? null`
  - `dimensao` ← `dimensao_decisiva` `?? null`
  - `motivo` ← `motivo` `?? $reason`
  - `cnae` ← `$item['cnae']`
- **Filtro por CNAE caído:** só itera `por_cnae` cujo `$item['fluxo'] === Fluxo::Analise->value` (os que efetivamente caíram).
- **Degradado:** `$resolved === null` ⇒ 1 linha `viability_request_id` + `motivo = $reason`, com `cnae`/`tipo_gatilho`/`dimensao` NULL.

## Shape da tabela `expresso_quedas`

| Coluna | Tipo | Nota |
|---|---|---|
| `id` | bigint PK | |
| `viability_request_id` | FK → viability_requests | `constrained()->cascadeOnDelete()` |
| `cnae` | string nullable | NULL na linha de nível-processo (motor degradado) |
| `tipo_gatilho` | string nullable | valor de `TipoGatilho`; NULL sem gatilho/degradado (RN-001) |
| `dimensao` | string nullable | dimensão decisiva do risco (municipal/sanitario) |
| `motivo` | text | motivo estruturado do roteamento (não texto livre do usuário) |
| `timestamps` | | |

Índice composto `(tipo_gatilho, created_at)` para o ranking de motivos e a série temporal.

## Fórmulas do relatório (output do plano)

- **Taxa de resposta expressa (RN-002):** `respondidas ÷ elegíveis × 100` (1 casa), ou `null` sem base no período.
  - `respondidas` = `count(viability_decisions WHERE flow='expresso' AND decided_by_user_id IS NULL AND decided_at ∈ período)`
  - `elegíveis` = `count(viability_request_transitions WHERE from_status='protocolada' AND to_status ∈ {deferida, indeferida, em_analise} AND created_at ∈ período)`
  - `meta` = `Settings::get('relatorios.expresso.meta_taxa')` (cast float; `null` quando não definida — RN-004, nunca inventada)
- **Série temporal:** por dia (dentro da janela = período do filtro OU `relatorios.expresso.janela_dias`), `respondidas(dia) ÷ elegíveis(dia)`; coerente porque a auto-decisão expressa cria a transição protocolada→deferida/indeferida e a queda cria protocolada→em_analise no mesmo instante.
- **Ranking de motivos:** `ExpressoQueda` `groupBy('tipo_gatilho')` `count(*)` desc; `null` rotulado `'não classificado'`.
- **Drill-down:** `ProcessoQueryService::filtered($f->toProcessoFiltros())` ∩ `whereIn('id', expresso_quedas.viability_request_id)` — base para as divergências analista×motor (`AnalysisDivergence`).

## Files Created/Modified

- `database/migrations/2026_06_15_000004_create_expresso_quedas_table.php` — tabela da queda + índice (tipo_gatilho, created_at)
- `app/Models/ExpressoQueda.php` — model (`#[Fillable]`, relação `viabilityRequest`)
- `database/factories/ExpressoQuedaFactory.php` — default (gatilho ZEIS) + states `nivelProcesso()`/`semGatilho()`; protocolada sem protocol_number fixo
- `app/Services/Expresso/FluxoExpressoService.php` — **+** `capturarQueda()` chamado em `encaminharAnalise` (ADITIVO)
- `app/Services/Relatorios/ExpressoQuedaService.php` — taxa/série/ranking/drill-down (route-free, SQL)
- `app/Services/Relatorios/Export/Sources/ExpressoQuedaReportSource.php` — export HU-131 (personalData=false)
- `tests/Feature/Relatorios/ExpressoQuedaCapturaTest.php` — captura (gatilho real, degradado, real inelegível + anti-regressão)
- `tests/Feature/Relatorios/ExpressoQuedaServiceTest.php` — taxa 75.0/null, meta parametrizável, ranking, série, drill-down, report source

## Decisions Made

- **Captura aditiva na transação:** `capturarQueda()` privado, chamado após `audit->log` dentro da `DB::transaction` — preserva a atomicidade e NÃO altera a lógica de decisão das Fases 9/10.
- **Stub do resolver para o gatilho:** o `gatilhosContexto` só é populado no EP07 (hoje `RiscoInput::paraCnae` envia `[]`), então a queda por gatilho real não é alcançável via `decide()` end-to-end. O teste do gatilho injeta um `SolicitacaoViabilityResolver` stub (dependência interna, prática permitida) com o shape REAL do `RiscoResult`, exercitando a EXTRAÇÃO do gatilho sem fingir wiring inexistente. Os outros dois testes usam o caminho REAL (toggle off; CNAE alto risco real).
- **`EncaminhadoParaAnalise` faketado só no teste do gatilho:** a pré-análise (10-08) re-resolve o resolver (stub sem o objeto `consulta`); a captura ocorre ANTES do dispatch, então faketar o evento isola o alvo sem afetar a captura.
- **Meta como string → float:** `relatorios.expresso.meta_taxa` é `string` no catálogo (default null); o serviço faz cast e devolve null quando vazia.
- **Série coerente com o headline:** mesmo numerador/denominador por dia, em vez de uma "taxa por dia" de denominador diferente (evita inconsistência entre KPI e série).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Stub do resolver + fake de evento no teste do gatilho**
- **Found during:** Task 1 (RED do gatilho)
- **Issue:** A queda por gatilho real NÃO é alcançável via `decide()` (o `gatilhosContexto` só é ligado no EP07; hoje `RiscoInput::paraCnae` o envia vazio). Além disso, o listener de `EncaminhadoParaAnalise` (PreAnaliseService) lê `$item['consulta']` (objeto ausente no shape cravado).
- **Fix:** Stub do `SolicitacaoViabilityResolver` (devolve `ResolvedViability` com o shape REAL do `RiscoResult`) + `Event::fake([EncaminhadoParaAnalise::class])` no teste do gatilho. Os testes de queda degradada e de queda real (CNAE alto risco) usam o caminho REAL sem stub/fake.
- **Files modified:** tests/Feature/Relatorios/ExpressoQuedaCapturaTest.php
- **Verification:** `--filter=ExpressoQuedaCapturaTest` 3/3 (a captura roda dentro da transação, antes do evento).
- **Committed in:** 5a35e88

**2. [Rule 1 - Robustez] ExpressoQuedaFactory default sem protocol_number fixo**
- **Found during:** Task 2 (ranking com várias quedas)
- **Issue:** O state `protocoled()` fixa o mesmo `protocol_number` (único) → `count(N)` colidiria.
- **Fix:** Default usa `ViabilityRequest::factory()->state(['status' => Protocolada, 'protocoled_at' => now()])` (protocol_number nullable); testes passam `viability_request_id` explícito.
- **Files modified:** database/factories/ExpressoQuedaFactory.php
- **Verification:** `--filter=ExpressoQuedaServiceTest` 7/7 (ranking cria 6 quedas sem colidir).
- **Committed in:** 5de0208

### Coordenação com trabalho paralelo (sem mudança de escopo)

**3. [Coordenação] Migration isolada + staging seletivo + pint escopado**
- O working tree tem trabalho paralelo NÃO commitado do usuário (Fase 14 IA/e-mail; sidebar; vitest) e de outras waves da Fase 15. Migration `create_expresso_quedas_table` validada up/down ISOLADA em pgsql via `--path` (migrate global NÃO rodado). Cada commit recebeu APENAS os arquivos do 15-07 (`git add` por caminho); pint rodado com caminhos explícitos (não `--dirty`). Nenhum arquivo de outra frente foi tocado/staged/commitado.

**Total deviations:** 2 auto-corrigidas + 1 coordenação. **Impacto no plano:** nenhum (escopo intacto; cobertura honesta da extração de gatilho e preservação do trabalho paralelo).

## Verification (evidência fresca)

- `php artisan test --compact --filter=ExpressoQuedaCapturaTest` → **3/3** (25 asserções): gatilho real (`tipo_gatilho='zeis_especial'`), motor degradado (`tipo_gatilho` NULL, linha de processo), queda real inelegível (CNAE alto → `tipo_gatilho` NULL honesto + anti-regressão: em_analise/transição/auditoria preservadas).
- `php artisan test --compact --filter=ExpressoQuedaServiceTest` → **7/7** (24 asserções): taxa 75.0 (6÷8), taxa null sem base, meta null/80.0 parametrizável, ranking por gatilho com null→'não classificado', série temporal por dia, drill-down recortado, report source filtrado.
- **ANTI-REGRESSÃO Fases 9/10:** `--filter=FluxoExpresso` → **20/20**; `--filter=Expresso` → **123/123** (558 asserções) — captura 100% aditiva.
- Suíte de relatórios completa junto: `--filter=Relatorios` → **63/63** (263 asserções).
- Migration `2026_06_15_000004_create_expresso_quedas_table` up/down DONE ISOLADA em pgsql (`--path`).
- `vendor/bin/pint` nos meus arquivos → `passed`.

## Issues Encountered

- **Trabalho paralelo concorrente:** Fase 14 (IA/e-mail) e outras waves da Fase 15 com arquivos NÃO commitados no working tree. Resolvido com staging seletivo, migration isolada e pint escopado (ver Deviation 3). Nenhuma colisão de arquivo. A suíte completa (`composer test`) NÃO foi rodada por incluir testes/migrations paralelos possivelmente incompletos do usuário; a evidência do 15-07 é por filtros (RefreshDatabase em SQLite + migration isolada em pgsql).

## Next Phase Readiness

- A captura honesta está em produção: HU-145 mede sobre dado real e estruturado.
- **15-06 (HTTP/telas):** o `ExpressoQuedaService` e o `ExpressoQuedaReportSource` estão prontos para o controller/tela do relatório de quedas (taxa + série + ranking + drill-down + export).
- **EP07 (wiring do gatilho):** quando `gatilhosContexto` for ligado, a captura já extrai `tipo_gatilho` real automaticamente (sem mudança de código).
- **Pendência SEDUR/DPO (registrada, sem fachada):** meta da taxa expressa (RN-004 — hoje "não definida"); o detalhe de divergência analista×motor no drill-down se aprofunda quando a ficha de análise (AnalysisDivergence) tiver volume real.

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-15*
