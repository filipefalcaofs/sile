---
phase: 03-cadastro-empresarial
plan: 07
subsystem: ui
tags: [react, inertia, useHttp, useForm, data-table, portal, cnpj, empresas]

requires:
  - phase: 03-cadastro-empresarial
    plan: 02
    provides: Endpoint POST /portal/empresas/consultar-cnpj + shape snake_case do CnpjData::toArray (contrato do lookup)
  - phase: 03-cadastro-empresarial
    plan: 04
    provides: Rotas portal.empresas.index/create/store + shape das props do index e do create (cnpjLookupEnabled)
  - phase: 02-design-system
    provides: Componentes 2.4 (DataTable tipada, useServerTable, TableToolbar, PerPageSelect) e 2.1/2.2 (Card, Badge, EmptyState, Pagination, Alert, Input, Label, Button, PageHeader, TableAction)
provides:
  - Tela "Minhas empresas" (/portal/empresas) server-driven no padrão 2.4 com badges de origem e situação do vínculo
  - Página dedicada "Cadastrar empresa" (/portal/empresas/cadastrar) com lookup vivo de CNPJ via useHttp e degradação comunicada
  - Item "Minhas empresas" no grupo Serviços da sidebar do portal
affects: [03-08-detalhe-empresa, 03-09-verificacao]

tech-stack:
  added: []
  patterns:
    - "Request standalone Inertia v3: useHttp({ cnpj: '' }) + lookup.post(url, { onSuccess, onError }) + lookup.processing — JSON sem visita Inertia"
    - "Layout persistente por página: Page.layout = (page) => <PortalLayout>{page}</PortalLayout> (aplicado pelo fix 7dd1ab0)"
    - "EmptyState bifurcado por estado de busca: lista vazia (CTA cadastrar) × busca sem resultados (ajustar termo)"
    - "Máscara de CNPJ alfanumérica no cliente: guarda [A-Z0-9] (até 14), exibe XX.XXX.XXX/XXXX-XX — pronta para o CNPJ alfanumérico de julho/2026"

key-files:
  created:
    - resources/js/pages/portal/empresas/index.tsx
    - resources/js/pages/portal/empresas/cadastrar.tsx
  modified:
    - resources/js/layouts/portal-layout.tsx
    - tests/Feature/Companies/CompanyRegistrationTest.php

key-decisions:
  - "Trabalho já implementado por sessões anteriores (commits 2239efb, 41d76d8, 7dd1ab0): esta execução validou com evidência fresca em vez de duplicar (precedente do STATE.md)"
  - "Pendência herdada do 03-04 fechada: asserts ->component() adicionados quando as páginas passaram a existir em disco"
  - "Button não aceita href: ação primária envolvida em <Link> do Inertia (CardHeader e EmptyState)"

patterns-established:
  - "useHttp para endpoints JSON do portal: setData antes do post, onSuccess tipado com payload parcial, onError lendo message pt-BR do backend"
  - "setData funcional ((current) => ({ ...current, ...filled })) para merge atômico dos campos retornados pelo lookup"

requirements-completed: [HU-021, HU-023, HU-027]

duration: 5min (validação; implementação feita em sessões anteriores)
completed: 2026-06-12
---

# Phase 3 Plan 07: Telas Minhas Empresas e Cadastro com Lookup Summary

**Telas do portal para HU-027 (listagem "Minhas empresas" server-driven no padrão 2.4 com badges de origem/vínculo) e HU-021+HU-023 (página dedicada de cadastro com lookup vivo de CNPJ via useHttp, degradação comunicada quando o toggle está OFF) — implementadas em sessões anteriores e validadas nesta execução com evidência fresca contra todos os acceptance criteria.**

## Performance

- **Duration:** ~5 min (validação + fechamento de pendência; implementação prévia nos commits citados)
- **Completed:** 2026-06-12
- **Tasks:** 2 (ambas já implementadas e commitadas por sessões anteriores)
- **Files modified:** 4 (2 páginas criadas + sidebar + suíte de testes)

## Contexto da execução

As duas tasks deste plano já estavam implementadas e commitadas quando esta execução começou:

1. **Task 1 (sidebar + Minhas empresas)** — commit `2239efb` (`feat(03-07): sidebar + tela Minhas empresas (HU-027)`)
2. **Task 2 (cadastro com lookup)** — commit `41d76d8` (`feat(03-07): página de cadastro de empresa com lookup vivo de CNPJ`)
3. **Ajuste posterior** — commit `7dd1ab0` (`fix: layout persistente nas páginas e debounce do use-server-table`) trocou o wrapper inline por `Page.layout` (layout persistente do Inertia) nas duas páginas.

Seguindo o precedente do projeto (nota operacional do STATE.md), esta sessão validou o trabalho existente sem duplicar, fechou a pendência herdada do 03-04 e criou este SUMMARY.

## Evidência fresca coletada (2026-06-12)

- `npm run typecheck` — verde (tsc --noEmit sem erros).
- `npm run build` — verde (vite 8, 611 módulos, built in 719ms; chunks `empresas-*.js` e `cadastrar-*.js` gerados).
- `php artisan test --compact --filter='MyCompaniesTest|CnpjLookupTest|CompanyRegistrationTest'` — **30 testes verdes, 209 assertions** (29 no baseline + 1 adicionado nesta execução).
- Greps dos acceptance criteria, todos presentes:
  - Task 1: `"Minhas empresas"` no grupo Serviços do `portal-layout.tsx` (linha 22, primeiro item, ListIcon); `useServerTable` no `index.tsx` (linha 72); `EmptyState` com os DOIS textos — `'Nenhuma empresa cadastrada'` (lista vazia, com CTA) × `'Nenhuma empresa encontrada'` (busca sem resultados).
  - Task 2: `consultar-cnpj` (endpoint real, linha 121); `'Consulta automática indisponível — preencha os dados manualmente.'` (toggle OFF, hint do input); `useHttp` (import + uso); `A-Z0-9` (máscara alfanumérica, linha 79).
- `git status` — nenhum arquivo de `components/ui/*` nem de `pages/gestao/*` modificado por esta execução.
- `vendor/bin/pint --dirty --format agent` — passed.

## Componentes 2.4 efetivamente usados

Nenhum fallback 2.1 foi necessário — todos os componentes da fase 2.4 existiam no disco:

- **index.tsx:** `PageHeader` → `Card`/`CardHeader`/`CardContent` → `TableToolbar` (busca com placeholder "Buscar por razão social ou CNPJ...") → `DataTable<CompanyRow>` (5 colunas: empresa sortable, CNAE principal truncado `max-w-[28ch]`, origem com `Badge` info/light, vínculo com `Badge` success/light + role_label, ações com `TableAction` brand) → `Pagination` + `PerPageSelect`. Estado server-driven via `useServerTable` (busca/sort/per_page).
- **cadastrar.tsx:** `PageHeader` (breadcrumbs Meu painel → Minhas empresas) → 3 `Card`s (Identificação, Endereço, Contato) com `Input`/`Label` no padrão error/hint 2.1, `Alert variant="error"` para falha do lookup, `Button` outline/primary.

## API do request standalone (useHttp — Inertia v3)

```tsx
const lookup = useHttp({ cnpj: '' });
// ...
lookup.setData('cnpj', data.cnpj);
lookup.post('/portal/empresas/consultar-cnpj', {
    onSuccess: (response) => { /* merge dos campos snake_case não vazios via setData funcional */ },
    onError: (payload) => setLookupError(payload?.message ?? fallback),
});
// lookup.processing alimenta o estado 'Buscando...' do botão
```

- Sucesso preenche SOMENTE campos retornados não vazios (15 chaves snake_case do `CnpjData::toArray` [03-02]); o usuário revisa antes de salvar.
- Erro (404/422/503) exibe a `message` pt-BR do backend em `<Alert>` acima do form; o formulário continua editável (FA-03 — cadastro manual segue possível).
- Toggle OFF (`cnpjLookupEnabled === false`): botão `disabled` + hint de degradação no input — nunca falha silenciosa.
- Nenhum resultado simulado: sem resposta do endpoint, nenhum campo é preenchido.
- Submit do cadastro via `useForm.post('/portal/empresas')` (redirect do backend ao index com flash de sucesso).

## Estrutura final das páginas

- `resources/js/pages/portal/empresas/index.tsx` — tipos locais `CompanyRow`/`EmpresasIndexProps` espelhando o contrato do 03-04; `EmpresasIndex.layout = (page) => <PortalLayout>{page}</PortalLayout>`.
- `resources/js/pages/portal/empresas/cadastrar.tsx` — `CompanyForm` com os 16 campos do `StoreCompanyRequest`; helper `formatCnpj` (máscara alfanumérica); mesmo padrão de layout persistente.
- `resources/js/layouts/portal-layout.tsx` — "Minhas empresas" como primeiro item do grupo Serviços (antes de Procurações).

## Task Commits

Implementação (sessões anteriores):

1. **Task 1: Sidebar + tela Minhas empresas** - `2239efb` (feat)
2. **Task 2: Página de cadastro com lookup vivo** - `41d76d8` (feat)
3. **Ajuste: layout persistente (Page.layout)** - `7dd1ab0` (fix, fora do escopo formal do plano mas tocando as páginas)

Esta execução (validação):

4. **Pendência 03-04: assert de componente no GET create** - `91c85de` (test)

## Files Created/Modified

- `resources/js/pages/portal/empresas/index.tsx` - Listagem Minhas empresas server-driven (criado em 2239efb)
- `resources/js/pages/portal/empresas/cadastrar.tsx` - Página dedicada de cadastro com lookup (criado em 41d76d8)
- `resources/js/layouts/portal-layout.tsx` - Item de navegação no grupo Serviços (modificado em 2239efb)
- `tests/Feature/Companies/CompanyRegistrationTest.php` - +1 teste: componente e prop do GET create (modificado em 91c85de)

## Decisions Made

- **Validar em vez de reimplementar:** as tasks já estavam commitadas e atendiam todos os acceptance criteria — evidência fresca coletada e registrada acima (precedente do STATE.md para trabalho já feito).
- **Pendência herdada do 03-04 fechada:** `MyCompaniesTest` já verificava `->component('portal/empresas/index')` (veio com 2239efb); faltava a verificação do componente da página de cadastro — adicionado `test_pagina_de_cadastro_renderiza_componente_com_toggle_do_lookup` (GET `/portal/empresas/cadastrar` → `->component('portal/empresas/cadastrar')` + `has('cnpjLookupEnabled')`). Filtro rodado antes (29 verdes) e depois (30 verdes).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Assert de componente faltante no CompanyRegistrationTest**

- **Found during:** Validação dos acceptance criteria (pendência registrada no 03-04-SUMMARY)
- **Issue:** Os testes Inertia do cadastro não verificavam que o GET create renderiza a página `portal/empresas/cadastrar` — a checagem era impossível antes da página existir em disco
- **Fix:** Teste novo com `->component('portal/empresas/cadastrar')` e presença da prop `cnpjLookupEnabled`
- **Files modified:** tests/Feature/Companies/CompanyRegistrationTest.php
- **Verification:** Filtro da wave 30/30 verde; pint passed
- **Committed in:** 91c85de

---

**Total deviations:** 1 auto-fixed (fechamento de pendência herdada planejada). Sem scope creep.

## Issues Encountered

None — todos os acceptance criteria do plano já estavam atendidos pela implementação existente; nenhuma divergência real encontrada.

## User Setup Required

None - nenhuma configuração externa. O lookup usa o endpoint interno (provider BrasilAPI sem credenciais, [03-02]).

## Next Phase Readiness

- **03-08 (detalhe da empresa):** `TableAction` do index já aponta para `/portal/empresas/{id}`; o controller `show` e a página `detalhe.tsx` são escopo do 03-08.
- **03-09 (verificação):** fluxo cidadão completo navegável — sidebar → Minhas empresas → Cadastrar empresa → Buscar CNPJ → Salvar → volta à lista com flash; full-suite fica para o 03-09 conforme o plano.

---
*Phase: 03-cadastro-empresarial*
*Completed: 2026-06-12*

## Self-Check: PASSED

- As 2 páginas + sidebar existem no disco e batem com os commits citados (working tree limpo sobre eles).
- Commits `2239efb`, `41d76d8`, `7dd1ab0` e `91c85de` existem no histórico.
- Evidência fresca: typecheck/build verdes, 30 testes verdes (209 assertions), greps dos 9 acceptance criteria presentes.
