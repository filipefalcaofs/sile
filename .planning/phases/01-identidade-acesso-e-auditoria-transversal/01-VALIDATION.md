---
phase: 1
slug: identidade-acesso-e-auditoria-transversal
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-09
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12 (`php artisan test`) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter={grupo da task}` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30 segundos (suíte da fase) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter={grupo da task}` + `vendor/bin/pint --dirty --format agent`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd-verify-work`:** Full suite must be green + `npm run build` sem erros
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| (preenchido pelo planner ao criar os PLAN.md) | | | | | | | |

Mapa de grupos de teste → HU (do RESEARCH.md):

| Grupo (tests/Feature/) | HU | Automated Command |
|---|---|---|
| `Auth/RegistrationTest` | HU-001 | `php artisan test --compact tests/Feature/Auth/RegistrationTest.php` |
| `Auth/AuthenticationTest` | HU-002 | `php artisan test --compact tests/Feature/Auth/AuthenticationTest.php` |
| `Auth/PasswordResetTest` | HU-003 | `php artisan test --compact tests/Feature/Auth/PasswordResetTest.php` |
| `Settings/PasswordUpdateTest` | HU-004 | `php artisan test --compact tests/Feature/Settings/PasswordUpdateTest.php` |
| `Auth/EmailVerificationTest` | HU-005 | `php artisan test --compact tests/Feature/Auth/EmailVerificationTest.php` |
| `Lgpd/TermAcceptanceTest` | HU-006 | `php artisan test --compact tests/Feature/Lgpd/TermAcceptanceTest.php` |
| `Settings/ProfileTest` | HU-007 | `php artisan test --compact tests/Feature/Settings/ProfileTest.php` |
| `Procuration/LinkAttorneyTest` | HU-008 | `php artisan test --compact tests/Feature/Procuration/LinkAttorneyTest.php` |
| `Procuration/RevokeAttorneyTest` | HU-009 | `php artisan test --compact tests/Feature/Procuration/RevokeAttorneyTest.php` |
| `AccessHistory/AccessHistoryTest` | HU-010 | `php artisan test --compact tests/Feature/AccessHistory/AccessHistoryTest.php` |
| `Audit/AuditInfrastructureTest` | RN-002 | `php artisan test --compact tests/Feature/Audit/AuditInfrastructureTest.php` |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] PHPUnit já instalado e configurado no esqueleto (`phpunit.xml` com sqlite in-memory) — verificar `php artisan test --compact` roda verde no esqueleto
- [ ] `tests/TestCase.php` — adicionar `withoutVite()` se necessário para testes Inertia
- [ ] Dependências da fase instaladas (fortify, spatie/permission, spatie/activitylog) antes dos primeiros testes RED

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Smoke E2E navegável no browser | Critério de pronto da fase | Validação visual/UX (telas pt-BR, navegação real) | Roteiro de 9 passos na seção "Validação manual no browser" do 01-RESEARCH.md (`composer run dev`, cadastro → verificação → LGPD → lockout → reset → perfil → procuração → acessos → 403 gestão) |
| E-mails de verificação/reset chegando com conteúdo pt-BR | HU-003, HU-005 | Render de notificação | `MAIL_MAILER=log`; conferir `storage/logs/laravel.log` |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
