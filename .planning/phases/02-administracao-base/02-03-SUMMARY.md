---
phase: 02-administracao-base
plan: 03
subsystem: auth
tags: [fortify, authenticateUsing, middleware, access-logs, inativacao]

# Dependency graph
requires:
  - phase: 01-identidade-acesso-e-auditoria-transversal
    provides: "Fortify configurado (limiters.login=null, rebinding LoginRateLimiter), access_logs + RecordFailedLogin, factory states por papel, withAcceptedLgpdTerm"
provides:
  - "Coluna users.inactivated_at (timestamp nullable, fora do fillable) + User::isInactive() + UserFactory::inactive()"
  - "Bloqueio de login de conta inativada via Fortify::authenticateUsing com mensagem pt-BR e access_log evento 'inativada'"
  - "Middleware global EnsureUserIsActive: sessão aberta de inativado derrubada na request seguinte"
affects: [02-05 (UI de usuários: inativar/reativar consome este efeito), fase-12 (auditoria consulta evento 'inativada')]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Fortify::authenticateUsing como ADIÇÃO no boot (rebinding e RateLimiter da Fase 1 intocados)"
    - "Middleware de revalidação por request no append global do grupo web"

key-files:
  created:
    - database/migrations/2026_06_10_141715_add_inactivated_at_to_users_table.php
    - app/Http/Middleware/EnsureUserIsActive.php
    - tests/Feature/Users/InactiveUserLoginTest.php
  modified:
    - app/Models/User.php
    - database/factories/UserFactory.php
    - app/Providers/FortifyServiceProvider.php
    - bootstrap/app.php

key-decisions:
  - "Evento access_logs nomeado 'inativada' (9 chars): a coluna event é varchar(20) na migration da Fase 1 — nomes descritivos longos estourariam o limite (Pitfall 8 da pesquisa)"
  - "Middleware EnsureUserIsActive no append GLOBAL do grupo web (bootstrap/app.php), não por arquivo de rota: cobre portal, gestão e settings de uma vez, sem tocar routes/* (que pertencem a outros planos da wave); guest é no-op"
  - "Anti-oráculo de enumeração: mensagem de conta inativa SÓ com credenciais corretas; senha errada retorna null (fluxo padrão auth.failed + evento Failed -> access_log 'falha')"
  - "inactivated_at fora do #[Fillable]: inativação só por forceFill em fluxo autorizado (controller do 02-05), nunca mass assignment"

patterns-established:
  - "Inativação com efeito imediato: bloqueio no login + revalidação por request (consistente com ResolveRepresentation da Fase 1)"

# Metrics
duration: 10min
completed: 2026-06-10
---

# Phase 02 Plan 03: Inativação de Conta (Autenticação) Summary

**Conta inativada não autentica (Fortify::authenticateUsing + access_log 'inativada') e sessão aberta é derrubada na request seguinte por middleware global — sem oráculo de enumeração e sem regressão na Fase 1**

## Performance

- **Duration:** 10 min
- **Started:** 2026-06-10T14:13:50Z
- **Completed:** 2026-06-10T14:23:52Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments

- Coluna `inactivated_at` em `users` (timestamp nullable, fora do mass assignment) com `User::isInactive()` e state `inactive()` na factory.
- Login de conta inativada bloqueado no `Fortify::authenticateUsing`: credenciais corretas recebem a mensagem clara "Sua conta está inativa. Procure o administrador do sistema." e a tentativa fica registrada em `access_logs` com evento dedicado `inativada` — degradação comunicada, nunca silenciosa.
- Garantia anti-oráculo: senha errada em conta inativada segue o fluxo padrão (`return null` → evento `Failed` → access_log `falha` da Fase 1) — o estado da conta não vaza para quem não tem a credencial.
- Middleware `EnsureUserIsActive` no grupo web global: sessão já aberta de usuário inativado é derrubada na request seguinte (logout + invalidate + regenerateToken + redirect ao login com a mesma mensagem) — efeito imediato, consistente com a revalidação por request estabelecida na Fase 1.
- Reativação (`inactivated_at = null`) restaura o acesso imediatamente; conta ativa tem zero mudança de comportamento (regressões Auth/Lgpd verdes: 49 testes, 180 asserções).

## Task Commits

Each task was committed atomically:

1. **Task 1: Coluna inactivated_at + bloqueio de login via Fortify (TDD)** - `563cb6e` (feat)
2. **Task 2: Middleware EnsureUserIsActive — efeito imediato sobre sessões abertas (TDD)** - `6aeb55c` (feat)

## Files Created/Modified

- `database/migrations/2026_06_10_141715_add_inactivated_at_to_users_table.php` - Coluna `inactivated_at` timestamp nullable em `users`
- `app/Models/User.php` - Cast `datetime` + `isInactive()`; `#[Fillable]` inalterado
- `database/factories/UserFactory.php` - State `inactive()`
- `app/Providers/FortifyServiceProvider.php` - `Fortify::authenticateUsing` (adição no boot; rebinding `LoginRateLimiter` e `RateLimiter::for('login')` intactos)
- `app/Http/Middleware/EnsureUserIsActive.php` - Logout forçado + redirect com mensagem para inativado com sessão ativa
- `bootstrap/app.php` - `EnsureUserIsActive::class` no append do grupo web
- `tests/Feature/Users/InactiveUserLoginTest.php` - 6 testes cobrindo bloqueio, anti-oráculo, regressão, reativação e derrubada de sessão

## Decisions Made

- **Evento `inativada` (nome curto):** `access_logs.event` é `varchar(20)` desde a Fase 1; `inativada` tem 9 chars e cabe com folga, enquanto nomes descritivos como `bloqueio-inativacao` ficariam no limite exato (Pitfall 8 da pesquisa).
- **Middleware global no grupo web, não por arquivo de rota:** cobre portal, gestão e settings de uma vez em `bootstrap/app.php`, sem tocar `routes/portal.php`/`routes/gestao.php` (arquivos de outros planos da wave paralela); para guest o custo é um null-check.
- **Anti-oráculo:** a verificação de credencial vem ANTES da verificação de inatividade no callback — senha errada nunca revela que a conta está inativa.
- **`inactivated_at` fora do `#[Fillable]`:** inativação é operação privilegiada; só ocorre por `forceFill` em fluxo autorizado (o controller administrativo do plano 02-05).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- O efeito real de inativar/reativar está pronto de ponta a ponta no nível de autenticação; o plano 02-05 (UI administrativa de usuários) só precisa gravar/limpar `inactivated_at` via `forceFill` e auditar a ação.
- Wave 1 paralela: suíte completa e build ficam para o fechamento da wave pelo orquestrador (verificações deste plano foram escopadas: Users/Auth/Lgpd).

---
*Phase: 02-administracao-base*
*Completed: 2026-06-10*
