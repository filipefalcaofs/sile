---
phase: 02-administracao-base
plan: 05
subsystem: admin
tags: [spatie-permission, spatie-activitylog, inertia-react, lgpd, anti-lockout]

# Dependency graph
requires:
  - phase: 02-01
    provides: permissões manter-usuarios e consultar-acessos-de-qualquer-conta seedadas
  - phase: 02-03
    provides: mecanismo real de inativação (inactivated_at + Fortify::authenticateUsing + EnsureUserIsActive)
  - phase: 02-04
    provides: padrão de listagem administrativa com busca debounced e paginação parametrizada
provides:
  - Gestão de usuários (HU-012) navegável em /gestao/usuarios com os 4 CAs cobertos
  - Inativação/reativação pela interface com efeito real no login e auditoria explícita
  - Vínculo de papel via syncRoles com auditoria de papel anterior/novo
  - Anti-lockout: auto-inativação bloqueada com mensagem pt-BR
  - Link "Acessos" por conta — concern da Fase 1 (tela órfã gestao/acessos) RESOLVIDO
affects: [02-06 (perfis aparecem no select de papel), 02-08 (smoke humano da tela)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Auditoria explícita de usuários (log 'usuarios'): inactivated_at e papéis ficam fora do diff automático do HasAuditoria (só fillable)"
    - "CPF mascarado ***.***.***-DD no transform do paginator — dado sensível nunca sai em claro na listagem (LGPD)"
    - "FormRequest::after() para validação de invariante de rota (auto-inativação) com erro em chave própria ('user')"

key-files:
  created:
    - app/Http/Controllers/Gestao/UserManagementController.php
    - app/Http/Requests/Gestao/ToggleUserActivationRequest.php
    - app/Http/Requests/Gestao/UpdateUserRoleRequest.php
    - resources/js/pages/gestao/usuarios/index.tsx
    - tests/Feature/Users/ManageUsersTest.php
  modified:
    - routes/gestao.php

key-decisions:
  - "Toggle único de inativação (PUT usuarios/{user}/inativacao alterna pelo estado atual) — evita rota dupla e mantém o anti-lockout num único FormRequest"
  - "Select de papel oferece todos os papéis existentes (Role::pluck) — papéis customizados da HU-013 aparecem automaticamente sem retoque"
  - "cpf_masked calculado no transform do controller (substr dos 2 verificadores) — o front nunca recebe o CPF em claro"

patterns-established:
  - "Auditoria explícita com target_user_id nas properties para ações administrativas sobre contas de terceiros"

# Metrics
duration: 11min
completed: 2026-06-10
---

# Phase 2 Plan 05: Gestão de Usuários (HU-012) Summary

**Gestão de usuários navegável com inativação de efeito real no login (mecanismo 02-03), papel auditado com anterior/novo, anti-lockout e link por conta para o histórico de acessos — fechando o concern da Fase 1**

## Performance

- **Duration:** 11 min
- **Started:** 2026-06-10T15:05:50Z
- **Completed:** 2026-06-10T15:16:52Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments

- HU-012 com os 4 CAs cobertos por 9 feature tests novos (15/15 verdes em `tests/Feature/Users`; suíte completa 168/168).
- Inativação/reativação pela interface consome o mecanismo real do 02-03: teste ponta a ponta prova que a conta inativada via PUT não loga mais (logout explícito do admin antes do POST /login para exercitar o fluxo real).
- Vínculo de papel via `syncRoles` (um papel por conta) com auditoria explícita `papel-alterado` registrando `papel_anterior` e `papel_novo`.
- Anti-lockout (CA-03): `ToggleUserActivationRequest::after()` bloqueia auto-inativação com "Você não pode inativar a própria conta."
- CA-04 granular: rotas sob `permission:manter-usuarios`; gestor (que tem `acessar-gestao`) recebe 403 auditado (`log_name=seguranca`, `result=bloqueado`).
- LGPD: a listagem expõe apenas `cpf_masked` (`***.***.***-DD`) — teste asserta a ausência da chave `cpf` no payload Inertia.
- **Concern da Fase 1 "[Fase 2] Tela gestao/acessos alcançável apenas por URL direta" RESOLVIDO**: cada linha da listagem linka `/gestao/acessos/{user}` — o histórico de acessos de qualquer conta agora é alcançável por navegação.

## Task Commits

Each task was committed atomically:

1. **Task 1 (RED): testes falhando da gestão de usuários** - `6eb50ae` (test)
2. **Task 1 (GREEN): controller, requests e rotas** - `305a179` (feat)
3. **Task 2: tela de usuários com link para acessos** - `cde29da` (feat)

## Files Created/Modified

- `app/Http/Controllers/Gestao/UserManagementController.php` - index (busca nome/email + paginação `ui.users.per_page`), toggleActivation (forceFill auditado), updateRole (syncRoles auditado)
- `app/Http/Requests/Gestao/ToggleUserActivationRequest.php` - bloqueio de auto-inativação via `after()` com mensagem pt-BR
- `app/Http/Requests/Gestao/UpdateUserRoleRequest.php` - `role` validado contra `Rule::exists('roles','name')` no guard web
- `routes/gestao.php` - grupo `permission:manter-usuarios` com 3 rotas (index, papel.update, inativacao.update); rotas de cnaes/acessos intactas
- `resources/js/pages/gestao/usuarios/index.tsx` - busca debounced 350ms com `preserveState`, badges de papel/situação, select de papel, Inativar/Reativar com confirm pt-BR, link Acessos por linha
- `tests/Feature/Users/ManageUsersTest.php` - 9 testes cobrindo CA-01 a CA-04 + ponta a ponta com o bloqueio de login do 02-03

## Decisions Made

- Toggle único de inativação: a mesma rota PUT alterna ativa↔inativa conforme o estado atual — anti-lockout centralizado num único FormRequest.
- `cpf_masked` montado no servidor; o objeto `User` nunca é serializado direto para o Inertia (transform explícito por item).
- Auditoria explícita (não HasAuditoria) para inativação e papel — `inactivated_at` está fora do fillable e papéis são relação, então o diff automático não os cobriria (RESEARCH Padrão 4).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. Único ponto de sequenciamento esperado: o teste de listagem asserta o componente `gestao/usuarios/index` (config `inertia.testing.ensure_pages_exist`), então o GREEN completo do Task 1 só fecha quando a página do Task 2 existe — mesmo sequenciamento do 02-04 (backend commitado primeiro, tela na sequência, suíte verde no fechamento da task seguinte).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Wave 3 completa. Prontos para a wave 4: 02-06 (HU-013 perfis — o select de papel da tela de usuários já consome papéis customizados automaticamente) e 02-07 (HU-014 tela de parâmetros).
- O STATE.md pode remover o blocker "[Fase 2] Tela gestao/acessos alcançável apenas por URL direta" — resolvido por este plano.
- `ui.users.per_page` usa fallback 15 via `Settings::get`; entra no catálogo do ParameterSeeder quando o 02-07/02-08 consolidar o registry (mesmo padrão de `ui.cnaes.per_page` do 02-04).

---
*Phase: 02-administracao-base*
*Completed: 2026-06-10*
