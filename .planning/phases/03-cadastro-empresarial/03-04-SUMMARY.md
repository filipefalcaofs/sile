---
phase: 03-cadastro-empresarial
plan: 04
subsystem: portal
tags: [companies, policy, representation, transaction, server-driven, pagination, audit, laravel, inertia]

requires:
  - phase: 03-cadastro-empresarial
    plan: 01
    provides: Models Company/CompanyUser, Enums CompanySource/CompanyLinkRole, Rule ValidCnpj, factories, parâmetro ui.companies.per_page
  - phase: 03-cadastro-empresarial
    plan: 02
    provides: Settings::enabled('cnpj_lookup') (toggle do botão Buscar CNPJ), rota empresas/consultar-cnpj
  - phase: 01-fundacao
    provides: CurrentRepresentation/ResolveRepresentation ([01-07] acting_for), HasAuditoria, Settings, portal auth+verified+lgpd.accepted
provides:
  - CompanyPolicy view/update/manageCnaes/endLink sobre o usuário efetivo (representação integrada)
  - CompanyController index/create/store (store transacional empresa+vínculo; index server-driven)
  - StoreCompanyRequest (normalização CNPJ/telefone/CEP + ValidCnpj + unique pt-BR)
  - Company::countForUser (contagem reutilizável para o painel do cidadão)
  - Rotas portal.empresas.index/create/store
  - Shape das props do index (contrato da tela 03-07)
affects: [03-05-company-cnaes (Gate manageCnaes + rota empresas/{company}), 03-07-portal-telas (consome props do index e create)]

tech-stack:
  added: []
  patterns:
    - "effectiveUser() = CurrentRepresentation::grantor() ?? user — replicado na policy e no controller ([01-07])"
    - "store transacional: Company::create + links()->create na mesma DB::transaction"
    - "Listagem server-driven (whitelists SORTABLE_COLUMNS/PER_PAGE_OPTIONS, whereLike caseSensitive:false, paginação parametrizada) — padrão CnaeController@index"
    - "Eager loading anti-N+1 com constraint no pivot (wherePivot is_primary) e no vínculo do usuário"

key-files:
  created:
    - app/Policies/CompanyPolicy.php
    - app/Http/Requests/Portal/StoreCompanyRequest.php
    - tests/Feature/Companies/CompanyRegistrationTest.php
    - tests/Feature/Companies/MyCompaniesTest.php
  modified:
    - app/Http/Controllers/Portal/CompanyController.php
    - app/Models/Company.php
    - routes/portal.php

key-decisions:
  - "Asserções Inertia SEM ->component() até a UI existir (03-07): a checagem de componente exige o arquivo de página em disco, que só nasce na wave 6"
  - "Index implementado junto com create/store (mesmo arquivo) e commitado na Task 1; a Task 2 acrescentou a suíte que o exercita (commit test)"
  - "Teste de representação nos dois sentidos usa flushSession() entre requests — o test client retém acting_procuration_id; sem flush o procurador continuaria 'em nome de'"

requirements-completed: [HU-023, HU-027]

duration: 14min
completed: 2026-06-12
---

# Phase 3 Plan 04: Cadastro e Consulta de Empresas (HU-023/HU-027) Summary

**Backend de HU-023 (cadastrar empresa) e HU-027 (consultar empresas vinculadas): `CompanyPolicy` integrada à representação "em nome de" ([01-07]), `store` transacional que cria empresa + vínculo responsável ativo na mesma transação (em representação, PARA o representado), e listagem "Minhas empresas" server-driven (busca case-insensitive, ordenação, paginação parametrizada) com props prontas para a tela do 03-07.**

## Performance

- **Duration:** ~14 min
- **Completed:** 2026-06-12
- **Tasks:** 2
- **Files modified:** 7 (4 criados, 3 modificados)

## Accomplishments
- `CompanyPolicy` com `effectiveUser()` (`CurrentRepresentation::grantor() ?? $user`): `view` aceita vínculo ativo OU encerrado (histórico visível); `update`/`manageCnaes`/`endLink` exigem vínculo ATIVO.
- `store` cria `Company` (`source = manual`) + `CompanyUser` (`responsavel`, `started_at = now()`) na MESMA `DB::transaction`, em nome do usuário efetivo — em representação, o vínculo nasce para o REPRESENTADO (`user_id = grantor`), nunca para o procurador.
- `StoreCompanyRequest` normaliza CNPJ (uppercase, sem máscara — cobre alfanumérico de julho/2026), telefone e CEP (só dígitos) em `prepareForValidation`; `unique('companies','cnpj')` bloqueia duplicidade com a mensagem exata `Já existe empresa cadastrada com este CNPJ.` (CA-03).
- Auditoria automática (`HasAuditoria`) gera `created` da `Company` e do vínculo, enriquecida com `acting_for_user_id = grantor` pelo `RecordActivityAction` em representação (CA-02 + [01-07]).
- Listagem `index` server-driven: escopo por `whereHas('links', user efetivo)`, busca por razão social/fantasia (`whereLike caseSensitive:false` — fix PostgreSQL [2.4]) ou prefixo de CNPJ, ordenação whitelistada e paginação `ui.companies.per_page` parametrizada; eager loading anti-N+1 (`wherePivot('is_primary', true)` + vínculo do usuário).
- `Company::countForUser` exposto em `totalCompanies` — contagem reutilizável que o painel do cidadão consome na evolução.
- Visitante redirecionado para `/portal/login` em todas as rotas (CA-04).

## Shape das props do index (contrato da tela 03-07)

```jsonc
{
  "companies": {                       // paginator Laravel serializado
    "data": [
      {
        "id": 1,
        "legal_name": "Padaria Central",
        "trade_name": "Padaria",
        "formatted_cnpj": "00.000.000/0001-91",
        "source": { "value": "manual", "label": "Cadastro manual" },
        "primary_cnae": { "formatted_code": "5611-2/01", "description": "Restaurantes..." } | null,
        "link": {
          "role": "responsavel",
          "role_label": "Responsável",
          "active": true,               // ended_at === null
          "started_at": "2026-06-12",
          "ended_at": null
        } | null
      }
    ],
    "current_page": 1, "per_page": 15, "from": 1, "to": 15, "total": 42, "last_page": 3,
    "links": [ ... ]                    // chaves top-level consumidas pelo ui/pagination.tsx
  },
  "filters": { "search": "", "sort": "legal_name", "direction": "asc", "per_page": 15 },
  "perPageOptions": [10, 15, 25, 50],
  "totalCompanies": 42
}
```

Props do `create`: `{ "cnpjLookupEnabled": true }` (toggle `features.cnpj_lookup` — botão "Buscar CNPJ" desabilitado quando false, [03-02]).

## Assinaturas da policy

- `view(User, Company): bool` — qualquer vínculo do usuário efetivo (ativo ou encerrado).
- `update(User, Company): bool` — vínculo ATIVO (`whereNull('ended_at')`).
- `manageCnaes(User, Company): bool` — igual a `update` (consumida no 03-05).
- `endLink(User, Company): bool` — igual a `update` (consumida no 03-06/HU-028).
- privado `effectiveUser(User): User` → `app(CurrentRepresentation::class)->grantor() ?? $user`.

## Task Commits

1. **Task 1: CompanyPolicy + store transacional + index/create (TDD, HU-023)** - `619c106` (feat)
2. **Task 2: Suíte MyCompaniesTest da listagem server-driven (HU-027)** - `5e1c44f` (test)

_TDD: RED verificado (CompanyRegistrationTest 0/8 antes da implementação) → GREEN (8/8) → REFACTOR (pint). O `index` foi implementado junto na Task 1 (mesmo arquivo, evita RED/GREEN sujo entre tarefas do mesmo plano); a Task 2 acrescentou os 9 testes que o exercitam — commit `test`._

## Files Created/Modified
- `app/Policies/CompanyPolicy.php` - view/update/manageCnaes/endLink sobre o usuário efetivo
- `app/Http/Requests/Portal/StoreCompanyRequest.php` - normalização + ValidCnpj + unique pt-BR
- `app/Http/Controllers/Portal/CompanyController.php` - index/create/store (transacional + server-driven)
- `app/Models/Company.php` - método estático countForUser
- `routes/portal.php` - rotas empresas.index/create/store (literais antes de empresas/{company})
- `tests/Feature/Companies/CompanyRegistrationTest.php` - 8 testes HU-023
- `tests/Feature/Companies/MyCompaniesTest.php` - 9 testes HU-027

## Decisions Made
- **Inertia sem `->component()`:** as páginas React (`portal/empresas/index`, `cadastrar`) só nascem no 03-07. A checagem de componente exige o arquivo em disco, então os asserts usam `has()/where()` sobre as props. **Pendência para o 03-07:** adicionar a verificação visual/componente quando as telas existirem.
- **Index na Task 1:** implementado junto com create/store no mesmo controller para um ciclo RED/GREEN limpo; a Task 2 cobriu-o com a suíte (commit `test`, não `feat`).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] flushSession entre os dois lados da representação no teste**
- **Found during:** Task 2 (test_procurador_em_representacao_ve_empresas_do_representado)
- **Issue:** O test client do Laravel retém a sessão entre requests; após `withSession(['acting_procuration_id' => ...])` o segundo `get` (sem representação) ainda enxergava a empresa do outorgante — o middleware mantinha o "em nome de".
- **Fix:** `$this->flushSession()` antes da segunda request, simulando o procurador sem representação ativa (espelha o comportamento real, onde a sessão não carregaria o id).
- **Files modified:** tests/Feature/Companies/MyCompaniesTest.php
- **Verification:** MyCompaniesTest 9/9 verde.
- **Committed in:** 5e1c44f (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 bug de teste). Sem scope creep.

## Issues Encountered
- Nenhum além da deviation acima.

## User Setup Required
None - nenhuma configuração de serviço externo. As rotas usam o gate `lgpd.accepted` + `ResolveRepresentation` já existentes.

## Next Phase Readiness
- **03-05 (CNAEs da empresa):** `Gate manageCnaes` pronto; a rota `empresas/{company}` deve vir DEPOIS das literais (comentário já no `routes/portal.php`).
- **03-07 (telas do portal):** o shape das props do `index` e `create` é o contrato direto da DataTable e da página de cadastro; adicionar a verificação de `->component()` nos testes quando as páginas existirem.

## Verification
- `php artisan test --compact tests/Feature/Companies` — 50 testes verdes (grupo da fase até aqui).
- `php artisan test --compact --filter=CompanyRegistrationTest` — 8 verdes; `--filter=MyCompaniesTest` — 9 verdes.
- `php artisan route:list --path=portal/empresas` — index/cadastrar/store/consultar-cnpj.
- `vendor/bin/pint --dirty` — sem pendências.

---
*Phase: 03-cadastro-empresarial*
*Completed: 2026-06-12*

## Self-Check: PASSED

- Todos os 6 arquivos-chave (4 criados + 3 modificados) existem no disco.
- Os 2 commits de tarefa (`619c106`, `5e1c44f`) existem no histórico.
