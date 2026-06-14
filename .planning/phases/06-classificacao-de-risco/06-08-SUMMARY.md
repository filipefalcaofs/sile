---
phase: 06-classificacao-de-risco
plan: 08
subsystem: ui
tags: [risco, console-sedur, inertia-react, datatable, quatro-olhos, condicionante-pergunta, dark-mode, navegacao-por-permissao]

# Dependency graph
requires:
  - phase: 06-06-mantenedores-risco-condicionantes
    provides: "Rotas gestao.risco.* + props Inertia (gestao/risco/index e gestao/risco/condicionantes) + payload de publicação (author_id 4-olhos) + permissões consultar-risco/manter-risco"
  - phase: 02.4-template-listagens
    provides: "Biblioteca de listagem do console (PageHeader, Card, DataTable, useServerTable, TableToolbar, PerPageSelect, Pagination, EmptyState, KpiCard, TableAction, ConfirmDialog, Modal) + identidade Console SEDUR (sidebar escura)"
  - phase: 02-administracao-base
    provides: "Padrão de CRUD do console (CNAEs como listagem-modelo 2.4) + flash.status/flash.error via gestao-layout"
provides:
  - "Página gestao/risco/index: consulta da tabela de risco municipal vigente (DataTable busca/filtro/paginação) com municipal × sanitária separadas e ação de publicar nova versão (4-olhos comunicado) — HU-052/HU-053/HU-020"
  - "Página gestao/risco/condicionantes: CRUD das condicionantes-pergunta (Modal criar/editar + ConfirmDialog remover) com preview do efeito de reclassificação — HU-019"
  - "Itens de navegação 'Classificação de risco' (consultar-risco) e 'Condicionantes' (manter-risco) no grupo Cadastros do console"
  - "ShieldIcon (SVG inline, sem dependência) em components/icons"
affects: [07-consulta-previa]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Listagem de risco espelha a listagem-modelo CNAEs (PageHeader → Card(CardHeader com ação) → CardContent(TableToolbar → chips → DataTable → Pagination)), variante console density=compact"
    - "Dimensões municipal × sanitária separadas em painéis distintos (RN-009): a tela NÃO fabrica nível sanitário por CNAE (o endpoint 06-06 só expõe municipal por linha); a dimensão sanitária aparece como painel próprio + link para condicionantes"
    - "Quatro olhos na UI: ID do autor coletado em campo distinto, com guarda client-side (autor ≠ publicador) espelhando a regra do backend; bloqueio comunicado por flash.error/Alert (nunca silencioso)"
    - "Regra de reclassificação opcional via Switch (mecanismo DI) com preview textual do efeito ('Quando a resposta for Sim, o risco é reclassificado para Alto Risco')"
    - "Tabelas server-driven sem colunas sortable quando o backend não aceita sort (ordena fixo) — evita UI morta"

key-files:
  created:
    - resources/js/pages/gestao/risco/index.tsx
    - resources/js/pages/gestao/risco/condicionantes.tsx
  modified:
    - resources/js/layouts/gestao-layout.tsx
    - resources/js/components/icons/index.tsx
    - tests/Feature/Risco/RiscoConsultaTest.php
    - tests/Feature/Risco/RiscoCondicionanteMaintenanceTest.php

key-decisions:
  - "A dimensão sanitária NÃO é fabricada por CNAE: o RiscoController@index só expõe risco_municipal por linha (a dimensão sanitária é governada por condicionantes-pergunta). Para honrar a regra anti-fachada, a tela separa as dimensões em painéis distintos (municipal: Decreto 32.636/2020; sanitária: VISA + link para condicionantes) em vez de inventar um badge sanitário por linha. A 'versão vigente' por linha (idêntica em todas) é mostrada uma vez no painel municipal."
  - "O autor da nova versão (quatro olhos) é coletado por ID de usuário em campo numérico: o backend 06-06 não expõe lista de usuários como prop e adicioná-la seria scope creep de backend (proibido pelas constraints). Guarda client-side (autor ≠ publicador autenticado) + validação/erro do servidor."
  - "Colunas não-sortable: o RiscoController ordena fixo (cnae_code / id) e ignora sort — não renderizar colunas clicáveis evita controle morto. Busca e filtro por nível (index) seguem server-driven via useServerTable."
  - "Item de navegação 'Condicionantes' gated por manter-risco (conforme Task 1 do plano): é tela de manutenção; o analista (consultar-risco) acessa a consulta de condicionantes pela rota, mas o atalho de navegação é do mantenedor."
  - "ShieldIcon adicionado como SVG inline (escudo) para 'Classificação de risco'; 'Condicionantes' reusa ListIcon — zero dependência nova (sancionado pelo plano)."

patterns-established:
  - "Padrão de tela de dado versionado no console: painéis de dimensão com versão vigente (Badge) + tabela server-driven da dimensão + ação de publicação versionada em Modal com 4-olhos comunicado"
  - "Preview de regra (efeito de reclassificação) torna mecanismos parametrizáveis tangíveis ao admin antes de salvar"

# Metrics
duration: ~30min
completed: 2026-06-14
---

# Phase 6 Plan 08: UI de Risco no Console SEDUR Summary

**O console SEDUR ganha as telas reais da classificação de risco: analista/gestor consultam a tabela de risco municipal vigente (DataTable com busca, filtro por nível e resumo por nível), com as dimensões municipal (Decreto 32.636/2020) e sanitária (VISA) claramente separadas; o administrador publica uma nova versão pela interface com os quatro olhos comunicados e verificados; e mantém as condicionantes-pergunta (criar/editar/remover) com preview do efeito de reclassificação. Navegação por permissão, dark mode e mobile no padrão Console SEDUR — consumindo os endpoints/props reais do 06-06, sem mock.**

## Performance

- **Duration:** ~30 min
- **Completed:** 2026-06-14
- **Tasks:** 3 + ativação de testes
- **Files created:** 2 | **modified:** 4

## Accomplishments

- `gestao/risco/index.tsx` (HU-052/HU-053/HU-020): consulta da tabela de risco municipal vigente espelhando a listagem-modelo CNAEs (busca por código/denominação, filtro por nível com chip, paginação parametrizada), dois painéis de dimensão separados (municipal × sanitária — RN-009), KPIs de resumo por nível e Modal de publicação versionada com quatro olhos comunicado/verificado.
- `gestao/risco/condicionantes.tsx` (HU-019): CRUD das condicionantes-pergunta (Modal criar/editar com regra de reclassificação opcional e preview do efeito; remoção via ConfirmDialog com aviso de impacto; bloqueio comunicado quando não há versão sanitária vigente).
- Navegação por permissão: itens 'Classificação de risco' (consultar-risco) e 'Condicionantes' (manter-risco) no grupo Cadastros; `ShieldIcon` inline.
- Verificação: `npm run typecheck` e `npm run build` verdes; chunks `risco-*.js` e `condicionantes-*.js` emitidos; suíte SQLite **515/515 verde** (sem regressão); `--filter=Risco` **56/56** com `->component()` validado em disco.

## Task Commits

1. **Task 1: Navegação de risco + ShieldIcon** - `842c70d` (feat)
2. **Task 2: Consulta da tabela de risco (index.tsx)** - `1589b3c` (feat)
3. **Task 3: Manutenção de condicionantes (condicionantes.tsx)** - `b11c092` (feat)
4. **Ativação dos testes (component em disco)** - `f03e5a9` (test)

**Plan metadata:** este SUMMARY (docs).

## Files Created/Modified

- `resources/js/pages/gestao/risco/index.tsx` - Consulta da tabela municipal vigente + publicação versionada 4-olhos (HU-052/HU-053/HU-020).
- `resources/js/pages/gestao/risco/condicionantes.tsx` - CRUD das condicionantes-pergunta com preview do efeito (HU-019).
- `resources/js/layouts/gestao-layout.tsx` - Itens 'Classificação de risco' e 'Condicionantes' por permissão.
- `resources/js/components/icons/index.tsx` - `ShieldIcon` (SVG inline).
- `tests/Feature/Risco/RiscoConsultaTest.php` - `->component('gestao/risco/index')` (validação em disco).
- `tests/Feature/Risco/RiscoCondicionanteMaintenanceTest.php` - `->component('gestao/risco/condicionantes')` (validação em disco).

## Decisions Made

- **Dimensão sanitária não fabricada por CNAE (anti-fachada):** o endpoint 06-06 (`RiscoController@index`) só expõe `risco_municipal` por linha — a dimensão sanitária é governada por condicionantes-pergunta. Em vez de inventar um badge sanitário por linha (fachada), a tela separa as dimensões em painéis distintos: municipal (Decreto 32.636/2020, tabela por CNAE) e sanitária (VISA, versão vigente + link para a manutenção de condicionantes). A "versão vigente" — idêntica para todas as linhas — é mostrada uma vez no painel, não repetida por linha.
- **Autor (quatro olhos) por ID de usuário:** o backend 06-06 não fornece lista de usuários como prop; um picker exigiria mudança de backend (scope creep proibido). O campo coleta o ID do autor, com guarda client-side (autor ≠ publicador autenticado, ID exibido) reforçando a regra que o servidor já valida e comunica via `flash.error`.
- **Colunas não-sortable:** o controller ordena fixo e ignora `sort` — colunas clicáveis seriam UI morta. Mantidas busca + filtro por nível (server-driven).
- **'Condicionantes' gated por manter-risco** conforme a Task 1 do plano (tela de manutenção); a consulta de condicionantes permanece acessível por rota ao analista (consultar-risco).

## Deviations from Plan

### Ajustes necessários (sem scope creep)

**1. [Rule 3 - Blocking] Caminho do arquivo de ícones: `.ts` → `.tsx`**
- **Found during:** Task 1.
- **Issue:** `files_modified` lista `resources/js/components/icons/index.ts`, mas o arquivo real do projeto é `index.tsx` (componentes React/JSX). O `.ts` não existe.
- **Fix:** Editado o arquivo real `index.tsx` (adicionado `ShieldIcon`). Sem mudança de comportamento.
- **Committed in:** `842c70d`.

**2. [Decisão de escopo] "risco sanitário (Badge)" e "versão vigente" por linha não viraram coluna por CNAE**
- **Found during:** Task 2.
- **Issue:** O plano sugere colunas "risco sanitário (Badge)" e "versão vigente" por linha; o contrato real do 06-06 não traz nível sanitário por CNAE e a versão vigente é única.
- **Fix:** Dimensões separadas em painéis (municipal × sanitária) + KPIs de resumo municipal; "versão vigente" no painel municipal; coluna "Condicionantes" (dado real do `RiskClassification.condicionantes`) em vez de badge sanitário fabricado. Honra RN-009 e a regra anti-fachada (sem dado fictício).
- **Committed in:** `1589b3c`.

**3. [Verificação] Ativação de `->component()` em disco nos testes do 06-06**
- **Found during:** Etapa de verificação.
- **Issue:** Os testes 06-06 usavam `->component('gestao/risco/index', false)` (nome sem exigir arquivo). Com as páginas entregues, o plano pede para validar o arquivo em disco.
- **Fix:** Removido o segundo argumento `false` nos dois testes (igualando o padrão dos irmãos: CnaeCrudTest, ManageUsersTest etc.). Sancionado pelas constraints ("testes que checam component() podem ser ativados").
- **Verification:** `--filter=Risco` 56/56; suíte completa 515/515.
- **Committed in:** `f03e5a9`.

---

**Total deviations:** 1 blocking (caminho de ícone), 1 decisão de escopo (dimensão sanitária honesta), 1 de verificação (ativação de component em disco).
**Impact on plan:** Sem scope creep de backend. Todos os arquivos dentro do `files_modified` do 06-08 (mais os dois testes do 06-06, ativação sancionada). Nenhuma feature de fachada: a dimensão sanitária é representada pelo dado real (condicionantes), nunca inventada por CNAE.

## Issues Encountered

- **Nome do componente sem arquivo em disco (06-06):** resolvido na verificação ativando `->component()` em disco após a entrega das páginas (deviation 3).
- **Tipo do evento de formulário:** `React.FormEvent` exigiria importar React (novo JSX transform não injeta `React`); usado `import type { FormEvent } from 'react'`.

## User Setup Required

None - sem configuração de serviço externo. Sem dependência nova (DS TailAdmin próprio, zero libs de UI de terceiros).

## Next Phase Readiness

- **Checkpoint humano (validação visual):** o plano marca um `checkpoint:human-verify` (claro/escuro/mobile, 403 para quem não tem consultar-risco, fluxo de publicação 4-olhos e CRUD de condicionantes). O código está verde (typecheck/build/515 testes); a validação visual fica para o orquestrador/usuário.
- **EP07 (consulta prévia):** a tabela de risco já é navegável de ponta a ponta no console (critério 2 transversal do ROADMAP); o motor (06-05) reflete automaticamente as versões publicadas pelos mantenedores (dado versionado).
- Sem blockers introduzidos. Um picker de usuário para o autor da publicação (em vez de ID) e o versionamento sanitário pela UI ficam como evolução futura, sem retrabalho (espelham os contratos já estabelecidos).

---
*Phase: 06-classificacao-de-risco*
*Completed: 2026-06-14*
