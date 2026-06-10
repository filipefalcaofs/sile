---
phase: 01-identidade-acesso-e-auditoria-transversal
plan: 08
subsystem: auth
tags: [inertia, react, spatie-activitylog, spatie-permission, paginacao, fortify]

# Dependency graph
requires:
  - phase: 01-02
    provides: access_logs + AccessLogFactory, AuditService (log/logBlocked), 403 auditado
  - phase: 01-03
    provides: papéis/permissões seedados (consultar-acessos-de-qualquer-conta), rotas portal × gestão, layouts
  - phase: 01-06
    provides: grupo de rotas settings + settings-layout com nav para /settings/profile
  - phase: 01-07
    provides: subgrupo protegido do portal (lgpd + ResolveRepresentation) e tela de procurações
provides:
  - Perfil do próprio usuário (GET/PATCH /settings/profile) com CPF somente leitura e auditoria de mudanças
  - Re-verificação de e-mail ao alterar e-mail (email_verified_at = null + reenvio de VerifyEmail)
  - Histórico de acessos do próprio usuário em /portal/acessos (paginado, parametrizado)
  - Consulta administrativa /gestao/acessos/{user} permissionada e auditada (consulta-acessos)
  - Navegação do portal ligando painel, procurações e acessos
affects: [02-administracao-base (HU-012 listagem de usuários), 12-auditoria-e-compliance]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Page size de listagem parametrizado via Settings::get('ui.access_history.per_page', 15) — seção ui em config/sile.php"
    - "Filtro de acessos por user_id OR email — titular enxerga falhas/bloqueios pré-login gravados sem user_id"
    - "Consulta relevante a dados de terceiro auditada explicitamente via AuditService (CA-02)"

key-files:
  created:
    - app/Http/Requests/ProfileUpdateRequest.php
    - app/Http/Controllers/Settings/ProfileController.php
    - app/Http/Controllers/Portal/AccessHistoryController.php
    - app/Http/Controllers/Gestao/AccessHistoryController.php
    - resources/js/pages/settings/profile.tsx
    - resources/js/pages/portal/acessos.tsx
    - resources/js/pages/gestao/acessos.tsx
    - tests/Feature/Settings/ProfileTest.php
    - tests/Feature/AccessHistory/AccessHistoryTest.php
  modified:
    - routes/settings.php
    - routes/portal.php
    - routes/gestao.php
    - config/sile.php
    - resources/js/layouts/portal-layout.tsx

key-decisions:
  - "CPF imutável pós-cadastro: fora das regras do ProfileUpdateRequest — valor enviado é ignorado, nunca validado/preenchido"
  - "Troca de e-mail re-exige verificação: isDirty('email') zera email_verified_at antes do save e wasChanged('email') reenvia VerifyEmail"
  - "Histórico inclui registros pré-login: filtro combinado where(user_id = X) orWhere(email = Y) nos dois controllers"
  - "Consulta administrativa auditada com log 'acessos'/'consulta-acessos' e target_user_id nas properties"

patterns-established:
  - "Parametrização de UI em config/sile.php seção 'ui' lida via Settings::get — HU-014 troca o backend sem tocar call sites"
  - "Paginação Inertia: paginate()->through() no backend; links renderizados com <Link> e label via dangerouslySetInnerHTML"

# Metrics
duration: 10min
completed: 2026-06-10
---

# Phase 01 Plan 08: Perfil do usuário e histórico de acessos Summary

**HU-007 e HU-010 entregues: perfil com CPF imutável, auditoria de mudanças e re-verificação de e-mail; histórico de acessos paginado no portal (inclui bloqueios pré-login por e-mail) e consulta administrativa permissionada e auditada na gestão**

## Performance

- **Duration:** 10 min
- **Started:** 2026-06-10T02:49:44Z
- **Completed:** 2026-06-10T02:59:36Z
- **Tasks:** 2 (TDD: RED + GREEN cada)
- **Files modified:** 14

## Accomplishments

- HU-007 (4 CAs): usuário consulta e atualiza name/e-mail/phone em /settings/profile; CPF exibido somente leitura e impossível de alterar; troca de e-mail zera `email_verified_at` e reenvia a notificação de verificação; mudanças auditadas via HasAuditoria com `attribute_changes` sem campos sensíveis; e-mail duplicado bloqueado; visitante redirecionado para /login.
- HU-010 (4 CAs): usuário vê apenas os próprios acessos (login, saída, falha, bloqueio) com data/hora, IP e canal — inclusive registros pré-login gravados só com e-mail (bloqueio do RecordLockout); administrador consulta qualquer conta em /gestao/acessos/{user} com a permissão `consultar-acessos-de-qualquer-conta` e a consulta é auditada; analista (sem a permissão) recebe 403 auditado como acesso-negado/bloqueado; usuário inexistente retorna 404.
- Page size do histórico parametrizado (`sile.ui.access_history.per_page`, default 15) — sem valor de negócio hardcoded.
- Navegação do portal fechada: nav com Meu painel, Procurações e Meus acessos no portal-layout (sem telas órfãs no portal).
- Suíte completa verde: 119 testes / 464 asserções; typecheck e build OK; pint sem pendências.

## Task Commits

Each task was committed atomically (TDD: test → feat):

1. **Task 1 RED: testes do perfil (HU-007)** - `1248ba0` (test)
2. **Task 1 GREEN: gestão do próprio perfil** - `9ae8847` (feat)
3. **Task 2 RED: testes do histórico de acessos (HU-010)** - `444c1a0` (test)
4. **Task 2 GREEN: histórico no portal e na gestão** - `92d8134` (feat)

_REFACTOR sem commits próprios: pint e typecheck não exigiram mudanças após o GREEN._

## Files Created/Modified

- `app/Http/Requests/ProfileUpdateRequest.php` - Validação de name/email/phone; CPF fora das regras (imutável); unique ignorando o próprio usuário
- `app/Http/Controllers/Settings/ProfileController.php` - edit (Inertia settings/profile) e update com re-verificação de e-mail
- `routes/settings.php` - Rotas profile.edit (GET) e profile.update (PATCH) no grupo settings
- `resources/js/pages/settings/profile.tsx` - Tela "Meu perfil" com CPF formatado somente leitura e aviso de re-verificação
- `app/Http/Controllers/Portal/AccessHistoryController.php` - Acessos do próprio usuário (user_id OR email), paginação parametrizada
- `app/Http/Controllers/Gestao/AccessHistoryController.php` - Consulta administrativa auditada via AuditService (consulta-acessos)
- `config/sile.php` - Seção ui.access_history.per_page (15) preservando security
- `routes/portal.php` - GET /portal/acessos (subgrupo protegido)
- `routes/gestao.php` - GET /gestao/acessos/{user} com middleware permission:consultar-acessos-de-qualquer-conta
- `resources/js/pages/portal/acessos.tsx` - Tabela com badges por evento (Login/Saída/Tentativa falha/Bloqueio temporário), paginação e estado vazio
- `resources/js/pages/gestao/acessos.tsx` - Mesma tabela com cabeçalho "Acessos de {targetUser.name}"
- `resources/js/layouts/portal-layout.tsx` - Nav do portal (Meu painel, Procurações, Meus acessos)
- `tests/Feature/Settings/ProfileTest.php` - 7 testes cobrindo os 4 CAs da HU-007
- `tests/Feature/AccessHistory/AccessHistoryTest.php` - 9 testes cobrindo os 4 CAs da HU-010

## Decisions Made

- CPF imutável implementado por omissão nas regras de validação (não por regra `prohibited`): valor enviado é simplesmente ignorado pelo `validated()`/`fill()` — comportamento alinhado ao starter kit e ao CA-03.
- Filtro combinado `user_id OR email` aplicado igualmente no portal e na gestão para que falhas e bloqueios pré-login (gravados sem user_id pelo RecordLockout) apareçam para o titular do e-mail.
- Labels de paginação renderizados com `dangerouslySetInnerHTML` (labels do paginator vêm com entidades HTML traduzidas pelo laravel-lang).
- Nav do portal incluiu também "Meu painel" (além dos dois links pedidos) para cumprir o critério "navegação liga dashboard, procurações e acessos".

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Checkbox do 01-06 desatualizado no ROADMAP**

- **Found during:** Atualização de metadados pós-execução
- **Issue:** 01-06-PLAN.md concluído (01-06-SUMMARY.md existe, wave 6 fechada) mas o checkbox seguia `[ ]` no ROADMAP.md — estado incorreto da documentação de planejamento
- **Fix:** Marcado `[x]` com data, junto com a marcação do 01-08
- **Files modified:** .planning/ROADMAP.md
- **Verification:** Checkboxes refletem os SUMMARYs existentes
- **Committed in:** commit de metadados deste plano

---

**Total deviations:** 1 auto-fixed (correção de documentação de planejamento)
**Impact on plan:** Nenhum impacto em código. Plano executado exatamente como escrito.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Fase 1 com 8/9 planos concluídos — falta apenas o 01-09 (seeds dev + verificação integral + smoke E2E com checkpoint humano).
- Todas as 10 HUs da fase com CAs cobertos por testes (HU-001 a HU-010).
- **Concern (registrar no STATE.md):** a tela `gestao/acessos` é alcançável apenas por URL direta (`/gestao/acessos/{user}`) nesta fase — a listagem/busca de usuários da gestão é a HU-012 (Fase 2), que fechará essa navegação.

---
*Phase: 01-identidade-acesso-e-auditoria-transversal*
*Completed: 2026-06-10*
