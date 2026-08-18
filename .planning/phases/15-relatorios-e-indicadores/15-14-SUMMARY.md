---
phase: 15-relatorios-e-indicadores
plan: 14
subsystem: ui
tags: [hu-129, hu-130, hu-145, hu-137, inertia, react, echarts, export, relatorios, anti-fachada, rn-004, rn-007, lgpd]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-09)
    provides: "RelatorioController (Inertia tempo/produtividade/quedas com dado real + ?formato=/?relatorio=) e HolidayController CRUD (gestao/feriados/index)"
  - phase: 15-relatorios-e-indicadores (15-12)
    provides: "Chart (wrapper ECharts client-only SSR-safe) + ExportMenu (monta a URL ?formato= com os filtros atuais)"
  - phase: 15-relatorios-e-indicadores (15-13)
    provides: "Tela de indicadores (mesmo padrão filtros+Chart+ExportMenu) e navegação para os 4 relatórios/feriados"
provides:
  - "Tela Tempo de análise (HU-129): tempo médio por etapa em dias úteis/horas + emissão de TVL + export das sedes de escritório virtual"
  - "Tela Produtividade por analista (HU-130): anônima por default, nominal só quando o backend liberou (a tela não força nome)"
  - "Tela Quedas do fluxo expresso (HU-145): taxa + meta parametrizável/'meta não definida' + série temporal + ranking + drill-down"
  - "Tela de cadastro de Feriados (HU-137): CRUD server-driven (criar/editar/ativar-inativar) com ressalva da lista municipal pendente"
affects: [15-15-retencao-exportacoes]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Tela de relatório React: filtro de período self-managed (router.get) + KpiCard + Chart (ECharts via wrapper client-only) + DataTable + ExportMenu (?formato= com o recorte atual) — espelha gestao/relatorios/indicadores (15-13)"
    - "Degradação honesta na UI: travessão p/ média/taxa null; 'meta não definida' p/ meta null (RN-004); rótulo ordinal anônimo p/ produtividade sem permissão nominal (RN-007); ressalva de feriados não oficiais"
    - "Um endpoint que serve mais de um source pelo ?relatorio=: o ExportMenu recebe relatorio='escritorio-virtual' como param (nunca query na URL base, que duplicaria o ?)"

key-files:
  created:
    - resources/js/pages/gestao/relatorios/tempo.tsx
    - resources/js/pages/gestao/relatorios/produtividade.tsx
    - resources/js/pages/gestao/relatorios/quedas.tsx
    - resources/js/pages/gestao/feriados/index.tsx
  modified: []

key-decisions:
  - "Feriados NÃO recebe ExportMenu: o HolidayController (15-09) não tem branch ?formato= (é CRUD de parâmetro, não relatório). Adicionar o menu produziria um link que recarrega a tela em vez de baixar — fachada proibida. Espelha setores (que também não exporta). O export transversal fica nas 3 telas de relatório (que têm ?formato= real)."
  - "Filtros das telas de relatório limitados a período (data_de/data_ate): tempo/produtividade/quedas só consideram período nos serviços route-free (15-05/06/07). Exibir categoria/bairro nessas telas seria filtro que não muda o número (fachada). Setor/analista não foram surfados porque o controller não passa as listas de opções (exigiria mudança de backend fora dos files_modified do plano)."
  - "Sedes de escritório virtual é export-only: o RelatorioController::tempo passa só tempoPorEtapa/tempoEmissaoTvl/filtros; a lista de sedes é servida pelo export (?relatorio=escritorio-virtual). A tela expõe a ação de exportar, sem inventar uma tabela inline."
  - "Drill-down de quedas é um link para gestao/processos do mesmo período: o controller não passa a lista de processos caídos; as divergências analista×motor (HU-140) ficam na ficha de cada processo, então o link leva à superfície real."

patterns-established:
  - "minutos úteis → exibição: 1 dia útil = 1440 min (semântica do BusinessDeadlineCalculator), 1 h = 60 min; média null vira travessão"

# Metrics
duration: ~20 min
completed: 2026-06-16
---

# Phase 15 Plan 14: Telas de relatório (HU-129/130/145) + cadastro de Feriados (HU-137) Summary

**Quatro páginas React Inertia fecham os indicadores do EP15 consumindo o dado REAL do `RelatorioController`/`HolidayController` (15-09) com `<Chart>` (ECharts client-only de 15-12) e `<ExportMenu>` (`?formato=` do recorte filtrado): Tempo de análise por etapa em dias úteis + emissão de TVL + export de escritório virtual via `?relatorio=`; Produtividade anônima por default (nominal só sob permissão do backend); Quedas do expresso com taxa, meta parametrizável ou "meta não definida", série temporal com linha de meta, ranking e drill-down; e o CRUD de Feriados espelhando `setores` — todas com degradação honesta visível.**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-06-16
- **Tasks:** 3 (cada uma com commit atômico; staging seletivo por caminho)
- **Files created:** 4 páginas React; 0 modificados (trabalho paralelo do usuário 100% preservado)

## Accomplishments

- **Tempo de análise (HU-129):** gráfico de barras do tempo médio POR ETAPA (preenchimento/espera/análise/pendência) convertendo minutos úteis para horas/dias úteis; KpiCard do tempo de emissão do TVL (SAPS); card de export das sedes de escritório virtual (`?relatorio=escritorio-virtual`); ressalva honesta de cálculo em dias úteis sem feriados municipais oficiais.
- **Produtividade (HU-130):** tabela + gráfico de volume por analista com **rótulo ordinal anônimo por default**; nome só aparece quando o backend mandou (`nominal=true`) — a tela nunca força o nome. Aviso de visão anônima (RN-007/LGPD) vs. aviso de visão nominal habilitada.
- **Quedas do expresso (HU-145):** card da taxa de resposta expressa (`—` sem base), card da meta com **"meta não definida"** quando `meta=null` (RN-004), `<Chart>` de LINHA da série temporal com linha de meta quando parametrizada, ranking de motivos ("não classificado" para gatilho null) e seção de drill-down (link aos processos do período).
- **Feriados (HU-137):** listagem server-driven (`useServerTable`) com criar/editar e ativar/inativar (sem excluir — RN-004), formulário que envia a situação explícita (evita reativação acidental na edição pelo default do `HolidayRequest`), e ressalva da pendência SEDUR da lista municipal oficial.
- **Verificação fresca:** `npx tsc --noEmit` exit 0; `npm run build` exit 0 com os 4 chunks emitidos (`tempo-*`, `produtividade-*`, `quedas-*`, `feriados-*`) e as 4 entradas no `manifest.json`.

## Task Commits

1. **Task 1: Tela Tempo de análise (HU-129 por etapa + SAPS + ressalva)** — `c20479c` (feat)
2. **Task 2: Telas Produtividade (HU-130) e Quedas do expresso (HU-145)** — `f055b4b` (feat)
3. **Task 3: Tela de cadastro de Feriados (HU-137) + build smoke** — `13b9082` (feat)

**Plan metadata:** este SUMMARY + STATE.md (docs: complete plan)

## Shape das props consumidas (de 15-09)

- **`gestao/relatorios/tempo`** → `tempoPorEtapa: { etapas: { etapa, media_minutos: number|null, amostras }[] }`, `tempoEmissaoTvl: { media_minutos: number|null, amostras }`, `filtros`. Export default → `TempoAnaliseReportSource`; `?relatorio=escritorio-virtual` → `EscritorioVirtualReportSource`.
- **`gestao/relatorios/produtividade`** → `produtividade: row[]` (anônimo `{ analista_rotulo, decididas, deferidas, indeferidas, atribuidas }` ou nominal `{ analista_id, nome, ... }`), `nominal: boolean`, `filtros`.
- **`gestao/relatorios/quedas`** → `taxa: { respondidas, elegiveis, taxa: number|null, meta: number|null }`, `serie: { dia, respondidas, elegiveis, taxa: number|null }[]`, `ranking: { tipo_gatilho: string|null, rotulo, total }[]`, `filtros`.
- **`gestao/feriados/index`** → `holidays` (paginador `{ data: { id, date, name, recurring_annually, active }[], links, from, to, total }`), `filters: { search, per_page, active }`, `perPageOptions`. Rotas: `gestao.feriados.store/update/ativacao.update`.

## `?relatorio=` usado (endpoint que serve mais de um source)

- `gestao.relatorios.tempo`: ExportMenu padrão = tempo por etapa (sem `relatorio`); ExportMenu das sedes passa `params={{ ...periodo, relatorio: 'escritorio-virtual' }}` (param, não query na URL base — evita o `?` duplicado do `ExportMenu.buildHref`).

## Degradações honestas exibidas (anti-fachada)

- **Tempo:** ressalva "tempos em dias úteis, descontando fins de semana e os feriados cadastrados; lista oficial municipal pendente da SEDUR — nenhum feriado inventado"; média de etapa sem amostra vira `—`.
- **Produtividade:** rótulo ordinal anônimo por default + aviso "visão nominal restrita à permissão (RN-007)"; a tela só mostra nome quando `nominal=true`.
- **Quedas:** taxa `—` sem elegíveis; **"meta não definida"** quando a meta não está parametrizada (RN-004, nunca inventada); ranking com "não classificado" para gatilho null.
- **Feriados:** ressalva da lista oficial municipal pendente da SEDUR; até a carga oficial só os feriados cadastrados são descontados.

## Decisions Made

- **Feriados sem ExportMenu (anti-fachada):** o `HolidayController` (15-09) não tem `?formato=` — é CRUD de parâmetro, não relatório. Um ExportMenu apontando para `gestao/feriados?formato=csv` recarregaria a página (Inertia) em vez de baixar arquivo → fachada proibida. Espelha `setores/index.tsx` (que também não exporta). O export transversal (HU-131) vive nas 3 telas de relatório com `?formato=` REAL.
- **Filtros só de período nas telas de relatório:** os serviços de tempo/produtividade/quedas só recortam por período (e setor/analista, sem lista de opções no payload). Categoria/bairro não afetam esses números — exibi-los seria filtro de fachada.
- **Sedes de escritório virtual export-only** e **drill-down de quedas como link a `gestao/processos`**: as props não trazem essas listas; a tela leva à superfície real (export / consulta de processos) sem inventar tabela inline.

## Deviations from Plan

### Decisões de honestidade/escopo (sem mudança de comportamento)

**1. [Anti-fachada] Feriados entregue SEM ExportMenu**
- **Encontrado em:** Task 3 (e no must-have "todas as tabelas têm ExportMenu").
- **Motivo:** o backend de feriados (15-09) não expõe export (`?formato=`). Adicionar o menu seria um botão que não baixa nada (fachada — regra `entrega-funcional`). `setores` (modelo espelhado) também não exporta.
- **Resolução:** ExportMenu aplicado às 3 telas de relatório (export real via `?formato=`); feriados é CRUD server-driven completo (criar/editar/ativar-inativar). Habilitar export de feriados exigiria novo `ReportSource` + branch no `HolidayController` + rota nova em `routes/gestao.php` (arquivo fora do escopo/permitido neste plano). **Pendência para a coordenação:** decidir se o calendário de feriados deve ganhar export próprio.

**2. [Escopo] Filtros das telas de relatório limitados a período**
- **Motivo:** os serviços route-free só consideram período (tempo/produtividade/quedas); setor/analista exigem listas de opções que o controller não passa. Exibir filtros que não mudam o número (ou selects vazios) seria fachada.
- **Resolução:** período (data_de/data_ate) — filtro real e funcional, espelhando `indicadores`. Surfacing de setor/analista fica pendente de o backend passar as opções (mudança fora dos files_modified do plano).

---

**Total deviations:** 2 decisões de honestidade/escopo. **Impacto no plano:** nenhum nos comportamentos entregues; todas as telas consomem dado real e honram as degradações. O único item a validar com a coordenação é o export de feriados (deliberadamente omitido para não criar fachada).

## Issues Encountered

- **`ExportMenu.buildHref` não aceita URL com query string** (faz `${url}?${query}`, duplicando o `?`). Resolvido passando `relatorio` como **param** (`params={{ ...periodo, relatorio: 'escritorio-virtual' }}`) em vez de embutir na URL base — caminho correto para o endpoint de tempo que serve dois sources.

## Next Phase Readiness

- **EP15 com os 11 indicadores navegáveis e exportáveis:** indicadores (15-13) + tempo/produtividade/quedas (15-14), todos com `<Chart>`/tabela e `<ExportMenu>` real.
- **15-15 (retenção):** sem impacto — as telas só consomem leitura; o pruning dos `ExportFile` segue independente.
- **Pendências da coordenação/SEDUR (degrada honesto, registrado):** (a) export próprio do CRUD de feriados, se desejado; (b) listas de opções de setor/analista nas telas de relatório, se a SEDUR quiser esse recorte; (c) lista oficial de feriados municipais de Salvador e layout/colunas oficiais dos relatórios SAPS.

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-16*
