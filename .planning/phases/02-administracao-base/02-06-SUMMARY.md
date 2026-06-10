---
phase: 02-administracao-base
plan: 06
subsystem: auth
tags: [spatie-permission, roles, permissions, anti-lockout, inertia, react, auditoria]

# Dependency graph
requires:
  - phase: 02-01
    provides: 8 permissões granulares seedadas (manter-*/consultar-*/acessar-*/gerenciar-*) com seeder aditivo
  - phase: 02-04
    provides: padrão de tela administrativa e rotas com permissão por verbo (consultar × manter)
  - phase: 02-05
    provides: convenção FormRequest::after() para validações de negócio e auditoria explícita com target nas properties
provides:
  - CRUD de perfis (roles spatie) com atribuição granular de permissões por funcionalidade (HU-013)
  - Roles::STRUCTURAL como fonte de verdade dos papéis protegidos no código
  - Três proteções anti-lockout testadas (rename, exclusão, acessar-gestao do administrador)
  - Tela /gestao/perfis com checkboxes agrupadas por prefixo de permissão
affects: [02-07, 02-08, fase-12-auditoria, qualquer-fase-com-permissao-nova]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Papéis estruturais protegidos por constante no código (Roles::STRUCTURAL) validada em FormRequest::after()"
    - "Seeder aditivo (givePermissionTo) × update da UI exato (syncPermissions) — intenções diferentes, métodos diferentes"
    - "Sem reset manual de cache do spatie v8: create/syncPermissions/delete resetam automaticamente"

key-files:
  created:
    - app/Support/Roles.php
    - app/Http/Controllers/Gestao/RoleController.php
    - app/Http/Requests/Gestao/StoreRoleRequest.php
    - app/Http/Requests/Gestao/UpdateRoleRequest.php
    - tests/Feature/Roles/ManageRolesTest.php
    - resources/js/pages/gestao/perfis/index.tsx
  modified:
    - routes/gestao.php

key-decisions:
  - "Anti-lockout em três camadas no backend (FormRequest::after + ValidationException no destroy); a UI apenas comunica as restrições"
  - "permissoes_depois auditada a partir do validated (conjunto exato sincronizado), permissoes_antes do pluck pré-sync"
  - "Stub da tela criado na Task 1 porque inertia.testing.ensure_pages_exist=true exige o arquivo para o assert de component"

patterns-established:
  - "Proteção de dados estruturais: constante no código + validação de negócio em after() + teste nomeado por proteção"

# Metrics
duration: 9min
completed: 2026-06-10
---

# Phase 2 Plan 06: Perfis com permissões granulares e anti-lockout Summary

**CRUD de perfis spatie com permissões granulares de efeito imediato, papéis estruturais protegidos contra rename/exclusão, acessar-gestao intocável no administrador e auditoria antes/depois — HU-013 com os 4 CAs cobertos por 12 testes**

## Performance

- **Duration:** 9 min
- **Started:** 2026-06-10T16:05:46Z
- **Completed:** 2026-06-10T16:14:35Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments

- HU-013 completa: administrador cria perfis (ex.: "fiscal") com permissões granulares por funcionalidade, sem deploy, com efeito imediato comprovado por teste (perfil novo consulta CNAEs mas recebe 403 ao tentar manter).
- Três proteções anti-lockout implementadas no backend e provadas por teste:
  1. **Rename de papel estrutural bloqueado** — `test_papel_estrutural_nao_pode_ser_renomeado` (PUT no cidadao com name "municipe" → erro em `name`, nome inalterado).
  2. **Exclusão de papel estrutural bloqueada** — `test_papel_estrutural_nao_pode_ser_excluido` (DELETE no administrador → erro em `role`, papel preservado).
  3. **acessar-gestao não removível do administrador** — `test_nao_remove_acessar_gestao_do_administrador` (PUT sem acessar-gestao → erro em `permissions` citando a permissão, conjunto inalterado).
  Complemento CA-03: exclusão com usuários vinculados bloqueada — `test_exclusao_bloqueada_com_usuarios_vinculados` (mensagem "usuários vinculados").
- Auditoria explícita no log `perfis` (Role do spatie é model do vendor, sem HasAuditoria): `perfil-criado`, `perfil-atualizado` (com `permissoes_antes`/`permissoes_depois`) e `perfil-excluido`; 403 auditado para quem não tem `manter-perfis` (CA-04).
- Tela `/gestao/perfis`: cards por perfil com badge "Estrutural", contagem de usuários e chips de permissões; criação e edição com checkboxes agrupadas por prefixo (Manter/Consultar/Acessar/Gerenciar); nome readOnly nos estruturais; checkbox de acessar-gestao do administrador desabilitada + enviada por hidden; exclusão desabilitada com vínculos.
- Fechamento da wave 4: suíte completa 180/180 verde (168 anteriores + 12 novos), typecheck e build verdes.

## Task Commits

Each task was committed atomically:

1. **Task 1: CRUD de perfis com proteções estruturais e anti-lockout (TDD)**
   - RED: `dde4e30` (test(02-06): adiciona testes falhando para crud de perfis)
   - GREEN: `afbbedc` (feat(02-06): implementa crud de perfis com protecoes anti-lockout)
   - REFACTOR: não necessário (pint sem alterações)
2. **Task 2: Tela de perfis com permissões agrupadas em checkboxes** - `9213625` (feat(02-06): adiciona tela de perfis com permissoes agrupadas)

## Files Created/Modified

- `app/Support/Roles.php` - Constante `STRUCTURAL` com os 4 papéis que o código referencia (fonte de verdade única)
- `app/Http/Controllers/Gestao/RoleController.php` - index/store/update/destroy com auditoria explícita e bloqueios de destroy via ValidationException
- `app/Http/Requests/Gestao/StoreRoleRequest.php` - name único no guard web + permissions existentes
- `app/Http/Requests/Gestao/UpdateRoleRequest.php` - mesmas rules + after() com rename de estrutural proibido e anti-lockout do administrador
- `routes/gestao.php` - grupo `permission:manter-perfis` com as 4 rotas perfis.*
- `resources/js/pages/gestao/perfis/index.tsx` - tela com agrupamento por prefixo, comunicação das proteções e exclusão condicionada
- `tests/Feature/Roles/ManageRolesTest.php` - 12 testes cobrindo CA-01 a CA-04

## Decisions Made

- **Seeder aditivo × update da UI (justificativa registrada):** o `RolesAndPermissionsSeeder` usa `givePermissionTo` (aditivo) porque re-seed em produção não pode desfazer ajustes de permissão feitos pelo administrador via HU-013; o update da UI usa `syncPermissions` (conjunto exato) porque a intenção do admin é exatamente o que está marcado na tela — remover o que desmarcou faz parte do contrato. Mesmo mecanismo, intenções opostas, métodos distintos.
- **Sem `forgetCachedPermissions` manual:** spatie v8 reseta o cache nos métodos built-in (`create`, `syncPermissions`, `delete`) — reset manual seria redundância (Padrão 7 do RESEARCH). Verificado por acceptance criteria (grep = 0 ocorrências no controller).
- **Regras de negócio só no backend:** a UI desabilita/oculta controles (checkbox travada, botão de excluir desabilitado, nome readOnly) apenas como comunicação; toda proteção real está em FormRequest/Controller e coberta por teste.
- **`permissoes_depois` do validated:** após `syncPermissions`, o conjunto final é exatamente o validado — evita reload do relacionamento só para auditar.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Stub da tela criado na Task 1 para o resolver de páginas do Inertia**

- **Found during:** Task 1 (GREEN — teste de listagem)
- **Issue:** `inertia.testing.ensure_pages_exist` é `true` no projeto; o assert `component('gestao/perfis/index')` falhava porque o arquivo da página só seria criado na Task 2
- **Fix:** criado `resources/js/pages/gestao/perfis/index.tsx` mínimo (layout + Head) na Task 1; a Task 2 o substituiu pela tela completa
- **Files modified:** resources/js/pages/gestao/perfis/index.tsx
- **Verification:** 12/12 verdes na Task 1; tela completa com typecheck/build verdes na Task 2
- **Committed in:** afbbedc (Task 1), substituído em 9213625 (Task 2)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** nenhum desvio de escopo — o arquivo já estava previsto na Task 2; apenas a ordem de criação foi antecipada com conteúdo mínimo.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Wave 4 fechada com a suíte completa verde (180/180). Pronto para a wave 5 (02-07 — HU-014: tela de parâmetros, efeito sem deploy, toggle real e histórico).
- Perfis novos criados pela UI ficam imediatamente utilizáveis pelo vínculo de papel da HU-012 (tela de usuários lista roles dinamicamente).
- O 02-08 (navegação da gestão) deve incluir o link para /gestao/perfis condicionado à permissão manter-perfis.

---
*Phase: 02-administracao-base*
*Completed: 2026-06-10*
