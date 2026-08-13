---
phase: 08-solicitacao-de-viabilidade
plan: 03
subsystem: backoffice
tags: [solicitacao-viabilidade, hu-061, rn-005, crud-admin, parametrizacao, hu-014, permissao, auditoria, rn-002, console-sedur, inertia-react, anti-fachada]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: 01
    provides: "tabela viability_service_types (code unique, name, flow_hint, active) + model ViabilityServiceType (Fillable code/name/flow_hint/active, cast active bool, scopeActive) + ViabilityServiceTypeFactory (state inactive)"
  - phase: 08-solicitacao-de-viabilidade
    plan: 02
    provides: "permissão manter-tipos-servico (admin/gestor) — gate do CRUD"
  - phase: 02-administracao-base
    plan: "04"
    provides: "padrão CRUD de gestão server-driven (CnaeController index busca/ordenação/filtro/paginação + FormRequests + code imutável na edição)"
  - phase: 02.4-template-listagens
    plan: "01/02"
    provides: "padrão de listagem console (PageHeader→Card→TableToolbar→DataTable→Pagination), Modal criar/editar, confirm-dialog, useServerTable"
  - phase: 01-identidade
    plan: 02
    provides: "App\\Concerns\\HasAuditoria (RN-002) + gate permission: com 403 auditado (seguranca/acesso-negado/bloqueado, CA-04)"
provides:
  - "ViabilityServiceTypeController (index server-driven + store + update + toggleActivation) no console SEDUR"
  - "Store/UpdateViabilityServiceTypeRequest — code único (store) e imutável (update); active default true; name/flow_hint editáveis"
  - "rotas gestao.tipos-servico.{index,store,update,ativacao.update} sob permission:manter-tipos-servico"
  - "tela gestao/tipos-servico/index.tsx (datatable + modal criar/editar + toggle via confirm-dialog) + item de navegação por permissão"
  - "auditoria automática (HasAuditoria) de created/updated em ViabilityServiceType (RN-002)"
affects: [08-05-criar-rascunho (select de tipo de serviço ativo), 08-16-fechamento (seed mínimo dos tipos oficiais SEDUR)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CRUD admin de cadastro da fase 8 espelhando CnaeController (server-driven) — listagem e CRUD sob UMA permissão (manter-tipos-servico), sem consulta separada"
    - "Desativação por TOGGLE dedicado (toggleActivation) que preserva o registro — nunca destroy, pois o tipo é referenciado por solicitações (histórico)"
    - "code imutável na edição via omissão no UpdateRequest (padrão CPF/CNAE: valor enviado nunca chega ao validated())"
    - "Auditoria por HasAuditoria no model (created/updated dos fillable), como Company — sem AuditService explícito no controller"

key-files:
  created:
    - app/Http/Controllers/Gestao/ViabilityServiceTypeController.php
    - app/Http/Requests/Gestao/StoreViabilityServiceTypeRequest.php
    - app/Http/Requests/Gestao/UpdateViabilityServiceTypeRequest.php
    - resources/js/pages/gestao/tipos-servico/index.tsx
    - tests/Feature/Solicitacao/ViabilityServiceTypeCrudTest.php
  modified:
    - app/Models/ViabilityServiceType.php
    - routes/gestao.php
    - resources/js/layouts/gestao-layout.tsx
    - resources/js/components/icons/index.tsx

key-decisions:
  - "Auditoria via HasAuditoria no ViabilityServiceType (decisão do plano: como Company), não AuditService explícito — created/updated dos campos fillable; o toggle de ativação gera evento 'updated' (RN-002)."
  - "Listagem E CRUD sob a MESMA permissão manter-tipos-servico (não há consulta separada como em CNAE/risco/louos): tipos de serviço só interessam a quem os mantém (admin/gestor). Sem ela, 403 auditado (CA-04)."
  - "Desativar é TOGGLE dedicado (PUT {serviceType}/ativacao → toggleActivation) que NUNCA exclui o registro — preserva o histórico das solicitações que já usaram o tipo. Não há rota destroy."
  - "code imutável na edição: ausente do UpdateRequest (padrão CPF/CNAE). No store é obrigatório/único; formato livre (max:50) porque a lista oficial é pendência SEDUR (seed no 08-16)."
  - "per_page com DEFAULT_PER_PAGE constante (15) + whitelist [10,15,25,50] — sem parâmetro dedicado para não tocar o ParameterSeeder durante a execução paralela do 08-04 (sancionado pelo plano: 'parâmetro ui existente ou constante')."

patterns-established:
  - "Cadastro admin parametrizável (dado, não registry) da fase 8: tabela + CRUD + permissão própria + auditoria + toggle preservando histórico — molde para o 08-04 (requisitos documentais)"

# Metrics
duration: ~12 min
completed: 2026-06-14
---

# Phase 8 Plan 03: CRUD e UI dos Tipos de Serviço Summary

**Os tipos de serviço da solicitação (HU-061 RN-005) ganharam manutenção administrável de ponta a ponta no console SEDUR — dado (tabela + CRUD), não registry de código. `ViabilityServiceTypeController` é server-driven espelhando o `CnaeController` (busca por code/name caseSensitive:false, ordenação whitelistada [code,name], filtro de situação, paginação) e expõe store/update/toggleActivation. Os `Store/UpdateViabilityServiceTypeRequest` garantem `code` único na criação e IMUTÁVEL na edição (padrão CPF/CNAE — ausente das regras do update). A desativação é um TOGGLE dedicado que NUNCA exclui o registro (preserva o histórico das solicitações que já usaram o tipo). Tudo atrás de `permission:manter-tipos-servico` (sem consulta separada; sem ela → 403 auditado `seguranca/acesso-negado/bloqueado`, CA-04) e auditado automaticamente por `HasAuditoria` no model (created/updated, RN-002). A tela `gestao/tipos-servico/index.tsx` segue o padrão de listagem da gestão (PageHeader → Card → TableToolbar → DataTable → Pagination), com criar/editar em Modal e toggle via confirm-dialog, mais um item de navegação "Tipos de serviço" no grupo Cadastros condicionado à permissão. ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca): `ViabilityServiceTypeCrudTest` 6/6 (28 asserções); typecheck e build verdes.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-06-14 (RED de ViabilityServiceTypeCrudTest)
- **Completed:** 2026-06-14T08:30:33-03:00 (último commit cab7a33)
- **Tasks:** 2 (CRUD backend + permissão/auditoria; tela console + navegação)
- **Files:** 5 criados + 4 modificados — ZERO dependência nova

## Accomplishments

- **CRUD backend** server-driven dos tipos de serviço espelhando o `CnaeController` — busca, ordenação, filtro de situação e paginação.
- **`code` único e imutável** na edição (omitido do `UpdateRequest`); no `store` obrigatório/único, formato livre (lista oficial é pendência SEDUR).
- **Desativação que preserva histórico** via `toggleActivation` (PUT `{serviceType}/ativacao`) — nunca `destroy`.
- **Gate único** `permission:manter-tipos-servico` para listagem e CRUD; sem ela, 403 auditado (CA-04).
- **Auditoria automática** (`HasAuditoria` no model) de created/updated (RN-002).
- **Tela no padrão console** (TailAdmin / Fase 2.4) com modal criar/editar e toggle por confirm-dialog; **navegação** "Tipos de serviço" no grupo Cadastros condicionada à permissão; ícone `TagIcon` adicionado.

## Rotas (nomes exatos — insumo do 08-05/08-16)

| Método | URI | Nome | Ação |
|---|---|---|---|
| GET | `gestao/tipos-servico` | `gestao.tipos-servico.index` | listagem server-driven |
| POST | `gestao/tipos-servico` | `gestao.tipos-servico.store` | criar |
| PUT | `gestao/tipos-servico/{serviceType}` | `gestao.tipos-servico.update` | editar (name/flow_hint/active; code imutável) |
| PUT | `gestao/tipos-servico/{serviceType}/ativacao` | `gestao.tipos-servico.ativacao.update` | toggle ativo/inativo (preserva registro) |

Todas no grupo `auth:gestao` + `permission:acessar-gestao` + `lgpd.accepted`, sob `permission:manter-tipos-servico`.

## Shape das props da tela (`gestao/tipos-servico/index`)

- `serviceTypes`: paginação Inertia (`data[]` = `{ id, code, name, flow_hint, active }`, `links`, `from`, `to`, `total`).
- `filters`: `{ search, sort, direction, per_page, active }` (active `''|'0'|'1'`).
- `perPageOptions`: `[10, 15, 25, 50]`.

## Como o tipo de serviço é consultado pela criação (contrato p/ 08-05)

A criação do rascunho (08-05) seleciona um tipo de serviço ATIVO. O contrato é o model `ViabilityServiceType` com `scopeActive()` (já existe — 08-01): `ViabilityServiceType::active()->orderBy('name')->get(['id','code','name','flow_hint'])` alimenta o select. O `viability_requests.service_type_id` é `nullOnDelete`, mas como a desativação é toggle (não exclusão), o vínculo histórico é sempre preservado — um tipo desativado some da seleção sem quebrar solicitações existentes.

## Decisão de auditoria (HasAuditoria vs explícita)

Escolhido **`HasAuditoria` no model** (como `Company`), não `AuditService` explícito no controller: created/updated dos campos fillable (`code/name/flow_hint/active`) são logados automaticamente (`activity_log`, subject_type `App\Models\ViabilityServiceType`). O toggle de ativação altera `active` e gera evento `updated` auditado. Simples, consistente com o cadastro empresarial e suficiente para o RN-002 deste cadastro administrável.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: CRUD backend (controller + 2 FormRequests + rotas + HasAuditoria)** — `b4edaf3` (feat) — RED: 6 falhas (404 nas rotas inexistentes + sessão sem status/errors) → GREEN: 6/6 (28 asserções).
2. **Task 2: tela console + navegação + TagIcon** — `cab7a33` (feat) — typecheck e build verdes; chunk `tipos-servico-*.js` gerado.

**Plan metadata:** `docs(08-03)` (este SUMMARY + STATE).

## Decisions Made

- **Gate único `manter-tipos-servico`** para listagem E CRUD (sem "consultar" separado): diferente de CNAE/risco/louos, o cadastro de tipos só interessa a quem o mantém (admin/gestor). A navegação usa a mesma permissão.
- **Toggle, nunca destroy**: a desativação preserva o registro (referenciado por solicitações). Não existe rota de exclusão.
- **`code` imutável na edição** (padrão CPF/CNAE) e livre/único na criação — formato oficial é pendência SEDUR (seed no 08-16).
- **`per_page` por constante** (`DEFAULT_PER_PAGE=15` + whitelist), sem parâmetro dedicado — para não tocar o `ParameterSeeder` durante a execução paralela do 08-04; sancionado pelo plano ("parâmetro ui existente ou constante"). Um parâmetro `ui.tipos_servico.per_page` pode ser adicionado depois sem mudar o call site.
- **`TagIcon`** novo no conjunto de ícones (UX distinta na sidebar) — appended ao fim de `icons/index.tsx`.

## Deviations from Plan

None — plano executado como escrito. (O plano sugeria HasAuditoria OU AuditService explícito; escolhido HasAuditoria, conforme a preferência registrada no próprio plano.)

## Issues Encountered

- **08-04 executando em paralelo na MESMA working dir:** durante a execução, o agente do 08-04 (requisitos documentais) adicionou `DocumentRequirementController`/requests/test, modificou `app/Models/DocumentRequirement.php` e ANEXOU suas rotas em `routes/gestao.php`. **Boundary respeitado:** reli `routes/gestao.php` e `gestao-layout.tsx` imediatamente antes de editar e ANEXEI apenas o meu trecho; minhas rotas (147-151) permaneceram intactas após o append do 08-04 (134-140); staging sempre individual (nunca `git add -A`) — não estagei `routes/gestao.php` (já commitado na Task 1) nem nenhum arquivo de `DocumentRequirement`.
- **Confirmação do precedente [03-04]:** o teste do 08-04 falha com "Inertia page component file [gestao/requisitos-documentais/index] does not exist" — prova de que `->component()` exige o arquivo em disco. Por isso o `ViabilityServiceTypeCrudTest` (Task 1, backend) NÃO usa `->component()`; ainda assim a página existe (Task 2) e o teste de listagem afere props (`has/where`).

## Verification (evidência fresca)

- **RED Task 1:** `--filter=ViabilityServiceTypeCrudTest` → 6 falhas (404 + sessão sem status/errors).
- **GREEN Task 1:** `--filter=ViabilityServiceTypeCrudTest` → **6/6** (28 asserções).
- **`php artisan route:list --path=tipos-servico`** → 4 rotas (index/store/update/ativacao) sob `gestao.tipos-servico.*`.
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **`npx tsc --noEmit`** → 0 erros. **`npm run build`** → ✓ built (chunk `tipos-servico-1fC9FKxW.js`).
- **Suíte completa (`--exclude-group postgis`):** 698 testes, **697 passaram**, 1 falha — `DocumentRequirementCrudTest::test_administrador_lista_requisitos` (página `gestao/requisitos-documentais/index` ainda não criada pelo **08-04, em andamento**), FORA do meu escopo. Zero falha atribuível a este plano (CNAE, Roles, fundação da Solicitação e roteamento todos verdes).

## Next Phase Readiness

- **08-05 (criar rascunho):** o select de tipo de serviço consome `ViabilityServiceType::active()` (id/code/name/flow_hint). `service_type_id` no aggregate; tipo desativado some da seleção sem quebrar histórico.
- **08-16 (fechamento):** seed mínimo dos tipos oficiais entra pelo cadastro administrável (upsert por `code`), substituível sem deploy quando a SEDUR entregar a lista — o motor processa a carga, a lógica não muda.
- **Molde para 08-04 (requisitos documentais):** mesmo padrão (tabela + CRUD + permissão própria + auditoria + toggle preservando histórico).

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
