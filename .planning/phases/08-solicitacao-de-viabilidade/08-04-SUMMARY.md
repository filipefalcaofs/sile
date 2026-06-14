---
phase: 08-solicitacao-de-viabilidade
plan: 04
subsystem: cadastros-admin
tags: [solicitacao-viabilidade, hu-067, hu-014, requisitos-documentais, cnae, n-n, sync, crud, gestao, auditoria, rn-002, anti-fachada, inertia-react]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: 01
    provides: "tabelas document_requirements + pivot cnae_document_requirement + model DocumentRequirement (cnaes() belongsToMany) + factory"
  - phase: 08-solicitacao-de-viabilidade
    plan: 02
    provides: "permissão manter-requisitos-documentais (admin/gestor) — gate do CRUD"
  - phase: 03-cadastro-empresarial
    plan: 06
    provides: "padrão de sync N:N exato + auditoria antes/depois (CompanyCnaeService) e picker de CNAEs (CnaeSearchController, só ativos)"
  - phase: 02-administracao-base
    plan: 04
    provides: "padrão de CRUD de gestão server-driven (CnaeController/cnaes index.tsx) + DataTable/Modal/confirm-dialog (Fase 2.4)"
  - phase: 01-identidade
    plan: 02
    provides: "HasAuditoria + AuditService::log (RN-002) + render auditado do 403 (CA-04)"
provides:
  - "Gestao\\DocumentRequirementController: CRUD (index/store/update/toggle) + syncCnaes (vínculo N:N por sync exato)"
  - "rotas gestao.requisitos-documentais.* sob permission:manter-requisitos-documentais + busca de CNAEs (cnaes-disponiveis) reusando CnaeSearchController"
  - "StoreDocumentRequirementRequest/UpdateDocumentRequirementRequest (code único/imutável; required boolean)"
  - "tela gestao/requisitos-documentais/index.tsx (DataTable + modais + gerenciador de CNAEs vinculados com picker) + item de navegação por permissão"
  - "auditoria do requisito via HasAuditoria + auditoria explícita do vínculo (log 'solicitacoes', event 'requisito-cnaes', antes/depois)"
affects: [08-08-anexos]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Cadastro administrável de DADO de regra (HU-014): obrigatoriedade documental por CNAE é dado versionável (CRUD), não código — tabela por-CNAE nasce VAZIA (carga oficial pendente SEDUR), sem fachada"
    - "Sync N:N exato no controller (sem service dedicado, pois não há invariante extra como o 'único principal'): DB::transaction + sync() + auditoria explícita antes/depois (relações não entram no diff do HasAuditoria)"
    - "Reuso do CnaeSearchController em rota da gestão (guard gestao) — o controller é guard-agnóstico; o picker do console não pode bater em /portal/cnaes (sessões separadas por ambiente)"
    - "Auditoria mista: HasAuditoria no model (created/updated/deleted) + AuditService explícito para a relação N:N"

key-files:
  created:
    - app/Http/Controllers/Gestao/DocumentRequirementController.php
    - app/Http/Requests/Gestao/StoreDocumentRequirementRequest.php
    - app/Http/Requests/Gestao/UpdateDocumentRequirementRequest.php
    - resources/js/pages/gestao/requisitos-documentais/index.tsx
    - tests/Feature/Solicitacao/DocumentRequirementCrudTest.php
  modified:
    - routes/gestao.php
    - resources/js/layouts/gestao-layout.tsx
    - app/Models/DocumentRequirement.php

key-decisions:
  - "Auditoria do requisito via HasAuditoria (como Cnae/Company) — gera created/updated/deleted; o vínculo N:N é auditado explicitamente (log 'solicitacoes', event 'requisito-cnaes', antes/depois) porque relações não entram no diff do HasAuditoria. Consistente com 08-03 (tipos de serviço)."
  - "Sync N:N feito NO controller (não em service): diferente do CompanyCnaeService [03-06], aqui não há invariante extra (ex.: 'exatamente um principal') a proteger — só conjunto exato. DB::transaction + sync() + auditoria explícita."
  - "Só CNAEs ATIVOS podem ser vinculados (Rule::exists where active — precedente seleção manual [03-06]); o picker reusa CnaeSearchController (só ativos)."
  - "code imutável na edição (não está nas rules do UpdateRequest — padrão CPF/CNAE) e situação alternada por toggle dedicado (PUT {requirement}/toggle), separado do update — preserva histórico (RN-002), nunca exclui."
  - "Busca de CNAEs do picker exposta em rota da GESTÃO (gestao.requisitos-documentais.cnaes-disponiveis) reusando o Portal\\CnaeSearchController — o console (guard gestao) não acessa /portal/cnaes (guard web; sessões separadas por ambiente, decisão 2026-06-12)."
  - "per_page lido via Settings::get('ui.requisitos_documentais.per_page', 15) com default inline — sem novo parâmetro no catálogo (08-02 restringiu o escopo); admin pode promover a parâmetro depois sem tocar o call site (HU-014)."

patterns-established:
  - "Cadastro de requisitos (modelo 'Requisito' SIGVISA) com gerenciador de CNAEs vinculados em Modal (chips + picker incremental), salvando o conjunto exato via PUT {requirement}/cnaes"

# Metrics
duration: ~15 min
completed: 2026-06-14
---

# Phase 8 Plan 04: CRUD de Requisitos Documentais por CNAE (HU-067) Summary

**A obrigatoriedade documental por CNAE virou DADO administrável (HU-014), não código: o `DocumentRequirementController` entrega o CRUD do modelo "Requisito" do SIGVISA (index server-driven, store/update, toggle que desativa preservando histórico) e o vínculo N:N com CNAEs por `sync()` exato (padrão `syncSecondaries` [03-06]), tudo atrás da permissão `manter-requisitos-documentais` (403 auditado, CA-04). O requisito é auditado via `HasAuditoria` (created/updated) e o vínculo com auditoria explícita antes/depois (`log 'solicitacoes'`, event `requisito-cnaes`) — RN-002. A tela `gestao/requisitos-documentais/index.tsx` (console SEDUR) traz DataTable, modais de criar/editar, toggle por confirm-dialog e um gerenciador de CNAEs vinculados (chips + picker incremental que reusa o `CnaeSearchController`, só ativos), com item de navegação condicionado à permissão. ESCOPO HONESTO: a tabela por-CNAE nasce VAZIA (a planilha oficial da SEDUR está vazia hoje) — entregamos o CRUD real para a SEDUR popular; a carga oficial entra depois, mudando a carga e nunca a lógica. ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca): 8 testes do `DocumentRequirementCrudTest` (52 asserções); suíte completa 698/698 SQLite + 16/16 `@group postgis`; typecheck e build verdes.**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-06-14 (leitura do plano/contexto)
- **Completed:** 2026-06-14T08:34:10-03:00 (último commit)
- **Tasks:** 2 (Task 1 backend CRUD + sync N:N + testes; Task 2 tela de manutenção + navegação)
- **Files:** 5 criados + 3 modificados — ZERO dependência nova

## Accomplishments

- **CRUD administrável dos requisitos** (HU-067): index server-driven (busca por code/name, filtro de situação, ordenação whitelist `[code, name]`, per_page parametrizado), store/update com `code` único/imutável, `toggle` que desativa preservando o registro.
- **Vínculo N:N por sync exato**: `PUT {requirement}/cnaes` sincroniza o conjunto EXATO de CNAEs (`sync()`), em `DB::transaction`, com auditoria explícita antes/depois — espelha o `CompanyCnaeService` [03-06] sem precisar de service dedicado (não há invariante extra a proteger).
- **Gate + auditoria**: todas as rotas sob `permission:manter-requisitos-documentais` (403 auditado, CA-04); requisito auditado via `HasAuditoria` (RN-002); vínculo auditado explicitamente.
- **Tela de manutenção no console**: DataTable (código, nome, obrigatoriedade, nº de CNAEs, situação), modais criar/editar, toggle por `confirm-dialog`, e gerenciador de CNAEs vinculados (chips + picker incremental). Navegação condicionada à permissão.
- **Picker reusa o endpoint de busca**: `GET .../cnaes-disponiveis` monta o `Portal\CnaeSearchController` numa rota da gestão (guard gestao) — só ativos.
- **Anti-fachada**: a tabela por-CNAE nasce vazia (carga oficial pendente SEDUR); o CRUD é real e a regra documental por atividade deixa de ser hardcoded.

## Rotas (nomes exatos — insumo de 08-08)

| Método | URI | Nome |
|---|---|---|
| GET | `gestao/requisitos-documentais` | `gestao.requisitos-documentais.index` |
| GET | `gestao/requisitos-documentais/cnaes-disponiveis` | `gestao.requisitos-documentais.cnaes-disponiveis` |
| POST | `gestao/requisitos-documentais` | `gestao.requisitos-documentais.store` |
| PUT | `gestao/requisitos-documentais/{requirement}` | `gestao.requisitos-documentais.update` |
| PUT | `gestao/requisitos-documentais/{requirement}/toggle` | `gestao.requisitos-documentais.toggle` |
| PUT | `gestao/requisitos-documentais/{requirement}/cnaes` | `gestao.requisitos-documentais.cnaes` |

Todas sob `permission:manter-requisitos-documentais`. Ordenação literal antes do binding `{requirement}` (`cnaes-disponiveis` GET).

## Contrato para o DocumentRequirementResolver (08-08)

- Vínculo no model: `DocumentRequirement::cnaes()` é `belongsToMany(Cnae, 'cnae_document_requirement')->withTimestamps()`; `Cnae` não expõe a relação inversa (não foi necessária aqui).
- Como derivar os obrigatórios da solicitação: o resolver deve unir
  `DocumentRequirement::where('active', true)` **filtrados pelos CNAEs da solicitação** via o pivot `cnae_document_requirement` (ex.: `whereHas('cnaes', fn ($q) => $q->whereIn('cnaes.id', $cnaeIds))`) **∪** os obrigatórios-base condicionais (fachada sempre; concessão se `is_public_area`) — estes condicionais são tratados NO resolver (08-08), não neste cadastro.
- Hoje a interseção por-CNAE retorna VAZIO (tabela `cnae_document_requirement` sem dados): o resolver deve degradar honesto (sem requisito por-CNAE até a SEDUR popular), validando apenas os condicionais base.
- `validation_instructions` é o gancho para a validação por IA (EP14), inerte por ora; persistido e administrável.

## Decisions Made

- **Auditoria mista**: `HasAuditoria` no model (created/updated/deleted) + `AuditService::log('solicitacoes', 'requisito-cnaes', ...)` para o vínculo N:N (relações não entram no diff do HasAuditoria). Consistente com a escolha do 08-03.
- **Sync no controller, não em service**: ao contrário do `CompanyCnaeService` [03-06], aqui não há invariante extra (não existe "principal") — apenas conjunto exato; `DB::transaction` + `sync()` + auditoria explícita no próprio controller.
- **`toggle` separado do `update`**: a situação é alternada por rota dedicada (preserva histórico, nunca exclui); o `update` cuida só dos campos de conteúdo (code imutável).
- **`per_page` via Settings com default inline (15)**: sem novo parâmetro no catálogo (08-02 restringiu o escopo) — promovível a parâmetro depois sem tocar o call site.

## Deviations from Plan

### Auto-fixed / decisões dentro do escopo

**1. [Regra 2/3 — funcionalidade necessária] Rota de busca de CNAEs para o picker**
- **Contexto:** o plano lista as rotas index/store/update/toggle/cnaes e manda o picker "reusar o endpoint de busca (CnaeSearchController)". O endpoint existente (`/portal/cnaes`) está sob o guard `web` e é inacessível ao console (guard `gestao`, sessões separadas — decisão 2026-06-12).
- **Ação:** adicionada a rota `GET .../cnaes-disponiveis` na gestão montando o `Portal\CnaeSearchController` (guard-agnóstico). Reuso real do controller, sem tocar arquivos do portal.
- **Arquivo:** `routes/gestao.php` (no meu bloco).

**2. [Regra 2 — RN-002] `HasAuditoria` no `DocumentRequirement`**
- **Contexto:** o model (criado no 08-01) não usava `HasAuditoria`; o plano pede auditoria do requisito "via HasAuditoria (como Company) OU explícita". `app/Models/DocumentRequirement.php` não constava em `files_modified`.
- **Ação:** adicionado o trait (gera created/updated/deleted) — o 08-01 está concluído e o 08-03 não toca este model, sem risco de clobber. Documentado aqui.

**3. [Item do plano fora de files_modified] Item de navegação**
- **Contexto:** Task 2 exige "item de navegação condicionado à permissão", mas `resources/js/layouts/gestao-layout.tsx` não constava em `files_modified`.
- **Ação:** adicionado o item "Requisitos documentais" (visível com `manter-requisitos-documentais`) com disciplina de coordenação (reler antes de editar, anexar só o meu item, âncora estável na entrada "Usuários") — o 08-03 já havia commitado seu item "Tipos de serviço"; diff confirmou zero clobber.

**Total:** 3 ajustes dentro do escopo (necessários para a feature funcionar de ponta a ponta e cumprir RN-002/CA-04). Nenhum scope creep.

## Issues Encountered

- **08-03 em paralelo na MESMA working dir (arquivos compartilhados `routes/gestao.php` e `gestao-layout.tsx`):** o 08-03 já havia COMMITADO seus dois commits (`cab7a33`/`b4edaf3`) quando fui commitar. Reli os dois arquivos imediatamente antes de editar, anexei apenas o meu trecho (âncoras estáveis: bloco do sandbox LOUOS nas rotas; entrada "Usuários" na navegação) e confirmei por `git diff` que o diff continha SOMENTE minhas adições (o grupo `tipos-servico` e o item "Tipos de serviço" do 08-03 intactos). Staging sempre individual (nunca `git add -A`).
- **`test_administrador_lista_requisitos` (asserção `->component(...)`) só fica verde após a tela existir em disco (Task 2):** precedente [03-04]. Backend ficou 7/8 até a página ser criada; depois 8/8.

## Verification (evidência fresca)

- **RED:** `--filter=DocumentRequirementCrudTest` → 8 falhas (404 — rotas/controller inexistentes), motivo certo.
- **GREEN (backend):** após controller/requests/rotas/model → 7/8 (a 8ª exige a página em disco — Task 2).
- **GREEN (completo):** após a tela `index.tsx` → `--filter=DocumentRequirementCrudTest` → **8/8 (52 asserções)**.
- **`npx tsc --noEmit`** → sem erros. **`npm run build`** → ok (chunk `requisitos-documentais-*.js` gerado).
- **Suíte completa (`--exclude-group postgis`):** **698 testes, 698 passaram** (3571 asserções).
- **`@group postgis`** (container `sile-pgsql` healthy, `POSTGIS_TESTS_REQUIRED=true`): **16/16** (90 asserções).
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **`php artisan route:list --path=requisitos-documentais`** → 6 rotas registradas com os nomes esperados.

## Next Phase Readiness

- **Insumo direto de 08-08 (anexos/validação documental):** o `DocumentRequirementResolver` consome `document_requirements` (active) interseccionados pelos CNAEs da solicitação via `cnae_document_requirement`, unindo os condicionais base — degradando honesto enquanto a tabela por-CNAE estiver vazia.
- **Bloqueio herdado (não introduzido aqui):** carga oficial dos requisitos por CNAE (planilha SEDUR vazia hoje) — o CRUD está pronto para a SEDUR popular; quando a base chegar, muda a carga, não a lógica.

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
