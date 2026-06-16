---
phase: 15-relatorios-e-indicadores
plan: 12
subsystem: ui
tags: [hu-122, hu-131, echarts, inertia, react, ssr, tree-shaking, dark-mode, export, data-table, rn-004]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-01)
    provides: "dependência echarts ^6.1 instalada + parâmetro relatorios.export.formatos_habilitados (csv/xlsx/pdf)"
  - phase: 15-relatorios-e-indicadores (15-09)
    provides: "RelatorioController serve as telas Inertia e responde a ?formato= delegando ao ReportExporter (alvo das URLs montadas pelo ExportMenu)"
  - phase: 04-georreferenciamento
    provides: "MapaSection/MapImovel — padrão client-only SSR-safe (lazy + mounted + skeleton) espelhado pelo wrapper de chart"
provides:
  - "<Chart option={...} className?> — wrapper de gráfico ECharts client-only SSR-safe, tema resolvido internamente pela classe dark do <html>"
  - "echarts-core.ts — registro tree-shakeable (echarts.use) + tipo EChartsOption composto pelos módulos registrados"
  - "<ExportMenu url params formatos? label? className?> — dropdown CSV/XLSX/PDF que monta {url}?formato=...&filtros atuais (RN-004)"
  - "useServerTable.currentParams — snapshot de query (sort/direction/per_page/search/filtros) reusável pela exportação"
affects: [15-13-dashboard, 15-14-telas-relatorios]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Wrapper client-only SSR-safe em 3 camadas: core tree-shake (echarts.use) + impl que toca o DOM (lazy-only) + guarda SSR (lazy + mounted + skeleton) — espelha MapaSection/MapImovel"
    - "Tema do gráfico segue o DS: lê document.documentElement.classList.contains('dark') e observa a classe do <html> com MutationObserver, trocando via setTheme (ECharts 6) SEM recriar a instância (Pitfall 5)"
    - "Tipo de option restrito (ComposeOption) aos módulos registrados — bundle enxuto e autocompletar correto"
    - "Export transversal: componente único (ExportMenu) monta a URL ?formato= a partir do currentParams do useServerTable; âncora nativa (download real, não visita Inertia)"

key-files:
  created:
    - resources/js/components/ui/chart/echarts-core.ts
    - resources/js/components/ui/chart/chart-impl.tsx
    - resources/js/components/ui/chart/chart.tsx
    - resources/js/components/ui/data-table/export-menu.tsx
  modified:
    - resources/js/components/ui/data-table/use-server-table.ts

key-decisions:
  - "chart.tsx NÃO importa echarts (nem o pacote cheio nem echarts/core): só import type da prop e dynamic import('./chart-impl') via lazy — garante caminho do servidor sem echarts (acceptance + SSR-safe)"
  - "Tema resolvido DENTRO do impl client-only (não como prop): <Chart> expõe só option; o impl lê a classe dark e observa mudanças (MutationObserver) — bate com o ThemeProvider do projeto, que alterna a classe dark no <html>"
  - "Troca de tema via setTheme('dark'|'default') na instância viva + reaplica setOption, sem dispose/init (must-have: sem recriar a instância). O tema dark embutido é registrado pela cadeia do echarts/core"
  - "buildParams extraído como fonte única do snapshot de query usado por visit e por currentParams (sem drift entre navegação e export)"
  - "ExportMenu usa âncora nativa <a href> (não DropdownItem, que renderiza Link do Inertia) — download real, não visita XHR do Inertia (mesmo padrão de auditoria/processos)"
  - "params do ExportMenu são coeridos a string no boundary da URLSearchParams (per_page é number); currentParams permanece com o MESMO shape de visit (string | number)"

# Metrics
duration: ~12 min
completed: 2026-06-16
---

# Phase 15 Plan 12: UI base dos relatórios (wrapper ECharts + ExportMenu) Summary

**Wrapper de gráfico Apache ECharts client-only e SSR-safe (`ui/chart/`) com import tree-shakeable (`echarts/core` + `echarts.use`) e tema claro/escuro sincronizado à classe `dark` do `<html>` sem recriar a instância, mais o `<ExportMenu>` reusável (`ui/data-table/`) que dá a qualquer tela com `useServerTable`+`DataTable` o dropdown CSV/XLSX/PDF apontando para `{url}?formato=` com os filtros atuais — base pronta para o dashboard (15-13) e as páginas de relatório (15-14) sem reimplementar chart/export.**

## Performance

- **Duration:** ~12 min
- **Completed:** 2026-06-16
- **Tasks:** 2 (cada uma com commit atômico)
- **Files created:** 4; **modified:** 1 (`use-server-table.ts`)

## Accomplishments

- **Gráfico SSR-safe (HU-122):** `<Chart>` monta o ECharts apenas no cliente (lazy + mounted + skeleton de mesma altura), sem quebrar o SSR do Inertia v3; o `echarts` nunca entra no caminho do servidor.
- **Bundle enxuto (tree-shake):** `echarts-core.ts` registra só os módulos usados via `echarts.use([...])`; o tipo `EChartsOption` é composto (`ComposeOption`) exatamente desses módulos.
- **Tema do DS sem recriar a instância (Pitfall 5):** o impl lê `classList.contains('dark')` e observa a classe do `<html>` com `MutationObserver`, aplicando `setTheme('dark'|'default')` + `setOption` na instância viva.
- **Export transversal (HU-131/RN-004):** `<ExportMenu>` monta `{url}?formato=csv|xlsx|pdf&...currentParams` por âncora nativa (download real); `useServerTable` passou a expor `currentParams` (mesmo snapshot de `visit`).

## Task Commits

1. **Task 1: Wrapper ECharts client-only SSR-safe (core tree-shake + impl + guarda) com tema do DS** — `c432e94` (feat)
2. **Task 2: `<ExportMenu>` (CSV/XLSX/PDF do conjunto filtrado) + exposição dos params do useServerTable + smoke build** — `9a69228` (feat)

**Plan metadata:** este SUMMARY + STATE.md (docs: complete plan)

## API final dos componentes

### `<Chart>` (`resources/js/components/ui/chart/chart.tsx`)

```tsx
import { Chart } from '@/components/ui/chart/chart';
import type { EChartsOption } from '@/components/ui/chart/echarts-core';

<Chart option={option} className="h-80 w-full" />
```

- **Props:** `option: EChartsOption` (obrigatório) + `className?: string` (define a altura; default `h-80 w-full`, usada também no skeleton para não saltar o layout).
- **Tema:** resolvido internamente — NÃO recebe prop `theme`. Acompanha a classe `dark` do `<html>` (o `ThemeProvider` do projeto adiciona/remove `dark` no `documentElement`).
- **SSR:** `chart.tsx` só faz `import type` da prop e `lazy(() => import('./chart-impl'))`; o `echarts` é carregado apenas no chunk assíncrono do cliente.

### `<ExportMenu>` (`resources/js/components/ui/data-table/export-menu.tsx`)

```tsx
import { ExportMenu } from '@/components/ui/data-table/export-menu';

const { currentParams } = useServerTable({ url, initialSort, initialPerPage });

<ExportMenu url={indexUrl} params={currentParams} formatos={['csv', 'xlsx', 'pdf']} />
```

- **Props:** `url: string` (rota do índice que responde a `?formato=`), `params: ServerTableParams` (o `currentParams`), `formatos?: ExportFormat[]` (default `['csv','xlsx','pdf']` — a tela deve passar `relatorios.export.formatos_habilitados`), `label?: string` (default "Exportar"), `className?: string`.
- **Comportamento:** cada item é uma âncora nativa para `${url}?${URLSearchParams({...params, formato})}` — download real (não visita Inertia). Acima do limiar, o backend (`ReportExporter`) processa em segundo plano e a notificação leva ao arquivo. Rótulos pt-BR ("Exportar CSV/Excel/PDF"); `formatos=[]` → não renderiza nada.
- **Acessibilidade:** botão com `aria-haspopup="menu"`/`aria-expanded`; lista com `role="menu"`/`role="menuitem"`; usa o `Dropdown` do DS (fecha ao clicar fora).

## Módulos ECharts registrados em `echarts-core.ts`

`echarts.use([...])` com import tree-shakeable de `echarts/core`:

- **Charts:** `BarChart`, `LineChart`, `PieChart`
- **Components:** `GridComponent`, `TooltipComponent`, `LegendComponent`, `TitleComponent`, `DatasetComponent`
- **Renderer:** `CanvasRenderer`

O tipo `EChartsOption` é `ComposeOption<BarSeriesOption | LineSeriesOption | PieSeriesOption | GridComponentOption | TooltipComponentOption | LegendComponentOption | TitleComponentOption | DatasetComponentOption>`. Novos tipos de gráfico em 15-13/15-14 entram aqui (registrar o `*Chart` e somar o `*SeriesOption` ao tipo).

## Como o tema dark/light é resolvido

Fonte da verdade = classe `dark` no `<html>`, controlada pelo `ThemeProvider` (`resources/js/contexts/theme-context.tsx`, que faz `document.documentElement.classList.add/remove('dark')`). O `chart-impl`:

1. Na montagem (client-only), `echarts.init(el, isDarkMode() ? 'dark' : null)` — `isDarkMode()` = `document.documentElement.classList.contains('dark')`.
2. Um `MutationObserver` em `document.documentElement` (filtro `attributes: ['class']`) detecta a troca de tema e chama `chart.setTheme('dark'|'default')` + `chart.setOption(option)` — sem `dispose`/`init` (instância preservada).
3. Um `ResizeObserver` mantém o gráfico responsivo; o cleanup desconecta os dois observers e faz `chart.dispose()`.

O tema `dark` embutido do ECharts é registrado ao importar `echarts/core` (cadeia `core` → `lib/export/core.js` → `lib/core/echarts.js`, `registerTheme('dark', ...)`), então `init`/`setTheme` com `'dark'` aplicam estilo real (sem fachada).

## Como o `useServerTable` expõe os params

- Extraído `buildParams(state)` (módulo) como **fonte única** do snapshot de query: `sort`/`direction`/`per_page` + `search` (quando preenchida) + filtros não-vazios. O `visit` (navegação) passou a usar essa função — comportamento de navegação inalterado.
- Adicionado `currentParams` ao retorno do hook, via `useMemo(() => buildParams({ search, sort, perPage, filters }), [...])` — **o MESMO objeto** montado em `visit`, exposto para o `ExportMenu` montar a URL de export sem reimplementar a serialização dos filtros (evita drift entre tela e export).
- Tipo exportado `ServerTableParams = Record<string, string | number>` (consumido pelo `ExportMenu`); a coerção a string acontece no boundary da `URLSearchParams` (porque `per_page` é `number`).

## Decisions Made

- **`chart.tsx` sem qualquer import de `echarts`** (nem o pacote cheio, nem `echarts/core`): só `import type { ChartImplProps }` (erasado em runtime) e `lazy(() => import('./chart-impl'))`. Garante o acceptance `! from 'echarts'` E o SSR-safe (o servidor nunca avalia o módulo do ECharts).
- **Tema interno, não por prop:** alinha com o `<Chart option={...} />` do plano e evita que cada tela do dashboard tenha que descobrir/propagar o tema — o impl é a única fonte que lê o DS.
- **`setTheme` em vez de recriar:** honra o must-have "trocando claro/escuro sem recriar a instância"; ECharts 6.1 suporta `setTheme` dinâmico e o tema `dark` vem registrado pelo core.
- **Âncora nativa no ExportMenu:** o `DropdownItem` do DS renderiza `Link` do Inertia (visita XHR), que quebraria um download binário; por isso o ExportMenu usa `<a href>` próprio com o estilo do DS (mesmo padrão dos exports de `auditoria`/`processos`).

## Deviations from Plan

### Coordenação (staging seletivo — sem mudança de escopo)

**1. [Coordenação] Staging seletivo preservando o trabalho paralelo da Fase 14**
- **Contexto:** o working tree mantém o trabalho NÃO commitado do usuário (Fase 14 IA/e-mail + sidebar/vitest + `RolesAndPermissionsSeeder`/`bootstrap/providers.php`/`package*.json`/`routes/gestao.php`).
- **Resolução:** cada commit recebeu APENAS os 5 arquivos do 15-12 (`ui/chart/*`, `ui/data-table/export-menu.tsx`, `ui/data-table/use-server-table.ts`). Nada da Fase 14 foi tocado/staged/commitado. `vitest.config.ts`/`package.json` do usuário intactos; nenhum teste JS adicionado (o plano não inclui testes; verificação é grep + typecheck + build).

**Total deviations:** 1 coordenação. **Impacto no plano:** nenhum (escopo intacto; trabalho paralelo do usuário preservado).

## Issues Encountered

- **`from 'echarts'` no exemplo da pesquisa vs. acceptance:** o exemplo do 15-RESEARCH importava `import type { EChartsOption } from 'echarts'` em `chart.tsx`, o que violaria o acceptance `! grep "from 'echarts'"`. Resolvido definindo `EChartsOption` em `echarts-core.ts` (via `ComposeOption`) e importando a prop em `chart.tsx` apenas como `import type { ChartImplProps } from './chart-impl'` — type-only, erasado em runtime, sem `from 'echarts'` e sem puxar o ECharts para o caminho do servidor.

## Next Phase Readiness

- **15-13 (dashboard) e 15-14 (telas de relatório):** podem montar gráficos com `<Chart option={...} />` (registrar novos `*Chart`/`*SeriesOption` em `echarts-core` se precisarem de outros tipos) e adicionar `<ExportMenu url={...} params={currentParams} formatos={formatosHabilitados} />` em qualquer tela com `useServerTable`+`DataTable`.
- **Verificação fresca:** `npx tsc --noEmit` exit 0 e `npm run build` exit 0 (Dimensão 6 — SSR/Front: o wrapper client-only não quebra o build); critérios de aceite (grep) das duas tasks satisfeitos.
- **Sem pendências/bloqueios externos** neste plano (fundação de frontend sobre libs já instaladas).

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-16*
