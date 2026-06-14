---
phase: 05-motor-louos
plan: 07
subsystem: ui
tags: [louos, inertia, react, console, four-eyes, maintainers, data-table, tailwind]

# Dependency graph
requires:
  - phase: 05-motor-louos
    plan: 06
    provides: "Rotas gestao.louos.index/publicar, props do LouosController (quadros/quadroSelecionado/itens/filtros/perPageOptions), payload do PublishLouosVersionRequest, permissões consultar-louos/manter-louos"
  - phase: 02.4-template-listagens
    provides: "Padrão de listagem do console (PageHeader → Card → DataTable → Pagination), useServerTable, TableToolbar, PerPageSelect, KpiCard"
  - phase: 06-classificacao-risco
    plan: 08
    provides: "Tela-modelo de mantenedor versionado no console (gestao/risco/index): consulta vigente + modal de publicação 4-olhos"
provides:
  - "Tela gestao/louos/index.tsx no console SEDUR: resumo/seletor dos 4 Quadros vigentes + listagem paginada com busca + publicação versionada por quatro olhos"
  - "Item de navegação 'Quadros LOUOS' no console, gateado por consultar-louos"
  - "Padrão de UI para mantenedor de múltiplos Quadros (seletor por cartão-resumo, colunas por Quadro, sinalização honesta de Quadro modelado)"
affects: [05-08-sandbox, 05-09-verificacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Cartões-resumo dos 4 Quadros que também são o seletor (param quadro via useServerTable.setFilter), com aria-pressed"
    - "Colunas da DataTable derivadas do quadroSelecionado (cast por Quadro) — uma tabela única para os 4 esquemas"
    - "Sinalização honesta de Quadro modelado (10/11/11A) por metadado de UX, sem fingir operação plena (entrega-funcional)"
    - "Editor de alterações por Quadro no modal de publicação (campos = chave natural + esquema da tabela tipada do 05-06)"

key-files:
  created:
    - resources/js/pages/gestao/louos/index.tsx
    - tests/Feature/Louos/LouosUiTest.php
  modified:
    - resources/js/layouts/gestao-layout.tsx

key-decisions:
  - "Os 4 cartões-resumo (versão vigente + vigência + total) acumulam a função de seletor de Quadro — resolve resumo + troca de quadro num só componente, com feedback imediato (aria-pressed pelo estado local da tabela)"
  - "Distinção operacional (Quadro 7 aplica; 10/11/11A modelados pendente base territorial SEDUR) vive como metadado de UX no frontend, não como flag de backend — o LouosController (05-06) está fora do files_modified e não foi tocado"
  - "Uma DataTable única com colunas escolhidas por quadroSelecionado (cast tipado), em vez de 4 tabelas — coeso e casa com o shape real de itens.data por Quadro"
  - "LouosUiTest assere ->component() + affordance por auth.permissions (manter-louos), complementando o LouosConsultaTest do 05-06 (que valida props/auditoria/4-olhos sem ->component())"

patterns-established:
  - "Mantenedor de múltiplos quadros versionados no console: resumo-seletor em cards → tabela do selecionado → modal de publicação 4-olhos"
  - "Modal de publicação com editor de alterações configurável por Quadro (ALTERACAO_FIELDS): text/number/select/textarea→array, required = chave natural"

# Metrics
duration: 13min
completed: 2026-06-14
---

# Phase 5 Plan 07: UI dos mantenedores dos Quadros da LOUOS Summary

**Tela `gestao/louos/index.tsx` no console SEDUR: resumo-seletor das 4 versões vigentes dos Quadros 7/10/11/11A, listagem paginada com busca e publicação de nova versão por quatro olhos consumindo as rotas/props reais do 05-06 — com sinalização honesta dos Quadros apenas modelados (pendente base territorial SEDUR).**

## Performance

- **Duration:** ~13 min
- **Started:** 2026-06-14T05:51:00Z
- **Completed:** 2026-06-14T06:04:00Z
- **Tasks:** 2 executadas (Task 3 é checkpoint humano de validação visual)
- **Files:** 3 (2 criados, 1 modificado)

## Accomplishments

- **Página de consulta dos 4 Quadros**: cada Quadro vira um cartão-resumo (versão vigente, vigência, total de registros) que também é o **seletor** — acioná-lo troca o param `quadro` (server-driven via `useServerTable`), recarregando a listagem do Quadro selecionado.
- **Listagem paginada por Quadro** com colunas próprias de cada esquema (Quadro 7: CNAE/grupo/subgrupo/faixa de área; Quadro 10: zona/grupo de uso/permissão em `Badge`/condicionante/base legal; Quadro 11/11A: classe de via/grupo de uso/condições/base legal), busca case-insensitive e `Pagination` com meta from/to/total — tudo nos componentes padrão do console (`PageHeader`, `Card`, `DataTable`, `TableToolbar`, `PerPageSelect`).
- **Publicação versionada por quatro olhos** em `Modal` (`useForm` PUT `gestao.louos.publicar`): version, ID do autor e editor de alterações configurável por Quadro (a chave natural é obrigatória). A regra de quatro olhos é comunicada no texto e verificada no cliente (autor ≠ publicador), com o bloqueio real reforçado pelo backend via `flash.error` (Alert no `GestaoLayout`).
- **Honestidade anti-fachada**: os Quadros 10/11/11A são sinalizados como **modelados** a partir da Lei nº 9.148/2016 — aplicam plenamente quando a base oficial (zona urbanística / classificação viária, pendente SEDUR) chegar. O Quadro 7 aparece como operacional. Nenhum aviso falso de operação plena.
- **Navegação**: item **'Quadros LOUOS'** no grupo Cadastros do console, visível apenas com `consultar-louos`.

## Task Commits

1. **Task 1: página gestao/louos/index.tsx + navegação** — `5876f41` (feat)
2. **Task 2: teste de componente Inertia (LouosUiTest)** — `09ab871` (test)

**Plan metadata:** `docs(05-07)` (este SUMMARY)

_Nota: o commit `f06029e` (05-04, motor do Quadro 10) entrou no main em paralelo, entre os dois commits deste plano — execução concorrente de waves em arquivos disjuntos; os commits do 05-07 permaneceram atômicos e isolados aos seus arquivos._

## Files Created/Modified

- `resources/js/pages/gestao/louos/index.tsx` — Tela de consulta/publicação dos 4 Quadros no console (resumo-seletor, DataTable por Quadro, modal de publicação 4-olhos).
- `resources/js/layouts/gestao-layout.tsx` — Item de navegação 'Quadros LOUOS' gateado por `consultar-louos` (ícone `FileIcon`).
- `tests/Feature/Louos/LouosUiTest.php` — Teste Inertia: `->component('gestao/louos/index')` + contrato de props + affordance de publicar por permissão.

## Decisions Made

- **Cartões-resumo como seletor** (decisão de UX): unifica "resumo por Quadro" e "seletor de Quadro" num componente só; o destaque do selecionado usa o estado local da tabela (`table.filters.quadro`) para feedback imediato, enquanto colunas e dados usam `quadroSelecionado` (prop) para casar com o `itens.data` retornado.
- **Distinção operacional no frontend** (metadado `QUADROS_META`): o controller do 05-06 está fora do `files_modified` e não foi alterado; a sinalização de "modelado" reflete o estado documentado do projeto (zona urbanística bloqueada pendente SEDUR), comunicada honestamente — não é valor de negócio parametrizável.
- **Uma DataTable, colunas por Quadro** (cast tipado): coeso e fiel ao shape real de `itens.data`; durante a troca de Quadro o skeleton de loading cobre a transição.
- **Editor de alterações por Quadro** (`ALTERACAO_FIELDS`): campos espelham a chave natural + esquema da tabela tipada validados no `PublishLouosVersionRequest` (05-06); `textarea` de condições vira array no payload; linhas sem a chave natural são descartadas no submit.

## Deviations from Plan

None — plano executado como escrito. Os dois arquivos de produção e o teste correspondem exatamente ao `files_modified` do plano; nenhum ajuste fora de escopo foi necessário (nenhuma edição de backend/serviço; o `component()` foi assertável sem alterar o controller).

## Issues Encountered

- **Suíte concorrente:** o 05-04 (motor do Quadro 10) rodou em paralelo no mesmo working directory e adicionou/commitou `tests/Feature/Louos/LouosEnquadramentoTerritorioTest.php`. A suíte completa os inclui e permanece verde — sem colisão de arquivos com o 05-07.

## Checkpoint (validação visual humana — pendente)

A Task 3 do plano é um `checkpoint:human-verify` (bloqueante). Conforme as constraints da execução, o **código foi entregue verde** e a validação visual fica para o orquestrador/usuário. Roteiro de verificação (do plano):

1. `composer run dev`, logar no console em `/gestao/login` (admin) e aceitar o termo se pedido.
2. Acessar "Quadros LOUOS" → conferir a listagem do Quadro 7 (versão vigente, faixas por CNAE) e os resumos dos Quadros 10/11/11A com o aviso honesto de "modelado".
3. Abrir "Publicar nova versão", tentar publicar com o próprio usuário como autor → confirmar o bloqueio de quatro olhos (Alert). Publicar com autor distinto → nova versão vigente.
4. Conferir responsividade no viewport mobile (375px) e acessibilidade básica (foco/contraste).

## Evidência (verificação fresca)

- `npm run typecheck` → **0 erros** (exit 0).
- `npm run build` → verde; chunk `louos-*.js` (14,44 kB) emitido.
- `php artisan test --compact --filter=LouosUiTest` → **2 passed**, 47 asserções.
- `php artisan test --compact --filter=Louos` → **52 passed**, 336 asserções (inclui os testes do 05-04 paralelo).
- `php artisan test --compact --exclude-group postgis` → **571 passed**, 0 falhas (baseline 563 + 2 do 05-07 + 6 do 05-04 paralelo — sem regressão).
- `vendor/bin/pint tests/Feature/Louos/LouosUiTest.php --format agent` → passed.
- **Anti-fachada:** a tela consome as rotas/props REAIS do 05-06 (sem mock); a publicação é a operação versionada real (PUT `gestao.louos.publicar`) e o bloqueio de quatro olhos é real (testado no 05-06 e comunicado na UI); Quadros modelados sinalizados sem fingir operação plena.

## Next Plan Readiness

- **05-08 (sandbox):** segue o mesmo padrão desta tela (resumo-seletor + ações no console); a infra de rascunho (`openDraft` coexistindo com a vigente) e o `LouosMaintenanceService` já estão prontos no 05-06.
- **05-09 (verificação):** a UI navegável fecha o critério transversal "navegável no browser" dos mantenedores; 4-olhos e não-destrutividade já travados por teste no 05-06; resta a validação visual humana (checkpoint acima) e a verificação de fase.

---
*Phase: 05-motor-louos*
*Completed: 2026-06-14*
