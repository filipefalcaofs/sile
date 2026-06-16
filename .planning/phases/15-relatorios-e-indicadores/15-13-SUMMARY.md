---
phase: 15-relatorios-e-indicadores
plan: 13
subsystem: ui
tags: [hu-122, hu-123, hu-124, hu-125, hu-126, hu-127, hu-128, hu-145, dashboard, kpi, echarts, export, inertia, react, nav, anti-fachada]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-03)
    provides: "IndicadoresViabilidadeService (porPeriodo/porZona/porCnae/porRisco/taxaDeferimento/taxaIndeferimento)"
  - phase: 15-relatorios-e-indicadores (15-05)
    provides: "TempoAnaliseService::tempoPorEtapa (tempo útil por etapa, HU-129)"
  - phase: 15-relatorios-e-indicadores (15-07)
    provides: "ExpressoQuedaService::taxaRespostaExpressa (HU-145, meta null honesta)"
  - phase: 15-relatorios-e-indicadores (15-09)
    provides: "RelatorioController::indicadores → Inertia 'gestao/relatorios/indicadores' [porPeriodo/porZona/porCnae/porRisco/taxaDeferimento/taxaIndeferimento/filtros] + ?formato= (SolicitacoesReportSource)"
  - phase: 15-relatorios-e-indicadores (15-12)
    provides: "wrapper <Chart> client-only ECharts + <ExportMenu> (ui/data-table)"
  - phase: 2-gestao-base
    provides: "DashboardController + KpiCard (padrão KPI gated por permissão, sem delta inventado)"
provides:
  - "Dashboard executivo (HU-122): bloco KPI 'relatorios' gated por consultar-relatorios com volume/taxas/tempo/expressa REAIS + séries serie_volume/por_risco para gráficos — SEM delta inventado"
  - "Página gestao/relatorios/indicadores.tsx (HU-123–128): filtros + KpiCards de taxa + gráficos ECharts (porPeriodo/porRisco/porCnae) + DataTable por bairro (HU-124) + ExportMenu"
  - "Grupo Relatórios na navegação: sidebar (gestao-layout, NÃO commitado) + Cmd+K (command-search, commitado)"
affects: [15-14-telas-relatorios-restantes]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "KPI operacional do EP15 estende o padrão Fase 2.4 (bloco gated por permissão); janela técnica via config relatorios.dashboard.janela_dias; SEM delta sem série histórica (anti-fachada CA-03)"
    - "Volume derivado da própria série (array_sum de porPeriodo) — dado real, sem método/coluna novos no serviço"
    - "Tela de relatório AGREGADO (não paginado): filtros server-driven por router.get + currentParams montado dos filtros para o <ExportMenu> (não usa useServerTable, que é para listas paginadas)"
    - "Gráficos via <Chart option={...}> (wrapper client-only 15-12), nunca import direto de 'echarts'"

key-files:
  created:
    - resources/js/pages/gestao/relatorios/indicadores.tsx
    - tests/Feature/Relatorios/DashboardKpisTest.php
  modified:
    - app/Http/Controllers/Gestao/DashboardController.php
    - resources/js/pages/gestao/dashboard.tsx
    - resources/js/components/app/command-search.tsx
    - config/sile.php
    - resources/js/layouts/gestao-layout.tsx (EDITADO no working tree, NÃO COMMITADO — arquivo do dono da Fase 14)

key-decisions:
  - "Janela do dashboard via constante técnica config relatorios.dashboard.janela_dias=30 (fora do catálogo HU-014, precedente [02-02]) — não é valor de negócio do licenciamento"
  - "Volume = array_sum(porPeriodo.total): dado real da série já existente, sem adicionar método ao serviço (evita tocar 15-03 e seus testes)"
  - "Grupo Relatórios adicionado ao gestao-layout.tsx no working tree mas deixado SEM COMMIT (arquivo do dono da Fase 14); o Cmd+K (command-search) tem lista própria e foi commitado"
  - "indicadores.tsx NÃO usa useServerTable (endpoint é agregado, não paginado): filtros via router.get + currentParams montado dos filtros para o ExportMenu — comportamento de export idêntico"

patterns-established:
  - "Dashboard executivo: KpiCards + 1–2 <Chart> alimentados por séries reais das props (serie_volume/por_risco)"
  - "Página de indicadores agregados: PageHeader + filtros + KPIs de taxa + gráficos + DataTable de detalhamento + ExportMenu, com rótulo honesto de degradação zona→bairro (HU-124)"

# Metrics
duration: ~35 min
completed: 2026-06-16
---

# Phase 15 Plan 13: Dashboard executivo (HU-122) + Indicadores de Viabilidade (HU-123–128) Summary

**O `DashboardController` ganha um bloco KPI `relatorios` gated por `consultar-relatorios` com volume/taxa de deferimento/indeferimento/tempo médio de análise/taxa de resposta expressa REAIS da janela corrente (e séries `serie_volume`/`por_risco` para os gráficos) — SEM `delta` inventado (CA-03); o `dashboard.tsx` renderiza esses KPIs + gráficos ECharts, a nova página `relatorios/indicadores.tsx` entrega filtros + gráficos + DataTable por bairro + `<ExportMenu>` consumindo o payload real de 15-09, e a navegação ganha o grupo "Relatórios" (Cmd+K commitado; sidebar no working tree).**

## Performance

- **Duration:** ~35 min
- **Completed:** 2026-06-16
- **Tasks:** 3 (TDD na Task 1; staging seletivo por caminho)
- **Files:** 2 criados (página + teste), 4 modificados/commitados (controller, dashboard, command-search, config), 1 modificado SEM commit (gestao-layout)

## Accomplishments

- **Dashboard executivo (HU-122):** bloco `kpis.relatorios` gated por `consultar-relatorios` (padrão Fase 2.4). Cada número vem de um serviço route-free real do EP15; SEM série histórica persistida ⇒ SEM `delta`/"+X%" (anti-fachada CA-03). Degradação honesta: taxa `null` sem decisões/elegíveis, `volume` 0 real, `meta_expressa` `null` quando não definida.
- **Telas:** `dashboard.tsx` renderiza os KpiCards do EP15 + gráfico de volume (linha) e distribuição por risco (rosca); `relatorios/indicadores.tsx` (HU-123–128) entrega filtros (período/bairro/CNAE/categoria), KPIs de taxa, gráficos `porPeriodo`/`porRisco`/`porCnae` e DataTable por bairro com o rótulo honesto de degradação zona→bairro (HU-124) + `<ExportMenu>`.
- **Navegação:** grupo "Relatórios" (Indicadores/Tempo/Produtividade/Quedas gated por `consultar-relatorios` + Feriados por `manter-parametros`). Cmd+K (`command-search.tsx`) commitado; sidebar (`gestao-layout.tsx`) editada no working tree e deixada SEM commit (arquivo do dono da Fase 14).
- **TDD:** `DashboardKpisTest` prova os números EXATOS, a AUSÊNCIA de `delta`, o gating (null sem permissão) e a degradação honesta sem dados.

## Task Commits

1. **Task 1 (RED): teste dos KPIs do EP15 no dashboard** — `b480325` (test)
2. **Task 1 (GREEN): bloco KPI relatorios gated no DashboardController + janela de config** — `a18692c` (feat)
3. **Task 2: dashboard.tsx (KpiCards + gráficos) + relatorios/indicadores.tsx** — `0c807c8` (feat)
4. **Task 3: grupo Relatórios no Cmd+K (command-search)** — `76aba17` (feat)

**Plan metadata:** este SUMMARY + STATE.md (`docs(15-13)`)

## (a) KPIs do EP15 adicionados ao dashboard

Bloco `kpis.relatorios` (null sem `consultar-relatorios`):

| Chave | Origem (serviço real) | Honestidade |
|---|---|---|
| `volume` (int) | `array_sum` de `IndicadoresViabilidadeService::porPeriodo` | 0 real sem protocoladas |
| `taxa_deferimento` (float\|null) | `IndicadoresViabilidadeService::taxaDeferimento` | null sem decisões |
| `taxa_indeferimento` (float\|null) | `IndicadoresViabilidadeService::taxaIndeferimento` | null sem decisões |
| `tempo_analise_minutos` (int\|null) | `TempoAnaliseService::tempoPorEtapa` (etapa `analise`) | null sem amostras |
| `taxa_expressa` (float\|null) | `ExpressoQuedaService::taxaRespostaExpressa` | null sem elegíveis |
| `meta_expressa` (float\|null) | parâmetro `relatorios.expresso.meta_taxa` | null = "meta não definida" (RN-004) |
| `janela_dias` (int) | `relatorios.dashboard.janela_dias` (config técnica) | — |
| `serie_volume` (list) | `porPeriodo` | série real p/ o gráfico de linha |
| `por_risco` (list) | `porRisco` | distribuição real p/ a rosca |

**NÃO existe chave `delta`** em nenhum nível — sem janela histórica persistida não há comparativo, e fabricá-lo seria fachada (CA-03). Janela padrão: últimos 30 dias (`now()->subDays(30)`→`now()`).

## (b) Shape das props de `gestao/relatorios/indicadores`

Exatamente o que `RelatorioController::indicadores` renderiza (15-09):

- `porPeriodo`: `{ dia: string; total: number }[]`
- `porZona`: `{ degradacao: string; rotulo: string; itens: { bairro: string; total: number }[] }` (HU-124 — rótulo honesto)
- `porCnae`: `{ cnae: string; total: number }[]`
- `porRisco`: `{ nivel: string; fonte: 'real'|'derivada'|'indefinida'; total: number }[]`
- `taxaDeferimento`: `{ deferidas: number; total: number; taxa: number|null }`
- `taxaIndeferimento`: `{ indeferidas: number; total: number; taxa: number|null }`
- `filtros`: bag aplicado (`data_de/data_ate/bairro/cnae/categoria/setor/analista` — só os preenchidos)

O `<ExportMenu url="/gestao/relatorios/indicadores" params={currentParams}>` aponta para o mesmo endpoint com `?formato=` (exporta o conjunto filtrado via `SolicitacoesReportSource`). Filtros de `setor`/`analista` (precisam de listas de opções do backend) ficam para 15-14.

## (c) Itens de navegação / Cmd+K adicionados

Grupo "Relatórios" (sidebar `gestao-layout` + Cmd+K `command-search`):

| Item | Rota (path real) | Permissão |
|---|---|---|
| Indicadores de viabilidade | `/gestao/relatorios/indicadores` | `consultar-relatorios` |
| Tempo de análise | `/gestao/relatorios/tempo` | `consultar-relatorios` |
| Produtividade | `/gestao/relatorios/produtividade` | `consultar-relatorios` |
| Quedas do expresso | `/gestao/relatorios/quedas` | `consultar-relatorios` |
| Feriados | `/gestao/feriados` | `manter-parametros` |

As páginas de tempo/produtividade/quedas/feriados são criadas em 15-14 — os links já apontam para as rotas reais (criadas em 15-09).

## (d) DECISÃO — grupo Relatórios no `gestao-layout.tsx` SEM commit (pendência de integração)

A navegação foi refatorada pelo usuário (Fase 14): `app-sidebar.tsx` só RENDERIZA um array `groups: SidebarGroup[]` recebido por props, e os grupos da gestão são declarados em `resources/js/layouts/gestao-layout.tsx`. O grupo "Relatórios" foi adicionado lá (mesmo padrão do grupo "Auditoria e compliance", `visible: auth.permissions.includes(...)`), MAS o arquivo `gestao-layout.tsx` já estava modificado/NÃO COMMITADO pelo dono da Fase 14 — então a edição foi deixada no working tree **SEM commit** (staging seletivo proibia tocá-lo). O critério de aceite original do plano (`grep consultar-relatorios app-sidebar.tsx`) estava DESATUALIZADO (pré-refactor) e foi ignorado; o gating de `consultar-relatorios` vive agora no `gestao-layout`.

**PENDÊNCIA DE INTEGRAÇÃO (dono da Fase 14):** commitar o grupo "Relatórios" do `gestao-layout.tsx` junto com o trabalho da Fase 14. Sem esse commit, a sidebar NÃO mostra o grupo Relatórios no repositório (mas o Cmd+K já mostra, pois `command-search.tsx` foi commitado). O `app-sidebar.tsx` NÃO foi tocado.

## Files Created/Modified

- `tests/Feature/Relatorios/DashboardKpisTest.php` — CRIADO: prova KPIs exatos, ausência de `delta`, gating e degradação honesta.
- `app/Http/Controllers/Gestao/DashboardController.php` — MODIFICADO/COMMITADO: bloco `relatorios` gated + método `relatoriosKpis` (injeção dos 3 serviços no `__invoke`).
- `config/sile.php` — MODIFICADO/COMMITADO: `relatorios.dashboard.janela_dias=30` (constante técnica).
- `resources/js/pages/gestao/dashboard.tsx` — MODIFICADO/COMMITADO: seção Relatórios (KpiCards + 2 gráficos).
- `resources/js/pages/gestao/relatorios/indicadores.tsx` — CRIADO: tela de indicadores de viabilidade.
- `resources/js/components/app/command-search.tsx` — MODIFICADO/COMMITADO: grupo Relatórios no Cmd+K.
- `resources/js/layouts/gestao-layout.tsx` — MODIFICADO no working tree, **NÃO COMMITADO** (arquivo do dono da Fase 14): grupo Relatórios na sidebar.

## Decisions Made

- **Janela técnica de config** (`relatorios.dashboard.janela_dias=30`) em vez de parâmetro do catálogo: é recorte de leitura do painel, não valor de negócio (precedente [02-02]); ajustável sem deploy via `Settings::get`.
- **Volume sem método novo no serviço:** `array_sum(array_column(porPeriodo, 'total'))` reusa a série real, evitando tocar 15-03 e sua suíte.
- **Asserção numérica das taxas no teste:** a taxa float trafega como inteiro no JSON; o teste compara `(float) $taxa === 75.0` (a intenção — a razão real — é preservada, sem acoplar ao tipo do wire).
- **indicadores.tsx sem useServerTable:** o endpoint é AGREGADO (não paginado); os filtros são server-driven por `router.get` e o `currentParams` é montado dos filtros para o `<ExportMenu>` (export idêntico, sem fingir paginação que o backend não tem).

## Deviations from Plan

### Divergência conhecida do plano (refactor do usuário) + ajustes

**1. [Plano desatualizado] Grupo de nav no `gestao-layout`, não no `app-sidebar`**
- **Contexto:** o plano (e seu critério `grep consultar-relatorios app-sidebar.tsx`) foi escrito antes do refactor da navegação. Hoje `app-sidebar.tsx` só renderiza `groups` por props; os grupos vivem no `gestao-layout.tsx`.
- **Resolução:** grupo "Relatórios" adicionado ao `gestao-layout.tsx` (não tocado o `app-sidebar.tsx`); critério desatualizado ignorado. `gestao-layout.tsx` deixado SEM commit (arquivo do dono da Fase 14) — ver seção (d).

**2. [Refinamento] `config/sile.php` (6º arquivo commitado na Task 1)**
- Adicionado `relatorios.dashboard.janela_dias` (constante técnica). Arquivo limpo no repositório (não é trabalho da Fase 14) — commitado junto da implementação (GREEN).

**3. [Refinamento] `indicadores.tsx` não usa `useServerTable`**
- O plano sugeria `useServerTable`; o endpoint de indicadores é agregado (sem paginação), então os filtros são geridos diretamente e o `currentParams` do `<ExportMenu>` é montado dos filtros. Sem impacto no comportamento de export (RN-004).

**Total deviations:** 1 divergência de plano (refactor do usuário) + 2 refinamentos. **Impacto no plano:** escopo intacto; trabalho da Fase 14 do usuário 100% preservado (staging seletivo por caminho).

## Issues Encountered

- **Tipo da taxa no JSON:** a primeira asserção (`assertSame(75.0, ...)`) falhou porque a taxa float chega como inteiro no payload. Resolvido com comparação numérica `(float) $taxa === X` no teste (RED→GREEN limpo) — comportamento (75%/25%/50%) inalterado.

## Next Phase Readiness

- **15-14 (telas restantes):** `tempo`/`produtividade`/`quedas`/`feriados` (componentes Inertia de 15-09) ainda a criar; os links de nav e Cmd+K já apontam para as rotas reais. Padrão de tela (filtros + `<Chart>` + DataTable + `<ExportMenu>`) estabelecido por `indicadores.tsx`. Filtros `setor`/`analista` (com listas de opções) a completar.
- **PENDÊNCIA DE INTEGRAÇÃO (dono da Fase 14):** commitar o grupo "Relatórios" em `gestao-layout.tsx` (working tree) — sem isso, a sidebar não exibe o grupo no repositório (Cmd+K já exibe).

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-16*
