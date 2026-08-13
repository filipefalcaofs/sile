---
phase: 15-relatorios-e-indicadores
plan: 06
subsystem: relatorios-indicadores
tags: [hu-130, produtividade, analista, lgpd, rh, anonimizacao, agregacao-sql, report-source, sync-only]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-01)
    provides: permissão relatorios.produtividade.nominal (gate no HTTP de 15-09) + índice viability_decisions.decided_by_user_id
  - phase: 15-relatorios-e-indicadores (15-02)
    provides: contrato de exportação (ReportFilters/ReportDefinition/ReportSource/SyncOnlyReportSource) + ReportExporter (força síncrono p/ SyncOnly)
  - phase: 10-analise-tecnica
    provides: ViabilityDecision flow 'analise_tecnica' + decided_by_user_id (analista ≠ null) — fonte da produtividade
  - phase: 08-solicitacao-viabilidade
    provides: viability_requests.assigned_user_id / protocoled_at — processos atribuídos
provides:
  - "ProdutividadeAnalistaService::porAnalista (agregado/anonimizado por default; nominal e escopo do próprio parametrizados; vazio honesto sem dado — CA-03)"
  - "ProdutividadeAnalistaService::decisoesAgregadas (Builder agregado reutilizável — fonte compartilhada com o ReportSource)"
  - "ProdutividadeReportSource implements ReportSource, SyncOnlyReportSource (export anônimo por default, nominal sob flag, mesma minimização RN-007)"
affects: [15-09-http-dashboard-download]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Serviço de indicador route-free agregando em SQL (groupBy + selectRaw com bind), espelhando ProcessoQueryService"
    - "Minimização por default: rótulo ordinal anônimo (Analista #N) no serviço; identidade só no modo nominal (parâmetro), gate de permissão no HTTP"
    - "Builder agregado compartilhado serviço↔source; o source envelopa em fromSub (count() conta analistas, não grupos) + row_number() para o ordinal anônimo estável no streaming"
    - "SyncOnlyReportSource para source com estado de construtor (nominal/escopo) — ReportExporter força síncrono, nunca vai ao Job"

key-files:
  created:
    - app/Services/Relatorios/ProdutividadeAnalistaService.php
    - app/Services/Relatorios/Export/Sources/ProdutividadeReportSource.php
    - tests/Feature/Relatorios/ProdutividadeAnalistaServiceTest.php
    - tests/Feature/Relatorios/ProdutividadeReportSourceTest.php
  modified: []

key-decisions:
  - "Produtividade = decisões de análise técnica (flow='analise_tecnica' + decided_by_user_id NOT NULL) por decided_by_user_id; o expresso automático (decided_by null) NÃO é produtividade de analista"
  - "Default AGREGADO/ANONIMIZADO: rótulo ordinal 'Analista #N' por ordem de volume (decididas desc, analista_id asc); o modo nominal (join users → nome) é parâmetro, com o gate relatorios.produtividade.nominal e a auditoria no HTTP de 15-09"
  - "scopeUserId IGNORA nominal (é o recorte do próprio analista): devolve só a linha dele, anonimizada (sem nome), personalData=false"
  - "atribuidas (assigned_user_id) é métrica complementar do SERVIÇO (não vai ao export); decididas/deferidas/indeferidas são a coluna comum"
  - "ProdutividadeReportSource implements ReportSource, SyncOnlyReportSource — estado de construtor (nominal/escopo) preservado pelo síncrono forçado; o builder usa fromSub para count() correto e row_number() para o ordinal anônimo no streaming por chunks"
  - "Outcomes vão como bind no selectRaw (não interpolados); agregação SEMPRE em SQL (sem ->get()->groupBy())"

# Metrics
duration: ~35 min
completed: 2026-06-15
---

# Phase 15 Plan 06: Produtividade por analista (HU-130) Summary

**HU-130 calculada sobre DADO REAL em modo conservador RH/LGPD: `ProdutividadeAnalistaService::porAnalista` agrega em SQL as decisões de análise técnica por `decided_by_user_id` (decididas/deferidas/indeferidas) + processos atribuídos por `assigned_user_id`, ANÔNIMO por default (rótulo ordinal `Analista #N`, sem identidade), com a visão NOMINAL e o ESCOPO do próprio analista como parâmetros; `ProdutividadeReportSource` exporta com a MESMA minimização e implementa `SyncOnlyReportSource` (estado de construtor preservado pelo caminho síncrono forçado). Sem decisões no período → vazio honesto, NUNCA produtividade inventada (CA-03).**

## API entregue (para 15-09)

### `App\Services\Relatorios\ProdutividadeAnalistaService`
- `porAnalista(ReportFilters $filtros, bool $nominal = false, ?int $scopeUserId = null): array` — produtividade por analista no recorte do filtro (período em `decided_at`).
  - **Default (`nominal=false`):** cada linha = `['analista_rotulo' => 'Analista #N', 'decididas', 'deferidas', 'indeferidas', 'atribuidas']` — SEM nome nem id (minimização RN-007).
  - **`nominal=true`:** linha = `['analista_id', 'nome', 'decididas', 'deferidas', 'indeferidas', 'atribuidas']` (join `users`). O gate `relatorios.produtividade.nominal` e a auditoria ficam no HTTP de **15-09** — o serviço só expõe o parâmetro.
  - **`scopeUserId` (analista vendo o próprio):** filtra `decided_by_user_id = scopeUserId` e **ignora o nominal** (é o próprio) → devolve só a linha dele, anonimizada.
  - **Vazio:** sem decisões no período ⇒ `[]` (CA-03 anti-fachada).
- `decisoesAgregadas(ReportFilters $f, bool $nominal = false, ?int $scopeUserId = null): Builder` — Builder agregado reutilizável (`where flow='analise_tecnica'` + `whereNotNull(decided_by_user_id)` + `when()` período/escopo + `groupBy(decided_by_user_id)` + `selectRaw count/sum(case ... outcome = ?)` com bind dos outcomes; join `users` + nome no modo nominal). É a FONTE compartilhada com o `ProdutividadeReportSource`.

### Como o rótulo anônimo é gerado
- **Serviço:** ordinal estável `'Analista #'.($i + 1)` calculado em PHP sobre o resultado já ordenado por volume (`orderByDesc('decididas')->orderBy('analista_id')`).
- **Source (streaming):** o `mapRow` não tem índice por linha; o ordinal vem de `row_number() over (order by decididas desc, analista_id asc) as posicao` no Builder → `'Analista #'.posicao`. Estável no `chunk()` do CsvExporter e no `get()` do PdfExporter, sem identidade.

### `App\Services\Relatorios\Export\Sources\ProdutividadeReportSource implements ReportSource, SyncOnlyReportSource`
- `__construct(bool $nominal = false, ?int $scopeUserId = null)` — ESTADO de construtor.
- `definition(ReportFilters): ReportDefinition` — colunas anônimas por default (`analista_rotulo, decididas, deferidas, indeferidas`) e com `analista_nome` só quando `nominal=true`; `personalData = nominal` (nominal expõe identidade → herda meta-auditoria PII); `event='exporta-produtividade'`, `arquivoBase='produtividade-analistas'`.
- **SyncOnly (a correção do checker):** o source carrega estado não reconstrutível só pelo bag → implementa o marcador; o `ReportExporter` força o síncrono e NUNCA despacha o `GerarExportacaoJob` (que reconstruiria via `app($sourceClass)` e perderia `nominal`/`scopeUserId`). O builder usa `fromSub` para que o `count()` do exporter conte ANALISTAS (não a contagem do 1º grupo).

## Task Commits

1. **Task 1: ProdutividadeAnalistaService** — `0168c1e` (feat)
2. **Task 2: ProdutividadeReportSource** — `8973c5d` (feat)

## Deviations from Plan

### Refinamentos (auto-aplicados, dentro do escopo)

**1. [Refinamento] `decisoesAgregadas` extraído como Builder público compartilhado**
- O plano previa o serviço (array) e o source reproduzindo "a MESMA agregação em forma de Builder". Em vez de duplicar o `selectRaw`, extraí `decisoesAgregadas(): Builder` no serviço e o `ProdutividadeReportSource` o consome (`fromSub`). DRY real: uma única definição da agregação, duas formas de consumo (array no serviço, streaming no export).

**2. [Refinamento] `fromSub` + `row_number()` no source**
- O contrato de export chama `builder()->count()` (volume da auditoria) e `->chunk()` (CSV). Um Builder com `groupBy` faria o `count()` devolver a contagem do PRIMEIRO grupo (não o nº de analistas). Envelopei a agregação em `fromSub` → `count()` conta analistas corretamente; e o ordinal anônimo (`Analista #N`) sai de `row_number()` no SQL (o `mapRow` por linha não tem índice). Validado em SQLite (window functions) pelos testes.

**3. [Refinamento] `implements ReportSource, SyncOnlyReportSource` explícito**
- `SyncOnlyReportSource` já estende `ReportSource`; declarei as duas explicitamente para casar com o `contains: "implements ReportSource"` do plano e deixar a intenção legível.

### Coordenação com trabalho paralelo (sem mudança de escopo)

**4. [Coordenação] Staging seletivo + pint escopado**
- O working tree tem frentes paralelas NÃO commitadas do usuário (Fase 14 IA/e-mail; sidebar/vitest/package.json; `RolesAndPermissionsSeeder`/`routes/gestao.php`/`bootstrap/providers.php`) e um agente paralelo do 15-08 (XLSX) commitou entre minhas tasks. Cada commit recebeu APENAS os 4 arquivos do 15-06 (`git add` por caminho); `pint` rodado com caminhos explícitos (não `--dirty`, que reformataria os arquivos do usuário). Nenhum arquivo paralelo tocado/staged/commitado.

**Total deviations:** 3 refinamentos + 1 coordenação. **Impacto no plano:** nenhum (escopo intacto).

## Verification (evidência fresca)

- `php artisan test --compact --filter=ProdutividadeAnalistaServiceTest` → **4/4 (26 asserções)**.
- `php artisan test --compact --filter=ProdutividadeReportSourceTest` → **5/5 (17 asserções)**.
- `php artisan test --compact --filter=Produtividade` → **9/9 (43 asserções)**.
- `vendor/bin/pint` nos 4 arquivos do 15-06 → **passed** (sem pendências).
- Aceite: `grep -c "implements ReportSource"` = 1, `grep -c "SyncOnlyReportSource"` = 3; serviço usa `groupBy` SQL (sem `->get()->groupBy(`); `function porAnalista` presente.
- Anti-fachada/LGPD provados pelos testes: ruído (decisão expressa do sistema + decisão da Ana em outro fluxo) NÃO conta (só `analise_tecnica`); default sem nome/id; nominal sob flag; escopo só o próprio; período sem dado → `[]`.

## Issues Encountered

- **Mismatch do plano (coluna):** o plano citava "fichas/processos atribuídos via `assigned_user_id` (analysis_records)", mas `analysis_records` usa `analyst_user_id`; `assigned_user_id` mora em `viability_requests`. Implementei `atribuidas` por `viability_requests.assigned_user_id` (a coluna real e indexada), honrando o must_have "por decided_by_user_id e assigned_user_id".
- **Factories com unique constante:** `ViabilityRequestFactory::protocoled()` usa `protocol_number` constante e a decisão deferida usa `tvl_product_number` constante (ambos `unique`). Nos testes, criei a solicitação com `protocol_number=null` e a decisão com `tvl_product_number=null` (NULL não colide no unique) para cravar várias decisões por analista.
- **Não rodei `migrate` global** (migrations pendentes da Fase 14 — trabalho paralelo). Evidência via `RefreshDatabase` (SQLite isolado).

## Next Phase Readiness

- **15-09 (HTTP/dashboard/download):** instanciar `ProdutividadeReportSource` e chamar `porAnalista` resolvendo `nominal` pela permissão `relatorios.produtividade.nominal` e `scopeUserId = $request->user()->id` para o analista (gestor vê todos); auditar a consulta nominal. A rota de download `gestao.relatorios.exportacoes.download` (pendência herdada do 15-02) também nasce em 15-09.
- **Política definitiva de quem vê nominal** = confirmação SEDUR (o default conservador anonimizado não bloqueia).

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-15*
