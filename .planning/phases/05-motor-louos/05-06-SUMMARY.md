---
phase: 05-motor-louos
plan: 06
subsystem: api
tags: [louos, rule-versions, four-eyes, permissions, maintainers, inertia, sqlite, auditoria]

# Dependency graph
requires:
  - phase: 05-motor-louos
    plan: 01
    provides: "RuleDomain louos_quadro7/10/11/11a (isSensitive), tabelas tipadas louos_quadro7_faixas/quadro10_permissoes/quadro11_condicoes_via, enum Quadro10Permissao"
  - phase: 05-motor-louos
    plan: 02
    provides: "versões vigentes lei-9148-2016-quadro7/10/11/11a + dados (40 faixas Q7, 18 permissões Q10, 4+4 condições Q11/11A), seeders LouosQuadro7/10/11Seeder"
  - phase: 06-classificacao-risco
    provides: "RuleVersion + RuleVersionService (openDraft/publish 4-olhos), FourEyesViolationException, AuditService, padrão RiscoController/RiscoMaintenanceService/PublishRiscoVersionRequest"
provides:
  - "Permissões consultar-louos (analista/gestor/admin) e manter-louos (admin) — gateiam consulta vs manutenção"
  - "LouosMaintenanceService.publishNewVersion: publica nova versão de um Quadro copiando a vigente + alterações, por quatro olhos (reusa RuleVersionService)"
  - "LouosController (consulta vigente dos 4 Quadros + publicação versionada) e PublishLouosVersionRequest"
  - "Rotas gestao.louos.index (consultar-louos) e gestao.louos.publicar (manter-louos)"
affects: [05-07-ui-mantenedores, 05-08-sandbox, 05-09-verificacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Mantenedor de Quadro LOUOS = consulta vigente (server-driven) + publish nova versão 4-olhos, espelhando o mantenedor de risco (06-06)"
    - "Um único service/controller/FormRequest serve os 4 Quadros via switch por RuleDomain (chave natural por tabela)"
    - "Publicação versionada não-destrutiva: a vigente anterior vira substituida (valid_to), nunca apagada"

key-files:
  created:
    - app/Services/Louos/LouosMaintenanceService.php
    - app/Http/Controllers/Gestao/LouosController.php
    - app/Http/Requests/Gestao/PublishLouosVersionRequest.php
    - tests/Feature/Louos/LouosMaintenanceTest.php
    - tests/Feature/Louos/LouosConsultaTest.php
  modified:
    - database/seeders/RolesAndPermissionsSeeder.php
    - tests/Feature/Authorization/RolesAndPermissionsSeederTest.php
    - tests/Feature/Roles/ManageRolesTest.php
    - routes/gestao.php

key-decisions:
  - "Permissões consultar-louos/manter-louos espelham consultar-risco/manter-risco; aditivas (givePermissionTo), total 12 → 14"
  - "Um único LouosController/LouosMaintenanceService/PublishLouosVersionRequest cobre os 4 Quadros (switch por domínio) em vez de 4 controllers — coeso, espelha risco"
  - "4-olhos delegado ao RuleVersionService (autor=publicador em domínio sensível lança FourEyesViolationException); o controller intercepta autor=publicador ANTES e comunica via flash.error (degradação controlada, não exception)"
  - "Alterações por Quadro validadas no FormRequest com a chave natural obrigatória; estrutura mínima por Quadro via match"

patterns-established:
  - "publishNewVersion(RuleDomain, version, alteracoes, authorId, publisherId): copyRows por domínio (insert em lote, sem model events) + publish; auditoria é o evento único do publish"
  - "Consulta dos Quadros: resumo das 4 versões vigentes + listagem paginada do Quadro selecionado (param quadro, default quadro7), busca case-insensitive, auditada (louos/consulta-quadros)"

# Metrics
duration: 13min
completed: 2026-06-14
---

# Phase 5 Plan 06: Mantenedores backend dos Quadros da LOUOS Summary

**Permissões consultar-louos/manter-louos + LouosController (consulta da versão vigente dos 4 Quadros e publicação de NOVA versão por quatro olhos) sobre o LouosMaintenanceService, que reusa o RuleVersionService da Fase 6 — a vigente anterior é preservada (substituida), nunca editada destrutivamente.**

## Performance

- **Duration:** ~13 min
- **Started:** 2026-06-14T05:35:48Z
- **Completed:** 2026-06-14T05:48:27Z
- **Tasks:** 2 (TDD RED→GREEN cada)
- **Files:** 9 (5 criados, 4 modificados)

## Accomplishments

- **Permissões `consultar-louos` e `manter-louos`** seedadas de forma aditiva (espelham risco): analista/gestor/administrador consultam; só o administrador mantém. Catálogo de permissões 12 → 14.
- **`LouosMaintenanceService::publishNewVersion`** publica uma NOVA versão de um Quadro: abre rascunho (`openDraft`), copia as linhas da tabela tipada da vigente aplicando as alterações (chave natural por Quadro) e publica com quatro olhos via `RuleVersionService::publish` — **infra da Fase 6 reusada, não recriada**. A vigente anterior é fechada (status `substituida`, `valid_to`), nunca apagada.
- **`LouosController`**: consulta a versão vigente dos 4 Quadros (resumo + listagem paginada do Quadro selecionado, busca case-insensitive, auditada) e publica nova versão por quatro olhos; autor igual ao publicador é bloqueado e comunicado (`flash.error`), nunca silencioso.
- **Rotas** `gestao.louos.index` (atrás de `consultar-louos`) e `gestao.louos.publicar` (atrás de `manter-louos`).
- **TDD estrito**: 4-olhos e 403 sem permissão falhavam antes (RED confirmado); 8 testes novos de Louos + 1 de Authorization verdes.

## Task Commits

1. **Task 1: permissões LOUOS + LouosMaintenanceService (4-olhos)** — `9dc0680` (feat) — RED→GREEN
2. **Task 2: LouosController (consulta + publish) + FormRequest + rotas** — `5ce1066` (feat) — RED→GREEN

## Contrato para 05-07 (UI), 05-08 (sandbox) e 05-09 (verificação)

### Rotas (nomes finais)
- `GET /gestao/louos` → `gestao.louos.index` (middleware `permission:consultar-louos`).
- `PUT /gestao/louos/publicar` → `gestao.louos.publicar` (middleware `permission:manter-louos`).

### Props enviadas ao Inertia (`gestao/louos/index`) — insumo do 05-07
- `quadros`: `list<{quadro, label, version|null, valid_from|null, total}>` — resumo das 4 versões vigentes (quadro ∈ quadro7|quadro10|quadro11|quadro11a).
- `quadroSelecionado`: string (default `quadro7`).
- `itens`: paginador (`{data, links, meta}`) do Quadro selecionado, com `withQueryString`. Shape de `data` por Quadro:
  - **quadro7**: `{id, cnae_code, formatted_code, grupo, subgrupo, area_min, area_max|null, observacao}`.
  - **quadro10**: `{id, zona, grupo_uso, subgrupo, permissao, permissao_label, condicionante_ref, base_legal}`.
  - **quadro11/quadro11a**: `{id, classe_via, grupo_uso, condicoes, base_legal}`.
- `filtros`: `{quadro, search, per_page}`.
- `perPageOptions`: `[10, 15, 25, 50]` (whitelist técnica; default reusa `ui.cnaes.per_page`).

### `LouosMaintenanceService::publishNewVersion`
`publishNewVersion(RuleDomain $domain, string $version, array $alteracoes, int $authorId, int $publisherId): RuleVersion` — em `DB::transaction`. Abre rascunho com `source` da vigente (ou texto padrão por Quadro), copia + aplica `$alteracoes`, publica (4-olhos). Domínios LOUOS são `isSensitive`, logo `publisher === author` lança `FourEyesViolationException`.

### Chave natural de alteração por Quadro (como a alteração casa com a linha da vigente)
- **quadro7**: `cnae_code` (dígitos) + `area_min`. Campos: `{cnae_code, area_min, area_max?, grupo, subgrupo?, observacao?}`.
- **quadro10**: `zona` + `grupo_uso` + `subgrupo` (ausente normalizado para `''`). Campos: `{zona, grupo_uso, subgrupo?, permissao, condicionante_ref?, base_legal?, observacao?}`.
- **quadro11/11a**: `classe_via` + `grupo_uso` (ausente normalizado para `''`). Campos: `{classe_via, grupo_uso?, condicoes?(array), base_legal?, observacao?}`.

### Payload do `PUT /gestao/louos/publicar` (PublishLouosVersionRequest)
`{quadro: in quadro7|quadro10|quadro11|quadro11a, version: string único no domínio, author_id: exists users, alteracoes: array (estrutura por Quadro acima)}`. O 4-olhos (autor ≠ publicador) é checado no controller (flash.error), não como erro de validação.

### Permissões adicionadas
`consultar-louos` (analista, gestor, administrador) e `manter-louos` (administrador). Cidadão não recebe nenhuma. Total do catálogo: **14**.

## Testes de contagem de permissão ajustados (sem regressão)
Adicionar 2 permissões alterou as asserções de contagem — atualizadas:
- `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php`: `test_seeder_e_idempotente` 12 → **14**; lista de `test_seeder_cria_papeis_e_permissoes_da_fase` ganhou as 2 permissões; novo `test_papeis_recebem_permissoes_de_louos`.
- `tests/Feature/Roles/ManageRolesTest.php`: `->has('permissions', 12)` → **14** (fora do `files_modified` do plano, ajuste exigido pela contagem — autorizado nas constraints).

## Evidência (verificação fresca)
- `php artisan test --compact --filter="LouosMaintenanceTest|RolesAndPermissionsSeederTest"` → **11 passed** (RED prévio: 4-olhos e contagem 14 falhavam pelos motivos certos).
- `php artisan test --compact --filter=LouosConsultaTest` → **5 passed** (RED prévio: 404/sessão ausente).
- `php artisan route:list --path=gestao/louos` → `gestao.louos.index` (GET) + `gestao.louos.publicar` (PUT).
- `php artisan test --compact --filter="Louos|Authorization|Roles"` → **63 passed**.
- `php artisan test --compact --exclude-group postgis` → **563 passed**, 0 falhas (baseline ~554 + 9 testes novos do plano; a referência 546 do plano estava defasada, como o 05-01/05-02 já notaram).
- `vendor/bin/pint --dirty --format agent` → passed.
- **Anti-fachada:** a publicação é operação real versionada (a vigente anterior vira `substituida` com `valid_to` — provado em `LouosMaintenanceTest` e `LouosConsultaTest`) e o 4-olhos é bloqueio real (`FourEyesViolationException` / flash.error), testado.

## Decisions Made
- **Um service/controller/FormRequest para os 4 Quadros** (switch por `RuleDomain`), em vez de 4 conjuntos — coeso e espelha o mantenedor de risco; a chave natural distingue a tabela tipada.
- **4-olhos em duas camadas:** o `RuleVersionService` é a defesa de domínio (lança exception); o controller intercepta autor=publicador antes e comunica via `flash.error` (degradação controlada, não erro 500), espelhando o `RiscoController`.
- **copyRows com insert em lote (sem model events):** a auditoria é o evento único do `publish` (RN-002), não uma activity por linha — mesma disciplina do `RiscoMaintenanceService`.
- **Colunas anuláveis da chave natural normalizadas para `''`** (subgrupo no Q10, grupo_uso no Q11) na cópia e na alteração, coerente com a carga/import do 05-02.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug regression] Contagem de permissão em ManageRolesTest**
- **Found during:** Task 1 (verificação `--exclude-group postgis`).
- **Issue:** Adicionar `consultar-louos`/`manter-louos` quebraria `tests/Feature/Roles/ManageRolesTest.php::test_administrador_lista_perfis...` (`->has('permissions', 12)`), fora do `files_modified` do plano.
- **Fix:** Atualizado para `14` (as constraints autorizam explicitamente ajustar a contagem em ManageRolesTest).
- **Files modified:** tests/Feature/Roles/ManageRolesTest.php
- **Verification:** `ManageRolesTest` 12/12; suíte completa 563/563.
- **Committed in:** 9dc0680 (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (regressão de contagem prevista pelas próprias constraints). Sem scope creep; nada de fachada.

## Issues Encountered
- **Baseline do plano defasado:** o plano citava 546; a suíte canônica (`--exclude-group postgis`) estava em ~554 antes deste plano e fechou em **563** (+9 testes novos), 0 falhas. Mesma deriva de baseline registrada no 05-01/05-02 — recomendo referenciar 563 nos próximos planos da fase.

## Next Plan Readiness (05-07+)
- **05-07 (UI):** contrato das props de `gestao/louos/index` fixado acima; a página React consome `quadros`/`itens`/`filtros`/`perPageOptions` e publica via `PUT gestao.louos.publicar` (payload documentado). O teste de consulta NÃO valida `->component()` ainda — adicionar quando a página existir.
- **05-08 (sandbox):** publica rascunhos pela mesma infra (`RuleVersionService` + `LouosMaintenanceService` reusáveis; `openDraft` coexiste com a vigente).
- **05-09 (verificação):** 4-olhos e não-destrutividade já travados por teste; permissões gateando consulta vs manutenção.

---
*Phase: 05-motor-louos*
*Completed: 2026-06-14*
