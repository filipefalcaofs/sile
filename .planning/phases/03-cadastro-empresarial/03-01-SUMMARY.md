---
phase: 03-cadastro-empresarial
plan: 01
subsystem: database
tags: [cnpj, validation, eloquent, migrations, factories, laravel, inertia, parameters]

requires:
  - phase: 01-fundacao
    provides: Rule ValidCpf (padrão de rule), HasAuditoria, Settings/Parameter/ParameterSeeder, CurrentRepresentation
  - phase: 02-cnaes
    provides: Model Cnae + tabela cnaes (FK alvo), CnaeController, padrão de testes de CRUD, pendência de exclusão herdada
provides:
  - Rule ValidCnpj (módulo 11 ASCII-48, numérico e alfanumérico)
  - Schema companies/company_user/company_cnae com constraints
  - Models Company e CompanyUser com HasAuditoria e relações
  - Enums CompanySource e CompanyLinkRole
  - Factories CompanyFactory e CompanyUserFactory com states
  - 3 parâmetros novos no catálogo HU-014 (cnpj_lookup, base_url, companies.per_page)
  - Bloqueio de exclusão de CNAE vinculado a empresas (app + banco)
  - Canal flash.error compartilhado no Inertia
affects: [03-02-cnpj-lookup, 03-03-redesim-import, 03-04-company-crud, 03-05-company-cnaes, 03-06-portal-telas]

tech-stack:
  added: []
  patterns:
    - "Validação de CNPJ por módulo 11 ASCII-48 (ord(char)-48) cobrindo numérico e alfanumérico de uma vez"
    - "Pivot com nome explícito company_cnae (Eloquent inferiria cnae_company)"
    - "Unicidade condicional na aplicação + defesa restrictOnDelete no banco"
    - "Accessor de formatação posicional (regex por posição, não por classe de dígito)"

key-files:
  created:
    - app/Rules/ValidCnpj.php
    - app/Models/Company.php
    - app/Models/CompanyUser.php
    - app/Enums/CompanySource.php
    - app/Enums/CompanyLinkRole.php
    - database/migrations/2026_06_12_035745_create_companies_table.php
    - database/migrations/2026_06_12_035746_create_company_user_table.php
    - database/migrations/2026_06_12_035746_create_company_cnae_table.php
    - database/factories/CompanyFactory.php
    - database/factories/CompanyUserFactory.php
    - tests/Unit/Rules/ValidCnpjTest.php
    - tests/Feature/Companies/CompanyFoundationTest.php
  modified:
    - app/Models/Cnae.php
    - config/sile.php
    - database/seeders/ParameterSeeder.php
    - app/Http/Controllers/Gestao/CnaeController.php
    - app/Http/Middleware/HandleInertiaRequests.php
    - resources/js/layouts/gestao-layout.tsx
    - resources/js/types/index.d.ts

key-decisions:
  - "ValidCnpj nasce compatível com CNPJ alfanumérico (módulo 11 ASCII-48) antecipando julho/2026"
  - "Pivot company_cnae precisa de nome explícito no belongsToMany (Eloquent inferiria cnae_company)"
  - "Constantes técnicas do lookup (timeout/retries/cache_ttl) ficam só em config/sile.php, nunca no registry"
  - "Bloqueio de exclusão de CNAE: verificação amigável na aplicação + restrictOnDelete como defesa no banco"

patterns-established:
  - "Algoritmo de pesos [6,5,4,3,2,9,8,7,6,5,4,3,2] com array_slice(13-position) para os dois DVs"
  - "flash.error compartilhado e renderizado via Alert variant=error no layout"

requirements-completed: [HU-021, HU-023]

duration: 22min
completed: 2026-06-12
---

# Phase 3 Plan 01: Fundação do Cadastro Empresarial Summary

**Rule ValidCnpj (módulo 11 ASCII-48, numérico e alfanumérico), schema completo companies/company_user/company_cnae com models/enums/factories, 3 parâmetros novos no catálogo HU-014 e bloqueio de exclusão de CNAE vinculado herdado da Fase 2.**

## Performance

- **Duration:** ~22 min
- **Completed:** 2026-06-12
- **Tasks:** 3
- **Files modified:** 19 (12 criados, 7 modificados)

## Accomplishments
- `ValidCnpj` valida CNPJ numérico (`00000000000191`) e alfanumérico oficial RFB (`12ABC34501DE35`) com o mesmo algoritmo módulo 11 sobre `ord(char)-48`, rejeitando DV errado, tamanho errado, repetição uniforme e letra nos dígitos verificadores.
- Schema empresarial migrável: `companies` (cnpj string(14) unique), `company_user` (vínculo com ciclo de vida) e `company_cnae` (pivot com `restrictOnDelete` em `cnae_id` e `unique(company_id, cnae_id)`).
- Models `Company`/`CompanyUser` com `HasAuditoria`, relações `cnaes`/`primaryCnae`/`links`/`activeLinks` e accessor `formatted_cnpj`; `Cnae` ganhou `companies()`.
- Factories geram CNPJ válido pelo mesmo algoritmo de pesos e states `fromRedesim()`/`withPrimaryCnae()`/`responsavel()`/`procurador()`/`ended()`.
- Catálogo HU-014 passou de 11 para 14 parâmetros (`features.cnpj_lookup`, `integrations.cnpj_lookup.base_url` com `requires_connection_test`, `ui.companies.per_page`), defaults espelhados em `config/sile.php`.
- Pendência da Fase 2 resolvida: `CnaeController::destroy` bloqueia exclusão de CNAE vinculado com mensagem pt-BR visível (canal `flash.error` + `Alert`), com `restrictOnDelete` como defesa no banco.

## Task Commits

Each task was committed atomically:

1. **Task 1: Rule ValidCnpj (TDD)** - `da4d171` (feat)
2. **Task 2: Schema + models + enums + factories (TDD)** - `a2c43ee` (feat)
3. **Task 3: Parâmetros HU-014 + bloqueio de CNAE + flash.error (TDD)** - `6335514` (feat)

_Tarefas seguiram TDD (RED → GREEN → REFACTOR) em commit único cada, com o passo RED verificado antes do GREEN._

## Files Created/Modified
- `app/Rules/ValidCnpj.php` - Validação módulo 11 ASCII-48 (numérico + alfanumérico)
- `app/Models/Company.php` - Empresa com HasAuditoria, relações e accessor formatted_cnpj
- `app/Models/CompanyUser.php` - Vínculo usuário-empresa com ciclo de vida
- `app/Models/Cnae.php` - Adicionada relação companies() (pivot company_cnae)
- `app/Enums/CompanySource.php`, `app/Enums/CompanyLinkRole.php` - Enums com labels pt-BR
- `database/migrations/*_create_companies_table.php` - cnpj string(14) unique + dados cadastrais
- `database/migrations/*_create_company_user_table.php` - vínculo started_at/ended_at + index(company_id, user_id)
- `database/migrations/*_create_company_cnae_table.php` - restrictOnDelete + unique(company_id, cnae_id)
- `database/factories/CompanyFactory.php`, `CompanyUserFactory.php` - geração de CNPJ válido e states
- `config/sile.php` - defaults de cnpj_lookup (features/integrations) e ui.companies.per_page
- `database/seeders/ParameterSeeder.php` - 3 chaves novas no catálogo (total 14)
- `app/Http/Controllers/Gestao/CnaeController.php` - destroy bloqueia CNAE vinculado
- `app/Http/Middleware/HandleInertiaRequests.php` - flash.error compartilhado
- `resources/js/layouts/gestao-layout.tsx` - exibição de flash.error via Alert
- `resources/js/types/index.d.ts` - tipo flash.error
- `tests/Unit/Rules/ValidCnpjTest.php`, `tests/Feature/Companies/CompanyFoundationTest.php` - testes da fundação
- `tests/Feature/Seeders/ParameterSeederTest.php`, `tests/Feature/Cnae/CnaeCrudTest.php` - testes ampliados

## Assinaturas e nomes finais

- **ValidCnpj:** `validate(string $attribute, mixed $value, Closure $fail): void` — pesos `[6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]` com `array_slice($weights, 13 - $position)` para os DVs nas posições 12 e 13.
- **Migrations:** `2026_06_12_035745_create_companies_table.php`, `2026_06_12_035746_create_company_user_table.php`, `2026_06_12_035746_create_company_cnae_table.php`.
- **Catálogo de parâmetros:** 14 chaves nos grupos `features`, `integracoes`, `seguranca`, `ui`.

## Decisions Made
- Seguido o alerta do plano: usado o array de 13 pesos com offset `13 - $position` (NÃO o snippet de 12 pesos do RESEARCH, que produz slice errado para o 2º DV). Os vetores oficiais RFB no teste comprovam a forma correta.
- `belongsToMany` para o pivot exige nome explícito `company_cnae` em ambos os lados — Eloquent inferiria `cnae_company` (ordem alfabética dos models).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Nome explícito do pivot company_cnae no belongsToMany**
- **Found during:** Task 2 (Schema + models)
- **Issue:** Eloquent infere o nome do pivot pela ordem alfabética dos models (`cnae_company`), mas a tabela criada é `company_cnae` — os testes de relação/attach falhavam com "no such table: cnae_company".
- **Fix:** Passado o nome da tabela explicitamente em `Company::cnaes()` e `Cnae::companies()`: `belongsToMany(..., 'company_cnae')`.
- **Files modified:** app/Models/Company.php, app/Models/Cnae.php
- **Verification:** CompanyFoundationTest verde (6 testes).
- **Committed in:** a2c43ee (Task 2 commit)

**2. [Rule 1 - Bug] Atualização da contagem de parâmetros no DatabaseSeederTest**
- **Found during:** Task 3 (verificação escopada da fundação)
- **Issue:** `DatabaseSeederTest` (não listado nos arquivos do plano) também asseverava 11 parâmetros em dois testes; as 3 chaves novas quebrariam a suíte escopada.
- **Fix:** Atualizadas as duas asserções de `11` para `14`, espelhando o ajuste do `ParameterSeederTest`.
- **Files modified:** tests/Feature/Seeders/DatabaseSeederTest.php
- **Verification:** Suíte escopada (tests/Unit/Rules, Companies, Seeders, Cnae) verde — 48 testes.
- **Committed in:** 6335514 (Task 3 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 bug)
**Impact on plan:** Ambos necessários para correção. Sem scope creep — o ajuste do DatabaseSeederTest é consequência direta dos parâmetros novos do plano.

## Issues Encountered
- Nenhum além das duas deviations acima.

## User Setup Required
None - nenhuma configuração de serviço externo necessária nesta fundação. O provider real de CNPJ e seu teste de conexão são dos planos seguintes / Fase 13.

## Next Phase Readiness
- Base pronta para os planos 03-02 (lookup CNPJ), 03-03 (import REDESIM), 03-04 (CRUD), 03-05 (CNAEs) e 03-06 (telas do portal): rule de validação, schema, models com auditoria/relações, factories e parâmetros administráveis já existem.
- `features.cnpj_lookup` default `true` e `integrations.cnpj_lookup.base_url` apontando para BrasilAPI — prontos para o provider real do plano 03-02.

---
*Phase: 03-cadastro-empresarial*
*Completed: 2026-06-12*

## Self-Check: PASSED

- Todos os 13 arquivos-chave criados existem no disco.
- Os 3 commits de tarefa (`da4d171`, `a2c43ee`, `6335514`) existem no histórico.
