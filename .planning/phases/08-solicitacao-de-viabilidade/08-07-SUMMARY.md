---
phase: 08-solicitacao-de-viabilidade
plan: 07
subsystem: portal
tags: [solicitacao-viabilidade, hu-064, hu-065, cnae, pivot, is-primary, parametrizacao, simulacao-stale, rn-005, rn-002, policy, anti-fachada]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: 01
    provides: "ViabilityRequest::cnaes() belongsToMany viability_request_cnaes withPivot('is_primary') + primaryCnae() + markSimulationStale() (HU-063 RN-005); unique(viability_request_id, cnae_id)"
  - phase: 08-solicitacao-de-viabilidade
    plan: 02
    provides: "parâmetro solicitacao.cnaes_complementares.max (catálogo + fallback config/sile.php, default 99) lido via Settings::get"
  - phase: 08-solicitacao-de-viabilidade
    plan: 05
    provides: "ViabilityRequestPolicy::update (dono efetivo + status rascunho) integrada à representação; rotas portal.solicitacoes.* no grupo auth:web+verified+lgpd.accepted+ResolveRepresentation"
  - phase: 03-cadastro-empresarial
    plan: "06"
    provides: "padrão de escrita transacional do pivot company_cnae (CompanyCnaeService: sync com conjunto exato + is_primary + auditoria antes/depois) + CnaeSearchController (busca só ativos) + seleção manual aceita só CNAEs ativos"
provides:
  - "App\\Http\\Controllers\\Portal\\SolicitacaoAtividadeController@update — define atividade principal (is_primary=true) + CNAEs complementares (is_primary=false) por sync transacional do conjunto exato"
  - "App\\Http\\Requests\\Portal\\UpdateSolicitacaoAtividadesRequest — só CNAEs ativos, limite parametrizável dinâmico (solicitacao.cnaes_complementares.max), principal fora dos complementares, sem duplicados"
  - "rota PUT portal/solicitacoes/{solicitacao}/atividades (name portal.solicitacoes.atividades)"
affects: [08-08-anexos, 08-09-simulacao, 08-13-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Escrita do pivot da fase 8 no CONTROLLER (não em service): como toda a lista (principal + complementares) chega numa ÚNICA request, um sync com payload exato ({principalId: is_primary=true} + complementares: is_primary=false) garante 'exatamente um principal' e a unicidade sem precisar do par demote/promote do CompanyCnaeService (que existe porque a empresa tem 2 endpoints separados)"
    - "Limite administrável lido DINAMICAMENTE no rules() do FormRequest: max:{Settings::get('solicitacao.cnaes_complementares.max', config(...,99))} — efeito sem deploy (espelha a validação dinâmica do [02-07])"
    - "Mudança de CNAEs chama markSimulationStale() DENTRO da mesma transação do sync — a invalidação da simulação (RN-005) é atômica com a escrita dos vínculos"

key-files:
  created:
    - app/Http/Controllers/Portal/SolicitacaoAtividadeController.php
    - app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php
    - tests/Feature/Solicitacao/InformarAtividadesTest.php
  modified:
    - routes/portal.php

key-decisions:
  - "Pivot escrito no controller (não em service dedicado): a solicitação recebe principal + complementares numa única request, então um único $solicitacao->cnaes()->sync($payload) com o principal primeiro (is_primary=true) e os complementares (is_primary=false) já garante a invariante 'um principal' e a unicidade (request, cnae). Diferente do CompanyCnaeService (Fase 3), que precisa de setPrimary/syncSecondaries porque a empresa tem dois endpoints distintos. files_modified do plano não previa service — boundary respeitado."
  - "Limite de complementares lido dinamicamente no rules(): Settings::get('solicitacao.cnaes_complementares.max', config('sile.solicitacao.cnaes_complementares.max', 99)) como max:{N} — exceder gera erro de validação comunicado em 'complementares' (não silencioso); unique(request, cnae) é a defesa final no banco."
  - "Só CNAEs ATIVOS são vinculáveis (Rule::exists('cnaes','id')->where('active', true)) tanto no principal quanto em cada complementar — mesma regra da seleção manual da Fase 3 ([03-06]); a busca reusa portal.cnaes.search (CnaeSearchController, já só ativos)."
  - "Autorização dono+rascunho no controller via Gate::authorize('update', $solicitacao) usando a ViabilityRequestPolicy do 08-05 (a regra fina da edição já existia). FormRequest::authorize() retorna true; a validação só é alcançada por quem passa pela rota autenticada, e o 403 vem do Gate com dados válidos."
  - "markSimulationStale() é chamado dentro da DB::transaction do sync (RN-005): trocar a atividade/CNAEs invalida a simulação orientativa anterior de forma atômica. Auditoria explícita 'solicitacao-cnaes' (antes/depois com códigos + is_primary) coexiste com o updated do HasAuditoria — relações não entram no diff automático."

patterns-established:
  - "Update de coleção pivot 'tudo numa request' (principal + N itens): validar conjunto + sync único com is_primary no payload + invalidar derivados (simulação) + auditar antes/depois, tudo numa transação — molde para futuras coleções de uma só submissão"

# Metrics
duration: ~9 min
completed: 2026-06-14
---

# Phase 8 Plan 07: Informar Atividade Principal e CNAEs Complementares (HU-064/HU-065) Summary

**O requerente agora instrui as atividades do rascunho: define a atividade principal (CNAE principal, `is_primary=true`) e os CNAEs complementares (até o limite parametrizável, default 99) a partir da tabela oficial — só CNAEs ATIVOS, com exatamente um principal e unicidade `(request, cnae)`, espelhando o pivot `company_cnae` da Fase 3. O `SolicitacaoAtividadeController@update` grava `viability_request_cnaes` numa única transação com `cnaes()->sync()` do conjunto exato (o principal entra primeiro com `is_primary=true`; os complementares com `is_primary=false`), e — como a lista mudou — chama `markSimulationStale()` no mesmo átomo (HU-063 RN-005), tudo auditado explicitamente (`solicitacao-cnaes`, antes/depois). O `UpdateSolicitacaoAtividadesRequest` lê o limite DINAMICAMENTE do catálogo (`solicitacao.cnaes_complementares.max` via `Settings::get`, efeito sem deploy), recusa CNAE inativo/inexistente, impede o principal de aparecer entre os complementares e bloqueia duplicados — exceder o limite é erro comunicado, nunca silencioso. A autorização dono+rascunho usa a `ViabilityRequestPolicy` do 08-05. Plano IRMÃO PARALELO do 08-06 (imóvel/geometry): arquivos distintos, zero colisão — o 08-06 não tocou `routes/portal.php` (serviço `PropertyGeometryWriter` + teste postgis). ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca): 7 testes novos; suíte completa 744/744.**

## Performance

- **Duration:** ~9 min
- **Completed:** 2026-06-14
- **Tasks:** 1 (update de atividades: controller + request + rota + feature tests)
- **Files:** 3 criados + 1 modificado (`routes/portal.php`, append-only) — ZERO dependência nova

## Accomplishments

- **`SolicitacaoAtividadeController@update`**: escreve o pivot `viability_request_cnaes` em `DB::transaction` com um único `cnaes()->sync()` do conjunto exato — principal (`is_primary=true`) + complementares (`is_primary=false`); garante um principal único e a unicidade `(request, cnae)`.
- **Invalidação da simulação (RN-005)**: `markSimulationStale()` na mesma transação — mudar os CNAEs zera o snapshot orientativo anterior.
- **Auditoria explícita (RN-002)**: `AuditService::log('solicitacoes', 'solicitacao-cnaes', ...)` com `antes`/`depois` (códigos + `is_primary`), coexistindo com o `updated` do `HasAuditoria`.
- **`UpdateSolicitacaoAtividadesRequest`**: só CNAEs ATIVOS (principal e complementares), limite parametrizável dinâmico, principal fora dos complementares (`after()`), sem duplicados (`distinct`), mensagens pt-BR.
- **Autorização dono+rascunho** pela `ViabilityRequestPolicy` (08-05) via `Gate::authorize('update', ...)`.
- **Rota** `PUT portal/solicitacoes/{solicitacao}/atividades` anexada APÓS as literais, sem colisão com o 08-06.

## Rota e contrato (insumo de 08-08/08-09/08-13)

| Método | URI | Nome | Ação |
|---|---|---|---|
| PUT | `portal/solicitacoes/{solicitacao}/atividades` | `portal.solicitacoes.atividades` | define principal + complementares |

No grupo `auth:web` + `verified` + `lgpd.accepted` + `ResolveRepresentation`.

**Payload aceito:**

- `principal_cnae_id` (required, integer, CNAE ATIVO da tabela oficial).
- `complementares` (nullable, array, `max` = `solicitacao.cnaes_complementares.max`, default 99); `complementares.*` integer, `distinct`, CNAE ATIVO; o principal não pode estar entre eles.

**Efeito:** `cnaes()->sync()` do conjunto exato (1 principal + complementares) + `markSimulationStale()` + auditoria `solicitacao-cnaes`; redireciona `back()` com flash `status`.

## Assinaturas (contrato dos planos seguintes)

- **`SolicitacaoAtividadeController::update(UpdateSolicitacaoAtividadesRequest, ViabilityRequest $solicitacao): RedirectResponse`** — `Gate::authorize('update', $solicitacao)`; transação: `sync([principalId => ['is_primary'=>true], ...complementares => ['is_primary'=>false]])` + `markSimulationStale()` + auditoria.
- **`UpdateSolicitacaoAtividadesRequest`** — `rules()` lê `max` dinâmico via `Settings::get`; `after()` impede principal entre complementares; `messages()` pt-BR.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca):

1. **test(08-07)** — `8d6302d` — `InformarAtividadesTest` (7 testes). RED: 7 erros (rota `portal.solicitacoes.atividades` inexistente).
2. **feat(08-07)** — `985c582` — controller + request + rota. GREEN: 7/7 (29 asserções).

**Plan metadata:** `docs(08-07)` (este SUMMARY + STATE).

## Decisions Made

- **Pivot no controller (não em service)**: a lista chega completa numa única request → um `sync()` com payload exato já garante a invariante; o par `setPrimary`/`syncSecondaries` do `CompanyCnaeService` existe pela natureza de 2 endpoints da empresa, desnecessário aqui. `files_modified` do plano não previa service — boundary respeitado.
- **Limite dinâmico no `rules()`**: `max:{Settings::get('solicitacao.cnaes_complementares.max', config(...,99))}` — efeito sem deploy; exceder = erro comunicado em `complementares`.
- **Só ativos** (principal e complementares): `Rule::exists('cnaes','id')->where('active', true)`, mesma regra da seleção manual [03-06].
- **`markSimulationStale()` atômico** com o `sync()` na mesma transação (RN-005).
- **Auditoria explícita** `solicitacao-cnaes` (antes/depois) porque relações não entram no diff do `HasAuditoria`.

## Deviations from Plan

None — plano executado como escrito (1 task, TDD). O plano sugeria "espelhando CompanyCnaeService"; como toda a lista vem numa única request, a escrita ficou no controller com um único `sync()` (mais simples e suficiente para a invariante), dentro do `files_modified` declarado (sem criar service). Os nomes de teste pt-BR foram normalizados pelo Pint (`php_unit_method_casing`) — sem efeito no código de produção.

## Issues Encountered

- **08-06 em paralelo na MESMA working dir**: o 08-06 commitou `29fa6c9` (serviço `PropertyGeometryWriter` + `InformarImovelPostgisTest`) entre o início e o fim deste plano. **Boundary respeitado**: o 08-06 NÃO tocou `routes/portal.php` (confirmado por `git show`); reli `routes/portal.php` imediatamente antes de editar e apenas ANEXEI o import + a rota (staging individual; `git diff` confirmou exatamente 2 adições, zero clobber). A suíte completa (744) validou os dois trabalhos juntos sem regressão (735 baseline + 2 do 08-06 + 7 meus).

## Verification (evidência fresca)

- **RED:** `--filter=InformarAtividadesTest` → 7 erros ("Route [portal.solicitacoes.atividades] not defined").
- **GREEN:** `--filter=InformarAtividadesTest` → **7/7** (29 asserções).
- **Critérios de aceite:** `grep cnaes_complementares.max` no request (2), `grep markSimulationStale` no controller (1), `grep solicitacoes/{solicitacao}/atividades` em routes (1); `php artisan route:list --path=solicitacoes` mostra a rota `portal.solicitacoes.atividades`.
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **Suíte completa:** `POSTGIS_TESTS_REQUIRED=true php artisan test --compact` → **744 testes, 744 passaram, 0 falhas** (3774 asserções; container `sile-pgsql` healthy).

## Next Phase Readiness

- **08-08 (anexos / resolver de requisitos)**: o `DocumentRequirementResolver` une os `document_requirements` dos CNAEs da solicitação — os vínculos `viability_request_cnaes` (principal + complementares) gravados aqui são a entrada (`whereHas('cnaes')`).
- **08-09 (simulação HU-141)**: o `SimulacaoSolicitacaoService` itera os CNAEs da solicitação chamando o `ConsultaViabilidadeService`; este plano garante que a lista existe e que mudá-la invalida a simulação anterior (RN-005).
- **08-13 (UI)**: a tela do picker de CNAEs (multi-etapa) consome a rota `portal.solicitacoes.atividades` e a busca `portal.cnaes.search` (só ativos); o limite parametrizável deve ser comunicado na UI (degradação honesta ao exceder).

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
