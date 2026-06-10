---
phase: 2
slug: administracao-base
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-10
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12 (`php artisan test`) |
| **Config file** | `phpunit.xml` (sqlite `:memory:`) |
| **Quick run command** | `php artisan test --compact tests/Feature/{grupo da task}` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~10 segundos (suíte completa atual: 122 testes) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact tests/Feature/{grupo}` + `vendor/bin/pint --dirty --format agent`
- **After every plan wave:** Run `php artisan test --compact` (suíte completa) — se a wave tiver planos paralelos, full-suite SÓ no fechamento da wave pelo orquestrador
- **Before `/gsd-verify-work`:** Full suite verde + `npm run typecheck && npm run build`
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| (preenchido pelo planner ao criar os PLAN.md) | | | | | | | |

Mapa de grupos de teste → HU (do 02-RESEARCH.md):

| Grupo (tests/) | HU / RN | Automated Command |
|---|---|---|
| `Feature/Cnae/CnaeCrudTest` | HU-011 CA-01..04 | `php artisan test --compact tests/Feature/Cnae` |
| `Feature/Cnae/CnaeImportTest` | HU-011 (seed 1.331, divergência 9900-8/00, idempotência) | `php artisan test --compact tests/Feature/Cnae` |
| `Feature/Users/ManageUsersTest` | HU-012 CA-01..04 (inativação, vínculo de papel, link acessos) | `php artisan test --compact tests/Feature/Users` |
| `Feature/Users/InactiveUserLoginTest` | HU-012 (login bloqueado pt-BR + access_log + sessão derrubada) | `php artisan test --compact tests/Feature/Users` |
| `Feature/Roles/ManageRolesTest` | HU-013 CA-01..04 (proteções estruturais, anti-lockout) | `php artisan test --compact tests/Feature/Roles` |
| `Feature/Parameters/ManageParametersTest` | HU-014 CA-01..04 + RN-007/RN-009 | `php artisan test --compact tests/Feature/Parameters` |
| `Feature/Parameters/ParameterEffectTest` | HU-014 CA-05 (efeito sem deploy) + CA-06 (degradação controlada) | `php artisan test --compact tests/Feature/Parameters` |
| `Feature/Parameters/ParameterHistoryTest` | HU-014 CA-07 (histórico; sensível mascarado) | `php artisan test --compact tests/Feature/Parameters` |
| `Feature/Seeders/` (estendido) | Seeder aditivo idempotente; ParameterSeeder preserva valor administrado | `php artisan test --compact tests/Feature/Seeders` |
| `Unit/Support/SettingsTest` (existente — NÃO tocar asserções) | Regressão da promessa da Fase 1 (fallback config sem tabela) | `php artisan test --compact tests/Unit/Support/SettingsTest.php` |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] CSV oficial convertido e versionado em `database/data/` (com script de proveniência) ANTES do CnaeSeeder
- [ ] Suíte atual verde no início da fase (regressão: 122/122)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Smoke E2E navegável das 4 telas administrativas | Critério de pronto da fase | Validação visual/UX | Roteiro de 7 passos na "Validação manual no browser" do 02-RESEARCH.md (`admin@sile.dev`/`password`) |
| Seed CNAE no banco real | HU-011 | Evidência fresca pós-migrate | Comandos tinker do 02-RESEARCH.md (count=1331, divergência registrada na auditoria) |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
