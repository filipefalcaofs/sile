---
phase: 01-identidade-acesso-e-auditoria-transversal
plan: 07
subsystem: auth
tags: [procuration, representation, acting-for, activitylog, context, scoped, inertia, react]

# Dependency graph
requires:
  - phase: 01-02
    provides: RecordActivityAction (enriquecimento via Context acting_for_user_id) e AuditService travado
  - phase: 01-03
    provides: papéis seedados, rotas portal protegidas, portal-layout e SharedProps tipadas
  - phase: 01-05
    provides: gate lgpd.accepted e state withAcceptedLgpdTerm() para navegar no portal em testes
provides:
  - Procuração user→user com vigência (starts_at/expires_at) e revogação (revoked_at/revoked_by)
  - Representação "em nome de" por sessão, revalidada a cada request (revogação com efeito imediato)
  - acting_for_user_id populado de verdade na trilha de auditoria (RN-002 completo)
  - Tela portal/procuracoes (vincular, revogar, atuar em nome de) + banner de representação
affects: [fase-3-cadastro-empresarial, fases-8+-solicitacoes, fase-12-auditoria-compliance, 01-08, 01-09]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Middleware de revalidação por request (ResolveRepresentation) + scoped service para estado de representação"
    - "Validação de negócio em FormRequest::after() (auto-procuração, duplicidade ativa)"
    - "Unicidade condicional na aplicação (scope active), sem índice único parcial (portabilidade SQLite/Postgres)"

key-files:
  created:
    - database/migrations/2026_06_10_023838_create_procurations_table.php
    - app/Models/Procuration.php
    - database/factories/ProcurationFactory.php
    - app/Http/Requests/StoreProcurationRequest.php
    - app/Http/Controllers/Portal/ProcurationController.php
    - app/Http/Controllers/Portal/RepresentationController.php
    - app/Http/Middleware/ResolveRepresentation.php
    - app/Support/Representation/CurrentRepresentation.php
    - app/Policies/ProcurationPolicy.php
    - resources/js/pages/portal/procuracoes/index.tsx
    - tests/Feature/Procuration/LinkAttorneyTest.php
    - tests/Feature/Procuration/RevokeAttorneyTest.php
  modified:
    - routes/portal.php
    - app/Providers/AppServiceProvider.php
    - app/Http/Middleware/HandleInertiaRequests.php
    - resources/js/layouts/portal-layout.tsx
    - resources/js/types/index.d.ts

key-decisions:
  - "Representação vive na sessão (acting_procuration_id) e é revalidada em TODA request do portal — revogação tem efeito imediato (Pitfall 7)"
  - "ResolveRepresentation reseta Context e scoped service quando não há representação válida — estado sobrevive entre requests em testes/queue workers"
  - "Unicidade 'uma procuração ativa por par' na aplicação via FormRequest::after(), não no banco (portabilidade SQLite/Postgres)"
  - "Procurador não é papel spatie: botão 'Atuar em nome de' deriva de procuração ativa recebida"

patterns-established:
  - "Estado por request compartilhado com Inertia: middleware popula scoped service; share usa closure lazy avaliada após middlewares de rota"
  - "Eventos de representação auditados com nomes próprios: representacao-iniciada / representacao-encerrada (log procuracao)"

# Metrics
duration: 13min
completed: 2026-06-10
---

# Phase 1 Plan 07: Procuração e Representação "Em Nome De" Summary

**Procuração user→user com vigência e revogação imediata; representação por sessão revalidada a cada request via ResolveRepresentation, alimentando o acting_for_user_id da auditoria (RN-002) e o banner "Atuando em nome de" do portal**

## Performance

- **Duration:** 13 min
- **Started:** 2026-06-10T02:32:18Z
- **Completed:** 2026-06-10T02:45:22Z
- **Tasks:** 2 (TDD: RED + GREEN cada)
- **Files modified:** 17

## Accomplishments

- HU-008 completa: representante legal vincula procurador por e-mail de conta existente, com vigência opcional; e-mail inexistente orienta cadastro prévio, auto-procuração e duplicidade ativa bloqueadas com mensagens pt-BR; vínculo auditado (9 testes).
- HU-009 completa: revogação restrita ao outorgante (policy + 403 auditado como acesso-negado/bloqueado), com efeito imediato — na request seguinte o procurador perde a representação com aviso (9 testes).
- RN-002 "em nome de" alimentado de ponta a ponta: `ResolveRepresentation` popula `Context::add('acting_for_user_id')` consumido pelo enriquecimento central de auditoria (01-02); qualquer activity gerada durante representação carrega o outorgante.
- UI do portal: tela "Minhas procurações" (vincular, outorgadas com Revogar, recebidas com Atuar em nome de) e banner âmbar "Atuando em nome de" com encerramento em um clique.

## Task Commits

Cada task em TDD com commits atômicos:

1. **Task 1 RED: teste falhando HU-008** - `0a4b7f4` (test)
2. **Task 1 GREEN: vínculo de procurador com vigência** - `3b193cc` (feat)
3. **Task 2 RED: teste falhando HU-009** - `13a2144` (test)
4. **Task 2 GREEN: revogação imediata + representação** - `575c5c1` (feat)

## Files Created/Modified

- `app/Models/Procuration.php` - Procuração com `scopeActive()` e `isActive(): bool`
- `database/migrations/*_create_procurations_table.php` - Schema portável (sem `DB::statement`), índice simples no par
- `app/Http/Requests/StoreProcurationRequest.php` - `exists:users,email` com orientação de cadastro + bloqueios de negócio em `after()`
- `app/Http/Controllers/Portal/ProcurationController.php` - index (outorgadas/recebidas), store, destroy com `Gate::authorize`
- `app/Http/Controllers/Portal/RepresentationController.php` - inicia/encerra representação com auditoria explícita
- `app/Http/Middleware/ResolveRepresentation.php` - revalidação por request + Context + scoped service
- `app/Support/Representation/CurrentRepresentation.php` - estado scoped da representação (set/clear/procuration/grantor)
- `app/Policies/ProcurationPolicy.php` - delete restrito ao `grantor_user_id`
- `routes/portal.php` - subgrupo protegido agora com `ResolveRepresentation`; rotas procuracoes.* e representacao.*
- `app/Http/Middleware/HandleInertiaRequests.php` - share `actingFor` (closure lazy)
- `resources/js/pages/portal/procuracoes/index.tsx` - tela de gestão de procurações
- `resources/js/layouts/portal-layout.tsx` - banner "Atuando em nome de" + encerrar representação
- `resources/js/types/index.d.ts` - `actingFor` tipado em SharedProps
- `tests/Feature/Procuration/{LinkAttorneyTest,RevokeAttorneyTest}.php` - 18 testes cobrindo CAs das duas HUs

## Decisions Made

- Representação na sessão (`acting_procuration_id`) com revalidação em toda request do portal — única forma de garantir efeito imediato da revogação (Pitfall 7 da pesquisa).
- `ResolveRepresentation` zera Context e scoped service quando não há representação válida (ramo else + `clear()`): Context e instâncias scoped sobrevivem entre requests no mesmo processo (testes, queue workers) — sem o reset, a representação "vazaria" após revogação/encerramento.
- Unicidade "uma procuração ativa por par" validada na aplicação (decisão travada da pesquisa — índice único parcial rejeitado por portabilidade SQLite/Postgres).
- Eventos de auditoria nomeados em pt-BR: `representacao-iniciada` / `representacao-encerrada` no log `procuracao`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Reset do estado de representação no middleware**

- **Found during:** Task 2 (representação "em nome de")
- **Issue:** O snippet do plano só populava Context/scoped service quando a representação era válida. Context e serviços scoped persistem entre requests no mesmo processo (testes na mesma instância, queue workers): sem reset explícito, `actingFor` e `acting_for_user_id` permaneceriam ativos após revogação/encerramento — exatamente o vazamento que a HU-009 proíbe.
- **Fix:** `CurrentRepresentation::clear()` adicionado e `ResolveRepresentation` passa a resetar (`Context::forget` + `clear()`) quando não há representação válida na sessão.
- **Files modified:** app/Support/Representation/CurrentRepresentation.php, app/Http/Middleware/ResolveRepresentation.php
- **Verification:** `test_revogacao_tem_efeito_imediato` cobre o cenário (actingFor null na request seguinte à revogação)
- **Committed in:** 575c5c1 (commit da Task 2)

---

**Total deviations:** 1 auto-fixed (1 missing critical)
**Impact on plan:** Correção necessária para o efeito imediato da revogação valer em qualquer runtime. Sem scope creep.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Base de representação legal pronta para os fluxos de solicitação (Fases 8+): `CurrentRepresentation` injetável em controllers e `acting_for_user_id` na trilha.
- Fase 3 pode acrescentar escopo por empresa via migration aditiva (`company_id` nullable) sem retrabalho.
- Verificações globais da wave 6 (suíte completa `php artisan test --compact` e `npm run typecheck && npm run build`) ficam com o orquestrador no fechamento da wave, conforme regra de execução paralela 01-06 ∥ 01-07; o escopo deste plano (`--filter=Procuration`, 18 testes) está verde.

---
*Phase: 01-identidade-acesso-e-auditoria-transversal*
*Completed: 2026-06-10*
