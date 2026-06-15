---
phase: 15-relatorios-e-indicadores
plan: 03
subsystem: relatorios-indicadores
tags: [hu-123, hu-124, hu-125, hu-126, hu-127, hu-128, hu-131, agregacao-sql, rn-005, anti-fachada]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-01)
    provides: parâmetros relatorios.* + índices de desempenho (protocoled_at/decided_at)
  - phase: 15-relatorios-e-indicadores (15-02)
    provides: ReportFilters (bag + toProcessoFiltros + acessores) + ReportSource/ReportDefinition (contrato de export)
  - phase: 10-analise-tecnica
    provides: ProcessoQueryService::filtered (17 filtros SAPS) + ProcessoResource + analysis_category
  - phase: 09-fluxo-expresso
    provides: viability_decisions (outcome deferida/indeferida, decided_at)
  - phase: 06-classificacao-de-risco
    provides: risk_classifications (risco_municipal por cnae_code) + RuleVersion::vigente (Decreto 32.636/2020)
provides:
  - "IndicadoresViabilidadeService: porPeriodo/porZona/porCnae/porRisco/taxaDeferimento/taxaIndeferimento (HU-123..128) por agregação SQL real"
  - "SolicitacoesReportSource: ReportSource das solicitações filtradas (RN-005) reusando ProcessoQueryService::filtered via toProcessoFiltros"
affects: [15-06-http-telas, 15-09-dashboard, 15-08-xlsx]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Serviço de indicador route-free espelhando ProcessoQueryService: Builder + when() + groupBy/selectRaw/DB::raw, NUNCA loop PHP (Pattern 1 do RESEARCH)"
    - "Degradação honesta no SQL: zona→bairro com rótulo (HU-124); risco real→derivada com fonte por linha (HU-126); taxa null sem decisões (HU-127/128); listas vazias sem dado (CA-03)"
    - "ReportSource reconstrutível só pelo bag (sem estado de filtro no construtor) reusando ProcessoQueryService::filtered(toProcessoFiltros()) — zero filtro duplicado (RN-005)"

key-files:
  created:
    - app/Services/Relatorios/IndicadoresViabilidadeService.php
    - app/Services/Relatorios/Export/Sources/SolicitacoesReportSource.php
    - tests/Feature/Relatorios/IndicadoresViabilidadeServiceTest.php
    - tests/Feature/Relatorios/SolicitacoesReportSourceTest.php
  modified: []

key-decisions:
  - "porPeriodo trunca a data em SQL com date(protocoled_at) (portável SQLite/pgsql); população = protocoladas (protocoled_at não nulo), rascunho fora das métricas"
  - "porZona DEGRADA para bairro com rótulo honesto (zona oficial bloqueada SEDUR/GIS); bairro nulo NÃO vira bucket fantasma (whereNotNull)"
  - "porCnae conta processos DISTINTOS por código (count distinct viability_requests.id), top N (default 10)"
  - "porRisco prefere o nível REAL do Decreto pelo CNAE PRINCIPAL (leftJoin risk_classifications da versão vigente) → fallback analysis_category → 'nao_classificado'; cada linha carrega a fonte (real vs não-real), nunca um nível inventado"
  - "taxas = razão real sobre as DECIDIDAS no período (decided_at); taxa null sem decisões (CA-03), nunca 0% fabricado; deferimento e indeferimento não somam 100 artificialmente"
  - "SolicitacoesReportSource NÃO recorta o filtro manualmente: toProcessoFiltros() é a fonte única (RN-005 no síncrono e no assíncrono); personalData=true; event 'exporta-solicitacoes'"

# Metrics
duration: ~45 min
completed: 2026-06-15
---

# Phase 15 Plan 03: Indicadores de viabilidade + SolicitacoesReportSource Summary

**6 dos 11 indicadores da fase (HU-123 período, HU-124 zona→bairro, HU-125 CNAE, HU-126 risco real→derivada, HU-127/128 taxas) calculados por AGREGAÇÃO SQL real sobre os dados das Fases 8–10, com degradação honesta em todos os pontos (CA-03), mais o `SolicitacoesReportSource` que entrega o conjunto filtrado da consulta de processos ao export transversal reusando `ProcessoQueryService::filtered` (RN-005).**

## Performance

- **Duration:** ~45 min (com reconciliação de edições concorrentes na mesma frente)
- **Tasks:** 2 (commits atômicos)
- **Files created:** 4 (1 serviço + 1 source + 2 testes)

## Accomplishments

- **Task 1** (commit `e9f707b`): `IndicadoresViabilidadeService` com `porPeriodo`/`porZona`/`porCnae`/`porRisco` (HU-123/124/125/126) + `IndicadoresViabilidadeServiceTest` (6 casos, incluindo período vazio anti-fachada).
- **Task 2** (commits `ac354f3` + `a16dc27`): `taxaDeferimento`/`taxaIndeferimento` (HU-127/128) no mesmo serviço + `SolicitacoesReportSource` (RN-005) + `SolicitacoesReportSourceTest`; `a16dc27` reaplicou os métodos de taxa perdidos por uma edição concorrente do working tree (ver Issues).

## API entregue (para 15-06/15-09)

### `App\Services\Relatorios\IndicadoresViabilidadeService` (route-free, sem estado)

Todos recebem `ReportFilters $f` e agregam em SQL (`when()` + `groupBy`/`selectRaw`), espelhando `ProcessoQueryService`:

- `porPeriodo(ReportFilters $f): array` → `list<array{dia: string, total: int}>` (HU-123). Protocoladas por dia (`date(protocoled_at)`), ordenadas; sem dados → `[]`.
- `porZona(ReportFilters $f): array` → `array{degradacao: 'bairro', rotulo: string, itens: list<array{bairro: string, total: int}>}` (HU-124). Rótulo honesto de degradação; bairro nulo excluído.
- `porCnae(ReportFilters $f, int $limite = 10): array` → `list<array{cnae: string, total: int}>` (HU-125). `count(distinct viability_requests.id)` por `cnaes.code`, top N desc.
- `porRisco(ReportFilters $f): array` → `list<array{nivel: string, fonte: string, total: int}>` (HU-126). Nível real do Decreto (CNAE principal × `risk_classifications` da versão vigente) → fallback `analysis_category` → `'nao_classificado'`; `fonte` distingue o nível oficial (`'real'`) do inferido/ausente — nunca um nível inventado.
- `taxaDeferimento(ReportFilters $f): array` → `array{deferidas: int, total: int, taxa: float|null}` (HU-127).
- `taxaIndeferimento(ReportFilters $f): array` → `array{indeferidas: int, total: int, taxa: float|null}` (HU-128). `taxa` null sem decisões no período (CA-03); medidas sobre a MESMA base de decididas (`decided_at`), não somam 100 artificialmente.

Filtros comuns (período/setor/analista/bairro/categoria/cnae) aplicados via `when()`; colunas qualificadas (`viability_requests.*`) para conviver com os joins.

### `App\Services\Relatorios\Export\Sources\SolicitacoesReportSource implements ReportSource`

- `__construct(ProcessoQueryService $processos)` — sem estado de filtro (reconstrutível só pelo bag; o container resolve em `app(self::class)` no `GerarExportacaoJob`).
- `definition(ReportFilters $f): ReportDefinition` com:
  - `builder = fn () => $processos->filtered($f->toProcessoFiltros())` — **mapeamento RN-005**: `ReportFilters` (bag completo das telas) → `toProcessoFiltros()` (recorta as 17 chaves do SAPS) → `ProcessoQueryService::filtered` (o MESMO Builder filtrado da consulta de processos), sem filtro reimplementado; round-trip do bag garante o mesmo conjunto no síncrono E no assíncrono.
  - `colunas` = [protocolo, empresa, cnpj, status, categoria, bairro, analista, protocolado_em, decidido_em, resultado].
  - `mapRow($solicitacao)` lê os campos preparados pela `ProcessoResource` (status_label/categoria/CNPJ formatado — PII minimizada na origem, RN-007) + bairro e decisão (decided_at/outcome) do modelo carregado.
  - `logName='relatorios'`, `event='exporta-solicitacoes'`, `personalData=true`, `arquivoBase='solicitacoes'`.

## Decisions Made

- **Agregação SEMPRE em SQL:** `groupBy`/`selectRaw`/`DB::raw('count(...)')`, nunca `->get()->groupBy()` em coleção (grep confirma 0 ocorrências do anti-pattern). `count(distinct ...)` para processos distintos por CNAE/risco.
- **porRisco pelo CNAE PRINCIPAL:** `leftJoin` em `vrc.is_primary = true` → cada processo cai em exatamente um bucket (nível do CNAE principal), com `count(distinct)` por segurança. Coalesce `risco_municipal → analysis_category → 'nao_classificado'` e `fonte` por linha (transparência HU-126). Preferiu-se o nível REAL (join na versão vigente do Decreto), não o fallback — o join é barato com os índices da 15-01 no volume de Salvador.
- **Taxas honestas (CA-03):** `taxa = total > 0 ? round(x/total*100, 1) : null`. Null comunica "sem decisões no período", jamais 0% fingindo medição. Base = `viability_decisions` × período sobre `decided_at`.
- **SolicitacoesReportSource sem recorte manual:** `toProcessoFiltros()` é a fonte única do filtro; o source só monta colunas/mapRow. Reusa o eager-load do `ProcessoQueryService` (company/sector/assignedTo/decision) para o `mapRow` sem N+1.

## Deviations from Plan

### Refinamento (auto-aplicado, dentro do escopo)

**1. [Refinamento] `porRisco` reforça `fonte` apenas como `'real'` vs não-real no teste**
- O plano pede "fonte (real vs derivada)". A implementação distingue o nível OFICIAL do Decreto (`'real'`) do inferido/ausente. O teste assere estritamente `'real'` para níveis classificados e `'derivada'` para o caso com categoria, e para o bucket `'nao_classificado'` assere apenas que a fonte **nunca é `'real'`** (a garantia anti-fachada essencial), robusto à escolha honesta de rótulo do caso totalmente desconhecido (`indefinida`/`derivada`).

## Issues Encountered

- **Edição concorrente na mesma frente (Fase 15):** durante a execução, outro(s) agente(s) trabalhavam em paralelo na Fase 15 (commits `8973c5d`/`8f0251b` de 15-06 `ProdutividadeReportSource`; `07d1c8a`/`0920d40` de 15-08 XLSX) e chegaram a editar/reverter o working tree do MEU `IndicadoresViabilidadeService.php` (refatoração de `porRisco` e, depois, reversão que descartou os métodos de taxa que eu havia adicionado antes do stage). Efeito: o commit `ac354f3` (Task 2) entrou com o TESTE de taxa mas SEM os métodos no serviço (estado quebrado). **Resolução:** reapliquei os métodos de taxa e fiz o commit de correção `a16dc27`; alinhei a asserção de `porRisco` à semântica honesta vigente; verifiquei FRESCO com a suíte verde. Staging sempre seletivo (apenas meus 4 arquivos por caminho); nenhum arquivo de outras frentes (`ProdutividadeReportSource`, Fase 14 IA/e-mail, sidebar/vitest/seeder/rotas) foi tocado/commitado por mim.
- **Verificação evitando disrupção:** não rodei `migrate`/`migrate:fresh` global (há migrations pendentes da Fase 14 do trabalho paralelo). Os testes usam `RefreshDatabase` (SQLite isolado), sem necessidade de migration nova.

## Verification (evidência fresca)

- `php artisan test --compact --filter=IndicadoresViabilidadeServiceTest` → **6/6** (Task 1) e **8/8** após Task 2 (período/zona/cnae/categoria/vazio/risco + 2 de taxa).
- `php artisan test --compact --filter=SolicitacoesReportSourceTest` → **4/4** (RN-005, ordem das colunas, metadados de auditoria).
- Conjunto: ambos os filtros **12/12, 35 asserções**, exit 0.
- Anti-regressão: `php artisan test --compact --filter=Relatorios` → **48/48, 206 asserções** (inclui 15-01/15-02/15-06/15-08 das frentes paralelas).
- `grep`: `class IndicadoresViabilidadeService` presente; `groupBy` presente (5×); `->get()->groupBy(` ausente (agregação em SQL). `function taxaDeferimento(`/`taxaIndeferimento(` presentes; `implements ReportSource` no source.
- `vendor/bin/pint` nos meus 4 arquivos → `passed` (caminhos explícitos, sem `--dirty`, preservando o trabalho paralelo não commitado).

## Next Phase Readiness

- 6 indicadores prontos para o HTTP/telas (15-06) e o dashboard (15-09) consumirem; `SolicitacoesReportSource` pronto para o branch `?formato=` do controller de solicitações (RN-009), herdando CSV/PDF (e XLSX via 15-08).
- **15-04** entrega os indicadores restantes (tempo de análise HU-129, relatórios SAPS).
- Sem bloqueios externos para estes indicadores (dados reais das Fases 8–10). Zona urbanística oficial (HU-124) segue pendente SEDUR — degradação para bairro é honesta e definitiva até o GIS (Fase 13).

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-15*
