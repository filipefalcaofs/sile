---
phase: 1
slug: identidade-acesso-e-auditoria-transversal
status: planned
nyquist_compliant: true
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
- **Wave 6 (01-06 ∥ 01-07, mesmo worktree):** os planos rodam APENAS filtros escopados aos próprios grupos durante a execução (RED transitório de um plano não pode quebrar o verify do outro); a suíte completa roda SOMENTE no fechamento da wave 6, pelo executor.
- **Before `/gsd-verify-work`:** Full suite must be green + `npm run build` sem erros
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 01-01 T1 Wave 0: deps + Fortify + pt-BR + withoutVite | 01-01 | 1 | infra (Wave 0) | smoke | `php artisan test --compact` + `php artisan route:list --path=login` | tests/ (esqueleto) | ⬜ |
| 01-01 T2 config/sile + Settings + política de senha | 01-01 | 1 | infra (HU-014 prep) | unit | `php artisan test --compact --filter=SettingsTest` | tests/Unit/Support/SettingsTest.php | ⬜ |
| 01-01 T3 SecurityHeaders no grupo web | 01-01 | 1 | infra | feature | `php artisan test --compact --filter=SecurityHeadersTest` | tests/Feature/Security/SecurityHeadersTest.php | ⬜ |
| 01-02 T1 activitylog estendido + RecordActivityAction | 01-02 | 2 | RN-002 | feature | `php artisan test --compact --filter=AuditInfrastructureTest` | tests/Feature/Audit/AuditInfrastructureTest.php | ⬜ |
| 01-02 T2 HasAuditoria + AuditService + 403 auditado | 01-02 | 2 | RN-002 | feature | `php artisan test --compact --filter=AuditInfrastructureTest` | tests/Feature/Audit/AuditInfrastructureTest.php | ⬜ |
| 01-02 T3 access_logs + 7 listeners de auth | 01-02 | 2 | RN-002 (base HU-010) | feature | `php artisan test --compact --filter=AccessLogRecordingTest` | tests/Feature/Audit/AccessLogRecordingTest.php | ⬜ |
| 01-03 T1 roles/permissions seed + factory states | 01-03 | 3 | RN-003 | feature | `php artisan test --compact --filter=RolesAndPermissionsSeederTest` | tests/Feature/Authorization/RolesAndPermissionsSeederTest.php | ⬜ |
| 01-03 T2 rotas portal × gestão + landing + shared props + layouts | 01-03 | 3 | RN-003 (base HU-002 CA-04) | feature | `php artisan test --compact --filter=EnvironmentAccessTest` + `npm run build` | tests/Feature/Routing/EnvironmentAccessTest.php | ⬜ |
| 01-04 T1 cadastro + ValidCpf + role cidadao + auditoria | 01-04 | 4 | HU-001 | unit + feature | `php artisan test --compact tests/Feature/Auth/RegistrationTest.php` + `--filter=ValidCpfTest` | tests/Feature/Auth/RegistrationTest.php | ⬜ |
| 01-04 T2 verificação de e-mail | 01-04 | 4 | HU-005 | feature | `php artisan test --compact tests/Feature/Auth/EmailVerificationTest.php` | tests/Feature/Auth/EmailVerificationTest.php | ⬜ |
| 01-05 T1 termos versionados + factories + seed | 01-05 | 5 | HU-006 | feature | `php artisan test --compact --filter=TermAcceptanceTest` | tests/Feature/Lgpd/TermAcceptanceTest.php | ⬜ |
| 01-05 T2 middleware lgpd.accepted + página + re-aceite | 01-05 | 5 | HU-006 | feature | `php artisan test --compact --filter=TermAcceptanceTest` | tests/Feature/Lgpd/TermAcceptanceTest.php | ⬜ |
| 01-06 T1 login + redirect por perfil + lockout | 01-06 | 6 | HU-002 | feature | `php artisan test --compact tests/Feature/Auth/AuthenticationTest.php` | tests/Feature/Auth/AuthenticationTest.php | ⬜ |
| 01-06 T2 recuperação de senha (CA-01..CA-04, 9 testes — inclui guest-only autenticado) | 01-06 | 6 | HU-003 | feature | `php artisan test --compact tests/Feature/Auth/PasswordResetTest.php` | tests/Feature/Auth/PasswordResetTest.php | ⬜ |
| 01-06 T3 alteração de senha | 01-06 | 6 | HU-004 | feature | `php artisan test --compact tests/Feature/Settings/PasswordUpdateTest.php` | tests/Feature/Settings/PasswordUpdateTest.php | ⬜ |
| 01-07 T1 vincular procurador | 01-07 | 6 | HU-008 | feature | `php artisan test --compact tests/Feature/Procuration/LinkAttorneyTest.php` | tests/Feature/Procuration/LinkAttorneyTest.php | ⬜ |
| 01-07 T2 representação "em nome de" + revogação imediata | 01-07 | 6 | HU-009 (+HU-008 CA-02) | feature | `php artisan test --compact tests/Feature/Procuration/RevokeAttorneyTest.php` | tests/Feature/Procuration/RevokeAttorneyTest.php | ⬜ |
| 01-08 T1 perfil do usuário | 01-08 | 7 | HU-007 | feature | `php artisan test --compact tests/Feature/Settings/ProfileTest.php` | tests/Feature/Settings/ProfileTest.php | ⬜ |
| 01-08 T2 histórico de acessos (portal + gestão) | 01-08 | 7 | HU-010 | feature | `php artisan test --compact tests/Feature/AccessHistory/AccessHistoryTest.php` | tests/Feature/AccessHistory/AccessHistoryTest.php | ⬜ |
| 01-09 T1 seeds dev + verificação integral | 01-09 | 8 | critério de pronto | full suite | `php artisan test --compact` + pint + typecheck + build | tests/Feature/Seeders/DatabaseSeederTest.php | ⬜ |
| 01-09 T2 smoke E2E browser | 01-09 | 8 | critério de pronto | manual (checkpoint) | roteiro de 9 passos no 01-09-PLAN.md | — | ⬜ |

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

- [x] All tasks have `<automated>` verify or Wave 0 dependencies (única exceção: 01-09 T2, checkpoint humano declarado em Manual-Only)
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references (plano 01-01: deps + withoutVite + smoke do esqueleto)
- [x] No watch-mode flags
- [x] Feedback latency < 60s (filtros por arquivo/grupo de teste)
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending (orquestrador/checker)
