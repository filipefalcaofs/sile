---
phase: 01-identidade-acesso-e-auditoria-transversal
plan: 06
subsystem: auth
tags: [fortify, inertia-react, rate-limiting, password-reset, activitylog, settings]

# Dependency graph
requires:
  - phase: 01-01
    provides: config/sile.php + App\Support\Settings + Password::defaults() parametrizado + features do Fortify
  - phase: 01-02
    provides: listeners de access_logs (login/falha/bloqueio/logout), AuditService e 403 auditado
  - phase: 01-03
    provides: papéis/permissões, rotas portal/gestao, auth-layout e SharedProps
  - phase: 01-04
    provides: convenções de form das telas auth (register.tsx) e MustVerifyEmail
  - phase: 01-05
    provides: gate lgpd.accepted e factory state withAcceptedLgpdTerm()
provides:
  - Login único com redirect por perfil (can('acessar-gestao') → gestão; senão portal)
  - Throttle de login parametrizado por security.login.max_attempts com Lockout auditado
  - Fluxo completo de recuperação de senha por e-mail (telas + auditoria)
  - Alteração de senha autenticada com auditoria explícita senha-alterada
  - Grupo de rotas /settings (auth+verified+lgpd.accepted) e settings-layout
affects: [01-07, 01-08, 01-09, fase-02-hu-014]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Binding singleton de LoginRateLimiter sobrescrito para parametrizar o limite mantendo o evento Lockout"
    - "Form do Inertia v3 com errorBag nomeado (errorBag=\"updatePassword\")"
    - "settings-layout com nav lateral e retorno ao painel por permissão"

key-files:
  created:
    - resources/js/pages/auth/login.tsx
    - resources/js/pages/auth/forgot-password.tsx
    - resources/js/pages/auth/reset-password.tsx
    - resources/js/layouts/settings-layout.tsx
    - resources/js/pages/settings/password.tsx
    - app/Http/Controllers/Settings/PasswordController.php
    - routes/settings.php
    - tests/Feature/Auth/AuthenticationTest.php
    - tests/Feature/Auth/PasswordResetTest.php
    - tests/Feature/Settings/PasswordUpdateTest.php
  modified:
    - app/Providers/FortifyServiceProvider.php
    - app/Actions/Fortify/UpdateUserPassword.php
    - routes/web.php

key-decisions:
  - "Bloqueio temporário em request web responde redirect 302 com erro auth.throttle (não 429): LockoutResponse lança ValidationException com status 429, mas o handler só honra o status em JSON"
  - "Limite de tentativas governado de verdade pelo parâmetro: LoginRateLimiter rebinded com subclasse lendo Settings::get('security.login.max_attempts')"
  - "Error bag no Inertia v3: <Form errorBag=\"updatePassword\"> é suportado nativamente (FormComponentProps inclui errorBag) — useForm desnecessário"
  - "CA-04 da HU-003: rotas de recuperação são guest-only; RedirectIfAuthenticated leva à rota nomeada home (landing /)"

patterns-established:
  - "Telas auth seguem o padrão visual do register.tsx (inputClasses/labelClasses/errorClasses, pt-BR)"
  - "Páginas de configurações da conta usam settings-layout com nav Perfil/Senha"

# Metrics
duration: 13min
completed: 2026-06-10
---

# Phase 1 Plan 06: Autenticação, Recuperação e Alteração de Senha Summary

**HU-002/003/004 entregues: login único com redirect por perfil e throttle parametrizado auditado, recuperação de senha por link de e-mail e alteração de senha com auditoria — 23 testes novos cobrindo os CAs**

## Performance

- **Duration:** 13 min
- **Started:** 2026-06-10T02:32:12Z
- **Completed:** 2026-06-10T02:45:30Z
- **Tasks:** 3
- **Files modified:** 13

## Accomplishments

- HU-002: login renderizado via Inertia, cidadão vai para `/portal` e quem tem `acessar-gestao` vai para `/gestao` (binding de `LoginResponse`); login/falha/bloqueio/logout alimentam `access_logs` com requests reais; cidadão autenticado em `/gestao` recebe 403 auditado em `activity_log`.
- HU-002 (parametrização): limite de tentativas vem de `Settings::get('security.login.max_attempts')` nos dois pontos (limiter nomeado e `LoginRateLimiter` do pipeline) — teste prova com `max_attempts=3` que a 4ª tentativa bloqueia e registra `bloqueio`.
- HU-003: fluxo completo — solicitar link (`Notification::assertSentTo` com captura do token real), tela de redefinição com token, redefinição com `PasswordReset` auditado (`senha-redefinida`), token inválido/e-mail desconhecido/senha fraca bloqueados, rotas guest-only para autenticados.
- HU-004: `/settings/password` protegida por `auth+verified+lgpd.accepted`; PUT `/user/password` exige senha atual, aplica a política parametrizada, audita `senha-alterada` (log `seguranca`, result `sucesso`) sem expor hash; visitante é redirecionado ao login.
- Frontend pt-BR: `auth/login`, `auth/forgot-password`, `auth/reset-password`, `settings/password` + `settings-layout` com nav lateral (Perfil/Senha) e "Voltar ao painel" por permissão.

## Task Commits

Each task was committed atomically (TDD: RED → GREEN):

1. **Task 1: HU-002 login por perfil + throttle parametrizado** - `0712183` (test) + `42d4771` (feat)
2. **Task 2: HU-003 recuperação de senha** - `8f18e20` (test) + `610a1e0` (feat)
3. **Task 3: HU-004 alteração de senha** - `9d7b2fc` (test) + `e1f2f36` (feat)

## Files Created/Modified

- `app/Providers/FortifyServiceProvider.php` - loginView/forgot/reset views Inertia; `RateLimiter::for('login')` e rebinding do `LoginRateLimiter` parametrizados; binding de `LoginResponse` por perfil
- `resources/js/pages/auth/login.tsx` - tela de login pt-BR com lembrar-me, link de recuperação e flash status
- `resources/js/pages/auth/forgot-password.tsx` - solicitação do link de recuperação
- `resources/js/pages/auth/reset-password.tsx` - redefinição com token oculto e e-mail readonly
- `app/Actions/Fortify/UpdateUserPassword.php` - auditoria explícita `senha-alterada` via AuditService após a troca
- `app/Http/Controllers/Settings/PasswordController.php` - render de `settings/password` com a política de senha
- `routes/settings.php` - grupo `/settings` (auth+verified+lgpd.accepted), rota `settings.password.show`
- `routes/web.php` - require do settings.php
- `resources/js/layouts/settings-layout.tsx` - header "Configurações da conta", nav Perfil/Senha, retorno por permissão
- `resources/js/pages/settings/password.tsx` - form PUT `/user/password` com `errorBag="updatePassword"` e feedback `recentlySuccessful`
- `tests/Feature/Auth/AuthenticationTest.php` - 8 testes (CA-01..CA-04 da HU-002)
- `tests/Feature/Auth/PasswordResetTest.php` - 9 testes (CA-01..CA-04 da HU-003)
- `tests/Feature/Settings/PasswordUpdateTest.php` - 6 testes (CA-01..CA-04 da HU-004)

## Decisions Made

- **Comportamento real do throttle (registro pedido pelo plano):** com `fortify.limiters.login = null`, o bloqueio vem do pipeline (`EnsureLoginIsNotThrottled` → evento `Lockout` → `LockoutResponse`). A `LockoutResponse` lança `ValidationException` com status 429, mas o handler do Laravel só honra esse status em respostas JSON — em request web a resposta efetiva é **redirect 302 com erro de validação `email` (mensagem `auth.throttle`)**. O teste asserta redirect + erro de sessão + `access_logs.bloqueio` (não `assertTooManyRequests`, que só valeria com limiter nomeado como middleware — caminho rejeitado no 01-02 por não disparar `Lockout`).
- **Mecanismo de error bag no Form do Inertia v3 (registro pedido pelo plano):** o componente `<Form>` aceita a prop `errorBag` nativamente (`FormComponentProps` herda `errorBag` de `Visit`); `errorBag="updatePassword"` faz os erros do bag chegarem achatados no render prop `errors` — `useForm` não foi necessário.
- **CA-04 da HU-003:** rotas de recuperação são guest-only; o `RedirectIfAuthenticated` do framework redireciona autenticados para a rota nomeada `home` (landing `/` do 01-03), pois não existe rota `dashboard`. Mesma leitura do CA-04 aplicada à HU-001 (rota pública guest-only).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Limite de tentativas parametrizado de verdade (rebinding do LoginRateLimiter)**

- **Found during:** Task 1 (RED do bloqueio com `max_attempts=3`)
- **Issue:** o plano previa apenas `RateLimiter::for('login')` parametrizado, mas com `fortify.limiters.login = null` (decisão travada do 01-02) o limiter nomeado **não é consultado** no POST `/login` — quem governa é `Laravel\Fortify\LoginRateLimiter::tooManyAttempts()`, com 5 fixo no vendor. O parâmetro administrável viraria fachada (violação do must_have e da regra de entrega funcional).
- **Fix:** singleton de `LoginRateLimiter` sobrescrito no `register()` do próprio `FortifyServiceProvider` com subclasse anônima cujo `tooManyAttempts()` lê `(int) Settings::get('security.login.max_attempts')`. O `RateLimiter::for('login')` parametrizado também foi registrado conforme o plano (substituindo o `perMinute(5)` publicado), ficando coerente caso `limiters.login` volte a ser nomeado.
- **Files modified:** app/Providers/FortifyServiceProvider.php (já listado no plano)
- **Verification:** `test_bloqueio_temporario_apos_tentativas_excedidas` define `max_attempts=3` e prova bloqueio na 4ª tentativa com registro `bloqueio` em access_logs; suíte do 01-02 (default 5) continua verde nos grupos rodados.
- **Committed in:** 42d4771 (Task 1)

---

**Total deviations:** 1 auto-fixed (1 missing critical)
**Impact on plan:** correção necessária para o parâmetro administrável governar o bloqueio de verdade. Sem scope creep — mesmo arquivo do plano.

## Issues Encountered

- `vendor/bin/pint --dirty` tocou um arquivo do plano paralelo 01-07 (`StoreProcurationRequest.php`) por operar em todos os dirty files do worktree; o arquivo NÃO foi commitado aqui e as execuções seguintes do pint passaram a usar paths explícitos dos arquivos deste plano.
- `npm run typecheck && npm run build` do REFACTOR do plano foram adiados para o fechamento da wave 6, conforme regra de convivência da execução paralela (orquestrador roda no fechamento). Lints dos TSX novos verificados sem erros.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Jornada de autenticação completa e navegável: cadastro → confirmação → login → (termo LGPD) → portal/gestão → recuperação/alteração de senha.
- `settings-layout` pronto para receber `settings/profile` (HU-007, plano 01-08); nav lateral já aponta para `/settings/profile`.
- Grupos `tests/Feature/Auth` + `tests/Feature/Settings`: 38 testes verdes. Suíte completa e `npm run typecheck && npm run build` pendentes do fechamento da wave 6 (01-06 ∥ 01-07).

---
*Phase: 01-identidade-acesso-e-auditoria-transversal*
*Completed: 2026-06-10*
