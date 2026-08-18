---
phase: 09-fluxo-expresso
plan: 11
subsystem: ui-retaguarda
tags: [hu-076, hu-078, fluxo-expresso, retaguarda, console-sedur, inertia, react, eloquent-api-resource, somente-leitura, anti-fachada, consultar-solicitacoes, expresso]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "02"
    provides: "ViabilityDecision (model imutável 1:1) + DecisionOutcome(+label) + ViabilityRequest::decision()"
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "Decisão real gravada (outcome/consolidated_result/tvl_product_number/per_cnae/rules_versions/fundamentacao/reason/decided_at)"
  - phase: 09-fluxo-expresso
    plan: "08"
    provides: "Trilha 'integracoes'/'regin-parecer' result 'bloqueado' (origem do status pendente Regin)"
  - phase: 09-fluxo-expresso
    plan: "09"
    provides: "Trilha 'integracoes'/'sefaz-viabilidade' result 'bloqueado'/'ignorado' (origem do status pendente SEFAZ)"
  - phase: 08-solicitacao-viabilidade
    plan: "02"
    provides: "Permissão consultar-solicitacoes (reuso, sem permissão nova) + console TailAdmin (RiscoController/Pagination/DataTable)"
provides:
  - "Rota gestao.resultados-expresso (index/show) sob permission:consultar-solicitacoes — SOMENTE LEITURA"
  - "ResultadoExpressoController: lista filtrável (só decididas, join viability_decisions) + detalhe da ViabilityDecision (404 sem decisão)"
  - "ViabilityDecisionResource (1º Eloquent API Resource do projeto): payload de leitura honesto da decisão, consumido via ->resolve()"
  - "Status de transmissão Regin/SEFAZ lido da auditoria (pendente/transmitido/não-aplicável/aguardando) — nunca 'enviado' sem sucesso real"
  - "Páginas Inertia/React index/show no padrão do console (TailAdmin), somente leitura, com nota honesta de canal/PDF (HU-132 Fase 10)"
affects: [09-12, 10]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Eloquent API Resource consumido como prop do Inertia via ->resolve() (JsonResource é Responsable → Inertia adicionaria o wrapper 'data'; ->resolve() entrega o array puro)"
    - "Listagem por join no agregado (ViabilityRequest join viability_decisions) com ->through() preservando o shape do paginator que Pagination/useServerTable esperam — Resource fica para o detalhe (sem o gotcha de paginação dos resource collections)"
    - "Status de integração derivado da trilha de auditoria (último result por canal) — leitura honesta, sem coluna de estado redundante nem fachada de envio"

key-files:
  created:
    - app/Http/Controllers/Gestao/ResultadoExpressoController.php
    - app/Http/Resources/ViabilityDecisionResource.php
    - resources/js/pages/gestao/resultados-expresso/index.tsx
    - resources/js/pages/gestao/resultados-expresso/show.tsx
    - tests/Feature/Expresso/ResultadoExpressoConsoleTest.php
  modified:
    - routes/gestao.php

key-decisions:
  - "ZERO permissão nova: reusa consultar-solicitacoes (a decisão é parte da solicitação) — permanecem 19; decisão a validar com a SEDUR."
  - "ViabilityDecisionResource só no detalhe (show), consumido via ->resolve(); o index usa projeção via join (->through()) para manter o shape do paginator nativo — evita o wrapper 'data' e o gotcha de paginação dos resource collections."
  - "Status de transmissão Regin/SEFAZ lido da trilha 'integracoes' (result mais recente por canal): bloqueado⇒pendente, sucesso⇒transmitido, ignorado⇒não-aplicável, sem registro⇒aguardando. NUNCA 'enviado' sem sucesso real (anti-fachada)."
  - "show 404 quando a solicitação não tem decisão (em análise técnica) — sem inventar desfecho; a lista também só mostra decisões reais (join)."
  - "Consulta auditada (RN-002) em index e show (logName 'expresso', events consulta-resultado-lista/consulta-resultado), espelhando o RiscoController."
  - "per_cnae é a fonte autoritativa da decisão por CNAE (snapshot da decisão), não o pivot vivo de cnaes — anti-stale."

patterns-established:
  - "Tela de retaguarda somente leitura para registro imutável: zero ação de mutação na UI e no controller; a decisão append-only é só consultada/explicada."

# Metrics
duration: ~20min
completed: 2026-06-14
---

# Phase 9 Plan 11: Retaguarda do Resultado Expresso (lista + detalhe somente leitura) Summary

**A SEDUR ganhou a tela de consulta da decisão automática (HU-076/HU-078): `gestao.resultados-expresso` (index lista filtrável + show detalhe) sob REUSO de `consultar-solicitacoes` (ZERO permissão nova — permanecem 19; a decisão é parte da solicitação). O `ResultadoExpressoController` (espelha o RiscoController, server-driven) lista SÓ as decididas via join em `viability_decisions` (`->through()` projeção leve), com filtros por resultado/busca(protocolo,TVL)/período(`decided_at`), ordenação por decisão mais recente e paginação reusando `ui.cnaes.per_page`; o `show` carrega a decisão (404 honesto quando não há decisão — em análise técnica, sem inventar desfecho) e a entrega via o 1º Eloquent API Resource do projeto, `ViabilityDecisionResource`, consumido com `->resolve()` (porque o Inertia serializa um `JsonResource` como `Responsable`, o que adicionaria o wrapper `data`). O detalhe expõe outcome(+rótulo), veredito consolidado(+rótulo), número TVL (só no deferimento), decisão por CNAE, versões de regra da época, fundamentação legal, motivo (ex.: 'indeferido sem atuação', HU-134) e um resumo da solicitação. O STATUS DE TRANSMISSÃO Regin/SEFAZ é lido da trilha de auditoria `integracoes` (result mais recente por canal): `bloqueado⇒PENDENTE` (canal bloqueado, Fase 13), nunca "enviado" sem sucesso real (anti-fachada), com nota explícita de que o canal oficial é o Regin/SEFAZ e o PDF/TVL é a HU-132 (Fase 10). As páginas Inertia/React seguem o padrão TailAdmin (DataTable/Badge/Pagination/EmptyState/PageHeader) e são SOMENTE LEITURA (sem botões de ação). TDD estrito (RED→GREEN com evidência fresca): `ResultadoExpressoConsoleTest` 6/6 (56 asserções). Suíte completa SQLite 918/918 (4674 asserções) — zero regressão (912→+6). `vendor/bin/pint --dirty`, `npx tsc --noEmit` e `npm run build` verdes. ZERO dependência nova.**

## Performance

- **Duration:** ~20 min
- **Tasks:** 2 (backend rota/controller/resource + teste; páginas Inertia + verificação tsc/build)
- **Files modified:** 6 (5 criados, 1 modificado) — ZERO dependência nova

## Contrato para os planos seguintes (nomes EXATOS)

### Rotas (routes/gestao.php, sob `permission:consultar-solicitacoes`)

- `GET gestao/resultados-expresso` → `gestao.resultados-expresso.index`
- `GET gestao/resultados-expresso/{viabilityRequest}` → `gestao.resultados-expresso.show`
- SOMENTE LEITURA (sem store/update/destroy).

### `App\Http\Controllers\Gestao\ResultadoExpressoController`

- `index(Request)`: join `viability_decisions` (só decididas), `with('company')`, filtros `outcome` (deferida/indeferida), `search` (protocol_number/tvl, case-insensitive), período `data_de`/`data_ate` (`decided_at`), ordem `decided_at desc`, paginação (PER_PAGE_OPTIONS [10,15,25,50], default `ui.cnaes.per_page`=15). Props: `decisoes` (paginator: data/links/from/to/total), `filtros`, `perPageOptions`, `outcomeOptions`. Audita `expresso`/`consulta-resultado-lista`.
- `show(ViabilityRequest)`: `abort_unless(decision exists, 404)`; props `decisao` (ViabilityDecisionResource->resolve()) + `transmissao`. Audita `expresso`/`consulta-resultado`.

### `App\Http\Resources\ViabilityDecisionResource` (consumir via `->resolve()`)

Chaves: `id`, `flow`, `outcome`, `outcome_label`, `consolidated_result`, `consolidated_result_label`, `tvl_product_number` (null no indeferimento), `per_cnae[]`, `rules_versions{}`, `fundamentacao[]`, `reason`, `decided_at` (ISO-8601), `decided_by` (null=sistema), `is_sistema`, `solicitacao{id, protocol_number, status, status_label, empresa, cnpj, endereco}`.

### `transmissao` (prop do show)

`{ regin: Canal, sefaz: Canal }`, cada `Canal = { canal, status, label, result, registrado_em }` com `status ∈ {pendente, transmitido, nao_aplicavel, aguardando}` derivado do result mais recente da trilha `integracoes` (event `regin-parecer`/`sefaz-viabilidade`).

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-073/074/075 CA-04 — segurança de acesso | ResultadoExpressoConsoleTest::sem_permissao | 403 + auditoria 'seguranca'/'acesso-negado'/'bloqueado' |
| HU-076 — retaguarda exibe a decisão consolidada | ResultadoExpressoConsoleTest::show_de_deferida | decisao.outcome 'deferida' + tvl_product_number + per_cnae |
| HU-078 — decisão auditável consultável | ResultadoExpressoConsoleTest::lista/show | só decididas (join), props da decisão real |
| HU-076 RN-008 / anti-fachada — transmissão pendente | ResultadoExpressoConsoleTest::transmissao | regin.status/sefaz.status 'pendente' lido da trilha 'bloqueado' |
| Imutabilidade — somente leitura | ausência de store/update/destroy + show 404 sem decisão | sem inventar desfecho |

## Decisions Made

- **ZERO permissão nova (reuso de `consultar-solicitacoes`)** — a decisão é parte da solicitação; permanecem 19 permissões (decisão a validar com a SEDUR, registrada).
- **Resource só no detalhe, consumido via `->resolve()`** — o Inertia serializa `JsonResource` (Responsable) com o wrapper `data`; `->resolve()` entrega o array puro. O `index` usa projeção via join (`->through()`) para preservar o shape do paginator nativo que `Pagination`/`useServerTable` esperam — evita o gotcha de paginação dos resource collections.
- **Status de transmissão lido da auditoria** — fonte única e honesta (`integracoes`), sem coluna de estado redundante; `bloqueado⇒pendente`, jamais "enviado" sem `sucesso` real.
- **`per_cnae` é a fonte autoritativa** da decisão por CNAE (snapshot da decisão) — não o pivot vivo de CNAEs (anti-stale); por isso a `show` não precisa carregar a relação `cnaes`.
- **Consulta auditada (RN-002)** em index e show (logName `expresso`), espelhando o RiscoController — a consulta da decisão também deixa trilha.

## Deviations from Plan

### 1. [Rule 3 — Blocking] Páginas Inertia criadas na Task 1 (não na Task 2)

- **Por quê:** o feature test da Task 1 usa `assertInertia(->component('gestao/resultados-expresso/index'|'show'))` e o `inertia.testing.ensure_pages_exist` é `true` (default do pacote; o projeto não sobrescreve `config/inertia.php`). O `component()` resolve o arquivo via `inertia.view-finder->find()`, então as páginas PRECISAM existir em disco para o teste da Task 1 ficar verde.
- **O que foi feito:** as duas páginas (index/show) foram criadas e commitadas junto do backend na Task 1; a Task 2 foi a verificação de frontend (`npx tsc --noEmit` + `npm run build` verdes), sem delta de código (páginas já corretas). Commits: `0e22e8a` (Task 1, feat com backend+páginas+teste); Task 2 sem commit de código (verde sem ajustes).
- **Impacto:** nenhum no escopo/funcionalidade — apenas a ORDEM de criação difere da divisão de arquivos sugerida no plano.

### 2. [Decisão de implementação] `index` usa projeção via join, não o Resource

- O plano sugeria "a coleção (Resource)"; usar `ViabilityDecisionResource::collection($paginator)` quebraria o shape de paginação esperado pelo front (resource collection muda `links`/`meta`). Mantive a projeção `->through()` (padrão da casa — RiscoController) e reservei o Resource para o detalhe, onde ele brilha. Decisão registrada acima.

### 3. [Escopo — registrado, não feito] Link na navegação do console

- O plano lista apenas controller/resource/rota/páginas/teste; a navegação (sidebar) NÃO está em `files_modified`. Para não ultrapassar o escopo, a tela é acessível por rota nomeada/URL; o link de menu fica como follow-up para 09-12 ou ajuste de UX.

## Authentication Gates

Nenhum — sem CLI/credencial externa neste plano.

## Verification (evidência fresca)

- **RED:** `--filter=ResultadoExpressoConsoleTest` → 5 falhas por 404 (rotas inexistentes) + 1 passou por coincidência (404 esperado); motivo certo.
- **GREEN:** `--filter=ResultadoExpressoConsoleTest` → **6/6 (56 asserções)**.
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **`php artisan route:list --path=resultados-expresso`** → 2 rotas (`gestao.resultados-expresso.index`/`.show`).
- **`npx tsc --noEmit`** → exit 0 (sem erros).
- **`npm run build`** → built (Vite, exit 0).
- **Suíte completa SQLite:** `php artisan test --compact --exclude-group postgis` → **918/918 (4674 asserções)** — zero regressão (912→+6); permissões permanecem 19 (RolesAndPermissionsSeederTest na suíte).

## Next Phase Readiness

- **09-12** (fechamento + smoke): a tela já existe e mostra a decisão real + a transmissão Regin/SEFAZ 'bloqueado' (pendente). O smoke navegável humano pode percorrer: protocolar sobre zona fictícia (dev) → decisão automática → `/gestao/resultados-expresso` (deferimento com TVL/por CNAE/versões/fundamentação) → conferir transmissão pendente na trilha.
- **Fase 10 / HU-132** (PDF/TVL): a MESMA fonte (`ViabilityDecision` exposta pelo `ViabilityDecisionResource`) alimenta o documento da retaguarda — sem recomputar a decisão.
- **Follow-up de UX:** adicionar o item de menu no console para a tela de resultados (hoje acessível por rota nomeada).

## Checkpoint humano sugerido (verificação visual — não bloqueia)

1. Autenticar no console com um usuário que tenha `consultar-solicitacoes` (analista/gestor/admin).
2. Abrir `/gestao/resultados-expresso`: conferir a lista (só decididas), os filtros (resultado/busca/período), a paginação e o empty-state.
3. Abrir um deferimento: conferir o número TVL, a decisão por CNAE, as versões de regra, a fundamentação e a transmissão Regin/SEFAZ como **pendente** (canal bloqueado).
4. Conferir mobile (375px) e dark mode.

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
