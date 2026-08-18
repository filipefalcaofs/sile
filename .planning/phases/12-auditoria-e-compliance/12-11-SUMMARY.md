---
phase: 12-auditoria-e-compliance
plan: 11
subsystem: ui
tags: [hu-149, hu-098, hu-100, hu-102, abuso, painel, efetividade, navegacao, command-search, cmd-k, gating, permissoes, inertia, react, server-driven, anti-fachada, ssr-safe]

# Dependency graph
requires:
  - phase: 12-auditoria-e-compliance
    provides: "12-09 — backend do abuso: Gestao\\AbusoController (index com lista filtrável + efetividade RN-005; confirmar/descartar com justificativa obrigatória), AbuseAlertResource, rotas gestao.abuso.index/confirmar/descartar sob permission:gerenciar-alertas-abuso"
  - phase: 12-auditoria-e-compliance
    provides: "12-10 — páginas gestao/auditoria/index (consultar-auditoria) e gestao/lgpd/index (monitorar-lgpd) já existentes e alcançáveis por rota; faltava a navegação apontar para elas"
  - phase: 10-analise-tecnica-sedur
    provides: "Design system (Card, Badge, DataTable, PerPageSelect, Pagination, KpiCard, Modal, TableAction, Button, Select, Input, Label) e padrões server-driven (resultados-expresso/index, auditoria/index, ficha-analise modal de justificativa)"
provides:
  - "Página gestao/abuso/index: painel humano de alertas (HU-149) — lista server-driven filtrável (regra/severidade/status/período) + paginada, indicador de efetividade (confirmados ÷ gerados, geral e por regra; taxa null sem base), e confirmar/descartar via modal com justificativa OBRIGATÓRIA (erros do backend exibidos)"
  - "Anti-fachada explícito na UI (CA-02): banner + nota no modal deixando claro que confirmar/descartar é revisão humana do ALERTA — nunca altera/pune o processo; malha fina ortogonal"
  - "app-sidebar: grupo 'Auditoria e compliance' (Trilha de auditoria, Conformidade LGPD, Alertas de abuso) na retaguarda (console), cada item gated por permissão; quem não tem não vê"
  - "command-search (Cmd+K): destinos das 3 superfícies gated pelas mesmas permissões; atalho/modal disponível para quem tem qualquer acesso relevante"
affects:
  - "12-12 (smoke navegável): exercita o painel de abuso (lista/efetividade/resolução com justificativa) e a navegação/Cmd+K das 3 superfícies, validando o render e os gates por permissão ponta a ponta"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Página de lista+ação server-driven sem useServerTable (espelha auditoria/index): filtros via router.get com preserveState/preserveScroll/replace; selects/datas disparam visita imediata; o cliente nunca recalcula"
    - "Resolução auditada via useForm.post (Inertia): justificativa obrigatória validada no backend (ResolverAbuseAlertRequest) com errors.justification exibido — sem disable por conteúdo, o erro real do backend é exercido e mostrado (anti-fachada)"
    - "Navegação gated por permissão dentro do app-sidebar (variant console): grupo de compliance derivado de auth.permissions e anexado aos groups do layout; filtragem de item.visible existente esconde quem não tem a permissão"
    - "Cmd+K com destinos estáticos gated: tabela DESTINOS [{label,grupo,href,permissao}] filtrada por auth.permissions; gating do atalho ampliado para qualquer acesso relevante (consultar-solicitacoes OU um dos destinos)"
    - "SSR-safe (lição Fase 9): Array.isArray + defaults antes de map/includes em alertas.data, efetividade.por_regra, ruleKeyOptions, severityOptions, statusOptions, auth.permissions; evidência minimizada por guard de tipo"

key-files:
  created:
    - "resources/js/pages/gestao/abuso/index.tsx"
  modified:
    - "resources/js/components/app/app-sidebar.tsx"
    - "resources/js/components/app/command-search.tsx"

key-decisions:
  - "Navegação adicionada DENTRO do app-sidebar (não no gestao-layout, fora do escopo do plano): grupo de compliance gated por auth.permissions, só na variante console — não vaza para o portal do cidadão. Mantém o escopo de 3 arquivos do files_modified"
  - "abuso/index usa router.get direto (não useServerTable): o AbusoController não lê sort/direction/search; injetá-los poluiria a URL — mesmo motivo da decisão de 12-10 para a trilha"
  - "Filtros são selects/datas (visita imediata); limpeza via botão 'Limpar filtros' (o Select do DS tem placeholder desabilitado, então 'todas/todos' por item não é selecionável — padrão da trilha 12-10)"
  - "Modal de resolução não desabilita o submit por conteúdo vazio: deixa o backend validar e exibe errors.justification — garante que o erro REAL de validação aparece (CA RN-003 anti-fachada)"
  - "command-search: gating ampliado para temAcesso = podeConsultar || destinos>0, para o auditor/admin sem consultar-solicitacoes ainda alcançar as superfícies; a busca de processos continua só com consultar-solicitacoes"
  - "Estado vazio honesto do painel: sem alertas, explica que a detecção nasce desligada (features.deteccao_abuso) e a ausência pode ser desativação OU nada sinalizado — não afirma um estado que a tela não recebe como prop"

patterns-established:
  - "Painel humano de triagem (HU-149): banner anti-fachada + efetividade (KPIs + tabela por regra) + lista com ação de resolução auditada em modal — base de UI para revisão de alertas"
  - "Grupo de navegação opcional gated por permissão no app-sidebar (console-only) sem tocar o layout — extensível para futuras superfícies de retaguarda"

# Metrics
duration: ~22min
completed: 2026-06-15
---

# Phase 12 Plan 11: Painel de abuso e navegação das superfícies de auditoria/compliance Summary

**Fechamento da camada de UI da Fase 12: a página `gestao/abuso/index` (HU-149) — lista server-driven filtrável + indicador de efetividade (confirmados ÷ gerados, RN-005) + confirmar/descartar em modal com justificativa OBRIGATÓRIA, deixando explícito que é revisão humana do alerta e NUNCA punição do processo (CA-02) — e a forma de CHEGAR nas três superfícies: o `app-sidebar` ganha o grupo "Auditoria e compliance" (Trilha, LGPD, Abuso) e o `command-search` (Cmd+K) ganha esses destinos, cada um gated pela permissão correta (quem não tem, não vê). Tudo compilando limpo e com AbusoPainelTest (12-09) verde.**

## Performance

- **Duration:** ~22 min
- **Completed:** 2026-06-15
- **Tasks:** 2
- **Files:** 1 criado, 2 modificados

## Accomplishments

- **HU-149 — painel de abuso (`gestao/abuso/index`):** consome as props de 12-09 (`alertas` paginado, `efetividade`, `filtros`, `ruleKeyOptions`/`severityOptions`/`statusOptions`, `perPageOptions`). Filtros (regra/severidade/status/período) disparam visita Inertia server-driven; tabela paginada com regra+severidade, status, processo (link) + selo "Em malha fina", evidência minimizada, detectado em e resolução. Cartão de **efetividade** com KPIs (gerados/confirmados/descartados/taxa) + tabela por regra; **taxa null vira travessão** (RN-005, nunca número inventado).
- **Resolução auditada (RN-003):** confirmar/descartar abrem um modal com justificativa **obrigatória** via `useForm.post` para `gestao.abuso.confirmar`/`gestao.abuso.descartar`; os erros de validação do backend (`ResolverAbuseAlertRequest`) são exibidos abaixo do campo.
- **Anti-fachada explícito (CA-02):** banner no topo + nota dentro do modal deixam claro que confirmar/descartar é a triagem humana do **alerta** — não altera, não indefere e não pune o processo, e não mexe na malha fina (ortogonal). Estado vazio honesto sobre a detecção nascer desligada.
- **Navegação gated (HU-098/100/102/149):** `app-sidebar` ganha o grupo "Auditoria e compliance" (só na variante console) com Trilha de auditoria (`consultar-auditoria`), Conformidade LGPD (`monitorar-lgpd`) e Alertas de abuso (`gerenciar-alertas-abuso`) — cada item visível só com a permissão. `command-search` (Cmd+K) ganha os mesmos 3 destinos com os mesmos gates; o atalho passa a aparecer para quem tem qualquer acesso relevante.

## Como as superfícies ficam alcançáveis (insumo de 12-12)

| Superfície | Rota (href) | Permissão (gate) | Onde |
|---|---|---|---|
| Trilha de auditoria | `/gestao/auditoria` | `consultar-auditoria` | sidebar (grupo "Auditoria e compliance") + Cmd+K |
| Conformidade LGPD | `/gestao/lgpd` | `monitorar-lgpd` | sidebar + Cmd+K |
| Alertas de abuso | `/gestao/abuso` | `gerenciar-alertas-abuso` | sidebar + Cmd+K + página própria |

- Sidebar: o grupo só aparece no console (gestão); itens sem a permissão são filtrados (`visible` + filtro de grupo vazio já existente). Não vaza para o portal do cidadão.
- Cmd+K: `DESTINOS` filtrado por `auth.permissions`; busca de processos continua exclusiva de `consultar-solicitacoes`.

## Props consumidas por `gestao/abuso/index` (de 12-09)

- `alertas`: paginator `{ data: AbuseAlertResource[], links, from, to, total }` (withQueryString — paginação preserva filtros).
- `efetividade`: `{ geral, por_regra[] }` com `taxa: number|null`.
- `filtros`: `{ rule_key, severity, status, data_de, data_ate, per_page }` (eco para a UI).
- `perPageOptions`, `ruleKeyOptions` (distinct real do ledger), `severityOptions`, `statusOptions`.
- Item: `id, rule_key, severity{value,label}, status{value,label}, detected_at, window{start,end}, evidence, processo{id,protocol_number}|null, encaminhado_malha_fina, resolucao{resolved_by,resolved_at,justification}|null`.

## Task Commits

1. **Task 1: página gestao/abuso/index (lista + efetividade + confirmar/descartar)** — `d182474` (feat)
2. **Task 2: navegação (app-sidebar) + Cmd+K (command-search) das 3 superfícies, gated** — `bcb2bda` (feat)

## Files Created/Modified

- `resources/js/pages/gestao/abuso/index.tsx` — painel de alertas (lista server-driven + filtros + efetividade + resolução em modal com justificativa obrigatória; anti-fachada; SSR-safe).
- `resources/js/components/app/app-sidebar.tsx` — grupo "Auditoria e compliance" gated por permissão, só no console; lê `auth.permissions` via `usePage<SharedProps>()` com guard SSR-safe.
- `resources/js/components/app/command-search.tsx` — destinos das 3 superfícies (gated) no Cmd+K; gating do atalho ampliado para qualquer acesso relevante; busca de processos preservada.

## Decisions Made

- Entradas de navegação adicionadas **dentro do app-sidebar** (não no `gestao-layout`, fora do `files_modified`): grupo de compliance derivado de `auth.permissions`, só na variante console — respeita o escopo de 3 arquivos e não vaza para o portal. O grep de aceite do plano (`contains: "auditoria"` em app-sidebar.tsx) confirma a intenção.
- `abuso/index` usa `router.get` direto (não `useServerTable`): o controller não lê sort/direction/search; igual à decisão de 12-10 para a trilha.
- Modal de resolução não desabilita o submit por conteúdo: deixa o backend validar e exibe o erro real (anti-fachada de RN-003).
- `command-search` gated por `temAcesso = podeConsultar || destinos>0` — o auditor/admin sem `consultar-solicitacoes` ainda alcança as superfícies; a busca de processos segue exclusiva de `consultar-solicitacoes`.

## Deviations from Plan

None — plano executado exatamente como escrito. Escopo respeitado: SOMENTE os 3 arquivos do `files_modified` (abuso/index.tsx, app-sidebar.tsx, command-search.tsx), `git add` por pathspec (nunca `-A`/`.`). Nenhum toque em `routes/gestao.php`, controllers, `gestao-layout.tsx`, STATE.md ou ROADMAP.md. Zero dependência nova; só frontend.

Nota de arquitetura (não é desvio): a navegação real do console é montada em `gestao-layout.tsx` e passada ao `app-sidebar` via props. Como o plano restringe a edição ao `app-sidebar.tsx`, o grupo de compliance foi anexado lá (gated por permissão, console-only) em vez de no layout. É a leitura fiel do `files_modified` + dos greps de aceite; o resultado é idêntico para o usuário (itens gated, sem duplicação, sem vazamento ao portal).

## Issues Encountered

- **O `Select` do design system tem o `<option value="">` placeholder desabilitado** — não dá para selecionar "todas/todos" por filtro depois de escolher um valor. Resolução: limpeza via botão "Limpar filtros" (mesmo padrão da trilha 12-10), sem segunda opção vazia conflitante.

## Verificação (evidência fresca)

- `npm run typecheck` (`tsc --noEmit`): **exit 0** (sem erro de tipos; `import type` para SharedProps/PaginationLink/ColumnDef/ReactNode).
- `npm run build` (vite): **exit 0**; chunk `abuso-*.js` (~13.2 kB) emitido no manifest; `app-sidebar`/`gestao-layout` recompilados sem erro.
- `php artisan test --compact --filter=AbusoPainelTest`: **15 passed, 93 assertions** (backend de 12-09 segue verde — frontend não tocou o backend).
- Greps de aceite: `efetividade`/`confirmar`/`descartar` presentes em `abuso/index.tsx`; `gestao/auditoria`+`gestao/lgpd`+`gestao/abuso` e `permissions.includes(consultar-auditoria/monitorar-lgpd/gerenciar-alertas-abuso)` presentes em `app-sidebar.tsx` e `command-search.tsx`.
- `ReadLints` nos 3 arquivos: **0 erros**.

## User Setup Required

None — frontend puro consumindo backend já entregue (12-09/12-10). Sem configuração de serviço externo.

## Next Phase Readiness

- **12-12 (smoke navegável):** o painel de abuso e a navegação/Cmd+K das 3 superfícies estão prontos para o smoke. Para exercitar lista + efetividade ponta a ponta, semear `abuse_alerts` variados (aberto/confirmado/descartado por regra) — o motor processa dados reais; o seed só muda a carga. O render fino (gates por papel: gestor vê abuso/auditoria, admin vê também LGPD) é validado lá.
- Observação para o smoke: a detecção de abuso nasce desligada (`features.deteccao_abuso`); o painel exibe estado vazio honesto até haver alertas reais (ou seed de dev).
- Sem blockers. ZERO dependência nova; SÓ frontend.

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*
