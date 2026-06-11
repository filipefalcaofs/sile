---
phase: 3
slug: cadastro-empresarial
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-11
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12 (`php artisan test`) |
| **Config file** | `phpunit.xml` (sqlite `:memory:`) |
| **Quick run command** | `php artisan test --compact tests/Feature/Companies` (ou `--filter={Teste}`) |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~12 segundos (suíte atual: 201 testes + novos) |

---

## Sampling Rate

- **After every task commit:** Run o grupo escopado da task + `vendor/bin/pint --dirty --format agent`
- **After every plan wave:** Run `php artisan test --compact` (suíte completa) — se a wave tiver planos paralelos, full-suite SÓ no fechamento da wave pelo orquestrador
- **Before `/gsd-verify-work`:** Full suite verde + `npm run typecheck && npm run build`
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| (preenchido pelo planner ao criar os PLAN.md) | | | | | | | |

Mapa de grupos de teste → HU (do 03-RESEARCH.md):

| Grupo (tests/Feature/Companies/) | HU | Automated Command |
|---|---|---|
| `CnpjLookupTest` | HU-021 | `php artisan test --compact --filter=CnpjLookupTest` |
| `RedesimImportTest` | HU-022 | `php artisan test --compact --filter=RedesimImportTest` |
| `CompanyRegistrationTest` | HU-023 | `php artisan test --compact --filter=CompanyRegistrationTest` |
| `CompanyUpdateTest` | HU-024 | `php artisan test --compact --filter=CompanyUpdateTest` |
| `PrimaryCnaeTest` | HU-025 | `php artisan test --compact --filter=PrimaryCnaeTest` |
| `SecondaryCnaesTest` | HU-026 | `php artisan test --compact --filter=SecondaryCnaesTest` |
| `MyCompaniesTest` | HU-027 | `php artisan test --compact --filter=MyCompaniesTest` |
| `EndCompanyLinkTest` | HU-028 | `php artisan test --compact --filter=EndCompanyLinkTest` |
| Suplementares (CnaeCrud restrictOnDelete, ParameterRegistry chaves novas, rotas com middlewares) | transversal | `php artisan test --compact tests/Feature/Companies tests/Feature/Cnae tests/Feature/Parameters` |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] Suíte atual verde no início da fase (regressão: 201/201) — baseline pelo ORQUESTRADOR antes de despachar waves paralelas
- [ ] `database/data/redesim-exemplo.json` (payload de referência) criado ANTES do RedesimImportTest
- [ ] Coordenação com a fase 2.4 (componentes de listagem) verificada antes das tasks de UI

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Smoke E2E navegável (roteiro de 7 passos) | Critério de pronto da fase | Validação visual/UX + integração CNPJ viva | Roteiro na "Validação manual no browser" do 03-RESEARCH.md (inclui busca real de CNPJ do Banco do Brasil) |
| Chamada REAL ao provider de CNPJ | HU-021 (integração real) | Evidência de integração viva contra a BrasilAPI | Passo 2 do roteiro + auditoria da consulta em activity_log |
| Import REDESIM via comando | HU-022 | Evidência fresca de execução real | `php artisan redesim:importar database/data/redesim-exemplo.json` (relatório no console + idempotência) |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
