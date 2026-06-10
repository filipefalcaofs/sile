---
phase: 2
slug: administracao-base
status: planned
nyquist_compliant: true
wave_0_complete: false
created: 2026-06-10
planned: 2026-06-10
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
| 02-01-T1 | 02-01 | 1 | HU-011 (Wave 0 — dado oficial) | script com asserts + check python (1331, sem dup, amostras) | `python3 scripts/convert-cnae-xlsx.py` + verificação csv do PLAN | `database/data/cnaes-subclasses-2-3.csv` | ⬜ |
| 02-01-T2 | 02-01 | 1 | HU-011/012/013/014 CA-04 (permissões + seeder aditivo) | PHPUnit | `php artisan test --compact tests/Feature/Authorization` | `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` | ⬜ |
| 02-02-T1 | 02-02 | 1 | HU-014 RN-007/RN-009 (registry: cast, crypt, cache) | PHPUnit | `php artisan test --compact tests/Feature/Parameters` | `tests/Feature/Parameters/ParameterRegistryTest.php` | ⬜ |
| 02-02-T2 | 02-02 | 1 | HU-014 RN-006 (backend Settings) + regressão Fase 1 | PHPUnit | `php artisan test --compact tests/Feature/Parameters tests/Feature/Seeders/ParameterSeederTest.php tests/Unit/Support/SettingsTest.php` | `tests/Feature/Parameters/SettingsBackendTest.php`, `tests/Feature/Seeders/ParameterSeederTest.php` | ⬜ |
| 02-03-T1 | 02-03 | 1 | HU-012 (login de inativado bloqueado + access_log) | PHPUnit | `php artisan test --compact tests/Feature/Users tests/Feature/Auth` | `tests/Feature/Users/InactiveUserLoginTest.php` | ⬜ |
| 02-03-T2 | 02-03 | 1 | HU-012 (sessão ativa derrubada — efeito imediato) | PHPUnit | `php artisan test --compact tests/Feature/Users tests/Feature/Auth tests/Feature/Lgpd` | `app/Http/Middleware/EnsureUserIsActive.php` | ⬜ |
| 02-04-T1 | 02-04 | 2 | HU-011 (import 1.331, divergência 9900-8/00, idempotência) | PHPUnit | `php artisan test --compact tests/Feature/Cnae` | `tests/Feature/Cnae/CnaeImportTest.php` | ⬜ |
| 02-04-T2 | 02-04 | 2 | HU-011 CA-01..04 (CRUD, código imutável, 403 auditado) | PHPUnit | `php artisan test --compact tests/Feature/Cnae` | `tests/Feature/Cnae/CnaeCrudTest.php` | ⬜ |
| 02-04-T3 | 02-04 | 2 | HU-011 (tela com busca debounced) | typecheck/build + full suite (fecho da wave 2) | `npm run typecheck && npm run build && php artisan test --compact` | `resources/js/pages/gestao/cnaes/index.tsx` | ⬜ |
| 02-05-T1 | 02-05 | 3 | HU-012 CA-01..04 (inativação/papel auditados, anti-lockout) | PHPUnit | `php artisan test --compact tests/Feature/Users` | `tests/Feature/Users/ManageUsersTest.php` | ⬜ |
| 02-05-T2 | 02-05 | 3 | HU-012 (tela + link acessos — fecha concern da Fase 1) | typecheck/build + full suite (fecho da wave 3) | `npm run typecheck && npm run build && php artisan test --compact` | `resources/js/pages/gestao/usuarios/index.tsx` | ⬜ |
| 02-06-T1 | 02-06 | 4 | HU-013 CA-01..04 (proteções estruturais, anti-lockout) | PHPUnit | `php artisan test --compact tests/Feature/Roles` | `tests/Feature/Roles/ManageRolesTest.php` | ⬜ |
| 02-06-T2 | 02-06 | 4 | HU-013 (tela com checkboxes agrupadas) | typecheck/build + full suite (fecho da wave 4) | `npm run typecheck && npm run build && php artisan test --compact` | `resources/js/pages/gestao/perfis/index.tsx` | ⬜ |
| 02-07-T1 | 02-07 | 5 | HU-014 CA-01..04 + RN-007/RN-009 | PHPUnit | `php artisan test --compact tests/Feature/Parameters` | `tests/Feature/Parameters/ManageParametersTest.php` | ⬜ |
| 02-07-T2 | 02-07 | 5 | HU-014 CA-05/CA-06/CA-07 (toggle real procurações) | PHPUnit | `php artisan test --compact tests/Feature/Parameters tests/Feature/Procuration` | `tests/Feature/Parameters/ParameterEffectTest.php`, `tests/Feature/Parameters/ParameterHistoryTest.php` | ⬜ |
| 02-07-T3 | 02-07 | 5 | HU-014 (telas + aviso de degradação no portal) | typecheck/build + full suite (fecho da wave 5) | `npm run typecheck && npm run build && php artisan test --compact` | `resources/js/pages/gestao/parametros/index.tsx`, `resources/js/pages/gestao/parametros/historico.tsx` | ⬜ |
| 02-08-T1 | 02-08 | 6 | Fase (seeds dev integrados + navegação permissionada) | PHPUnit + typecheck/build | `php artisan test --compact tests/Feature/Seeders && npm run typecheck && npm run build` | `tests/Feature/Seeders/DatabaseSeederTest.php` | ⬜ |
| 02-08-T2 | 02-08 | 6 | Fase (verificação integral com evidência fresca) | full suite + pint + typecheck + build + tinker | `php artisan test --compact && vendor/bin/pint --dirty --format agent && npm run typecheck && npm run build` | — | ⬜ |
| 02-08-T3 | 02-08 | 6 | Fase (smoke E2E das 4 HUs) | manual (checkpoint humano) | roteiro de 7 passos (Manual-Only Verifications) | — | ⬜ |

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

- [x] All tasks have `<automated>` verify or Wave 0 dependencies (única exceção: 02-08-T3, checkpoint humano listado em Manual-Only)
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references (CSV gerado no 02-01-T1, antes do CnaeSeeder do 02-04)
- [x] No watch-mode flags
- [x] Feedback latency < 60s (comandos escopados por diretório de teste)
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** planned 2026-06-10 (gsd-planner) — execução pendente
