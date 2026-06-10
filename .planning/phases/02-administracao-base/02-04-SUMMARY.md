---
phase: 02-administracao-base
plan: 04
subsystem: database
tags: [cnae, ibge-concla, import, upsert, crud, inertia-react, spatie-activitylog]

# Dependency graph
requires:
  - phase: 02-administracao-base (02-01)
    provides: "CSV oficial database/data/cnaes-subclasses-2-3.csv (1.331 subclasses) + permissões manter-cnaes/consultar-cnaes seedadas"
  - phase: 02-administracao-base (02-02)
    provides: "Settings banco+cache+fallback e chave ui.cnaes.per_page no catálogo/config"
  - phase: 01-identidade-acesso-e-auditoria-transversal
    provides: "AuditService (assinatura travada), HasAuditoria, 403 auditado global, factory states por papel, GestaoLayout e padrões de tela"
provides:
  - "Tabela cnaes populada com as 1.331 subclasses oficiais (import real idempotente, relatório auditado)"
  - "Model Cnae com code unique em dígitos, accessor formatted_code, hierarquia desnormalizada e flag active — pronto para as dimensões de risco da Fase 6"
  - "CnaeImportService reutilizável (re-import oficial preserva desativações administradas)"
  - "CRUD administrativo /gestao/cnaes com consulta granular separada da manutenção e busca server-side parametrizada"
affects: [fase-3 (vínculo empresa-CNAE consome cnaes.id), fase-6 (dimensões de risco/condicionantes no model), 02-08 (DatabaseSeeder + smoke)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Import oficial com relatório verificável: service testável + seeder que audita contadores e divergência com rules_version da fonte"
    - "Busca server-side com debounce 350ms + preserveState/replace + withQueryString (primeira tela com filtro do projeto)"
    - "Edição inline em tabela com Form Inertia por linha e campos imutáveis exibidos como texto"

key-files:
  created:
    - database/migrations/2026_06_10_144536_create_cnaes_table.php
    - app/Models/Cnae.php
    - database/factories/CnaeFactory.php
    - app/Services/CnaeImportService.php
    - database/seeders/CnaeSeeder.php
    - app/Http/Controllers/Gestao/CnaeController.php
    - app/Http/Requests/Gestao/StoreCnaeRequest.php
    - app/Http/Requests/Gestao/UpdateCnaeRequest.php
    - resources/js/pages/gestao/cnaes/index.tsx
    - tests/Feature/Cnae/CnaeImportTest.php
    - tests/Feature/Cnae/CnaeCrudTest.php
  modified:
    - routes/gestao.php

key-decisions:
  - "Upsert do import NÃO inclui 'active' nas colunas de update: re-import oficial atualiza denominações/hierarquia mas preserva desativações administradas pela HU-011"
  - "Divergência 1.331 × 1.332 (subclasse 9900-8/00) registrada no relatório auditado com explicação completa — nunca inserida silenciosamente; caminho administrativo é o CRUD (testado com o caso real)"
  - "UpdateCnaeRequest valida APENAS description e active (padrão CPF): código e hierarquia imutáveis na edição — valor enviado é ignorado, sem erro de validação"
  - "Auditoria em dois regimes: seed = log explícito único do relatório (1.331 activities por linha seriam ruído); CRUD manual = HasAuditoria com attribute_changes automático (CA-02)"
  - "Exclusão física permitida nesta fase (sem vínculos ainda); bloqueio por vínculo empresarial entra na Fase 3"

patterns-established:
  - "Importação-oficial: evento 'importacao-oficial' com properties=relatório e rules_version identificando a versão da fonte"
  - "Permissão por verbo: GET sob consultar-*, POST/PUT/DELETE sob manter-* em grupos de middleware separados"

# Metrics
duration: 22min
completed: 2026-06-10
---

# Phase 02 Plan 04: Manter CNAEs (HU-011) Summary

**Carga oficial real das 1.331 subclasses CNAE 2.3 (upsert idempotente com relatório auditado e divergência 9900-8/00 documentada) + CRUD administrativo com consulta granular, código imutável na edição, desativação lógica e busca server-side parametrizada**

## Performance

- **Duration:** 22 min
- **Started:** 2026-06-10T14:38:33Z
- **Completed:** 2026-06-10T15:00:27Z
- **Tasks:** 3
- **Files modified:** 12

## Accomplishments

- HU-011 completa com os 4 CAs cobertos por 16 feature tests (6 de import + 10 de CRUD), todos verdes
- Import oficial real e idempotente: 1.331 subclasses no banco, normalização para dígitos, rejeição de linha malformada sem abortar, re-import preserva `active` administrado
- Relatório do import auditado (activity `importacao-oficial`, rules_version `cnae-subclasses-2.3`) com a divergência da publicação oficial (1.332 citadas; 9900-8/00 ausente do arquivo de estrutura) — nada silencioso
- CRUD com consulta granular (analista/gestor consultam, não mantêm; cidadão 403 auditado), criação manual com normalização (testada com o caso real 9900-8/00), edição restrita a denominação/situação e exclusão auditada
- Tela administrativa com busca debounced que preserva foco e filtro na paginação, form recolhível de criação, edição inline e ações condicionadas à permissão `manter-cnaes`
- Suíte completa 159/159 verde no fechamento da wave 2; typecheck e build verdes

## Task Commits

Cada task seguiu o ciclo TDD com commits atômicos:

1. **Task 1: Import oficial (RED)** - `0d79fa1` (test)
2. **Task 1: Import oficial (GREEN)** - `89ac0c6` (feat)
3. **Task 2: CRUD administrativo (RED)** - `9416659` (test)
4. **Task 2: CRUD administrativo (GREEN)** - `ff1de42` (feat)
5. **Task 3: Tela administrativa** - `f28488a` (feat)

_REFACTOR das Tasks 1 e 2 sem commit próprio: pint não acusou pendências._

## Files Created/Modified

- `database/migrations/2026_06_10_144536_create_cnaes_table.php` - Tabela cnaes: code unique (7 dígitos), hierarquia desnormalizada, active indexado
- `app/Models/Cnae.php` - HasAuditoria + accessor formatted_code (DDDD-D/SS); PK surrogate id
- `database/factories/CnaeFactory.php` - Factory com state inactive()
- `app/Services/CnaeImportService.php` - Import real: validação de cabeçalho, trim, normalização, rejeições, upsert em chunks de 500 sem tocar active, relatório com divergência
- `database/seeders/CnaeSeeder.php` - Delega ao service e audita o relatório (rules_version cnae-subclasses-2.3)
- `app/Http/Controllers/Gestao/CnaeController.php` - index (busca+paginação via Settings), store, update, destroy
- `app/Http/Requests/Gestao/StoreCnaeRequest.php` - Normaliza código no prepareForValidation; valida formato/unicidade; mensagens pt-BR
- `app/Http/Requests/Gestao/UpdateCnaeRequest.php` - Rules apenas para description e active (código imutável)
- `routes/gestao.php` - 4 rotas: GET sob consultar-cnaes; POST/PUT/DELETE sob manter-cnaes
- `resources/js/pages/gestao/cnaes/index.tsx` - Tela com busca debounced, criação, edição inline, desativar/reativar, excluir com confirmação
- `tests/Feature/Cnae/CnaeImportTest.php` - 6 testes do import (contagem, normalização, idempotência, divergência, rejeição, seeder auditado)
- `tests/Feature/Cnae/CnaeCrudTest.php` - 10 testes dos CAs (listagem, busca, permissões, criação, validação, edição, desativação, auditoria, exclusão)

## Decisions Made

- **Upsert não toca `active`**: o array de colunas de update do upsert exclui a flag — desativação administrada sobrevive a re-imports oficiais (decisão central do plano, coberta por acceptance criteria)
- **Divergência como dado auditado**: relatório fixa `esperado_publicacao: 1332` e a explicação da 9900-8/00 (existe na CONCLA, ausente do arquivo de estrutura, fora do Decreto 32.636/2020); o teste de criação manual usa exatamente esse CNAE como caso real
- **Modelo pronto para a Fase 6 sem retrabalho**: PK id (FKs futuras), código não-PK, hierarquia desnormalizada do CSV — dimensões de risco/condicionantes entram como colunas/tabelas novas sem migração estrutural
- **Evidência fresca do seed em banco real (pgsql dev)**: `Cnae::count()` = 1331; activity id=1 com rules_version e relatório completo registrados

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Busca textual não pode virar `code LIKE '%'`**

- **Found during:** Task 2 (GREEN do CRUD)
- **Issue:** O snippet indicativo da pesquisa aplicava `where('code', 'like', $digits.'%')` incondicionalmente; com termo puramente textual (ex.: "restaurante"), `$digits` fica vazio e `LIKE '%'` casaria todos os registros — o próprio teste do plano (busca → 1 item) pegava o caso
- **Fix:** O branch de código só entra na query quando o termo contém dígitos; a busca textual fica restrita à denominação
- **Files modified:** app/Http/Controllers/Gestao/CnaeController.php
- **Verification:** `test_busca_filtra_por_codigo_ou_denominacao` verde (1 item para "restaurante" e para "0111-3")
- **Committed in:** ff1de42 (commit da Task 2)

**2. [Rule 1 - Bug] Confirmação de exclusão no onClick do botão, não no onSubmit do Form**

- **Found during:** Task 3 (tela)
- **Issue:** O plano sugeria `window.confirm` no onSubmit do `<Form>`, mas o Form do Inertia v3 define o próprio handler de submit DEPOIS de espalhar as props (verificado em node_modules/@inertiajs/react/dist/index.js) — um onSubmit custom é sobrescrito e o confirm nunca dispararia (feature de fachada)
- **Fix:** `window.confirm('Excluir este CNAE?')` no onClick do botão submit, com `preventDefault()` quando cancelado — impede o submit de forma confiável
- **Files modified:** resources/js/pages/gestao/cnaes/index.tsx
- **Verification:** typecheck/build verdes; comportamento de DOM padrão (preventDefault no click de type=submit cancela o submit)
- **Committed in:** f28488a (commit da Task 3)

---

**Total deviations:** 2 auto-fixed (2 bugs)
**Impact on plan:** Correções necessárias para a busca e a confirmação funcionarem de verdade. Nenhum scope creep.

## Issues Encountered

None — fora as deviações acima, o plano executou como escrito.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Cadastro estruturante de CNAEs operacional de ponta a ponta — Fase 3 (vínculo empresa-CNAE) e Fase 6 (dimensões de risco) têm a base pronta
- `CnaeSeeder` ainda NÃO está no `DatabaseSeeder` (pertence ao 02-08, conforme plano)
- Wave 2 fechada: suíte completa 159/159, typecheck/build verdes — wave 3 (02-05) liberada
- Navegação até /gestao/cnaes ainda é por URL direta; menu da gestão entra no 02-08

---
*Phase: 02-administracao-base*
*Completed: 2026-06-10*
