---
phase: 3
slug: cadastro-empresarial
status: planned
nyquist_compliant: true
wave_0_complete: false
created: 2026-06-11
planned: 2026-06-11
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
| **Estimated runtime** | ~12 segundos (suíte atual: 209 testes + novos) |

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
| ValidCnpj (módulo 11 ASCII-48) | 03-01 | 1 | transversal (HU-021/023) | unit | `php artisan test --compact --filter=ValidCnpjTest` | tests/Unit/Rules/ValidCnpjTest.php | ⬜ |
| Schema + models + factories | 03-01 | 1 | transversal | feature | `php artisan test --compact --filter=CompanyFoundationTest` | tests/Feature/Companies/CompanyFoundationTest.php | ⬜ |
| Parâmetros novos + pendência CNAE | 03-01 | 1 | transversal (HU-014/HU-011) | feature | `php artisan test --compact --filter='ParameterSeederTest\|CnaeCrudTest'` | tests/Feature/Seeders/ParameterSeederTest.php + tests/Feature/Cnae/CnaeCrudTest.php | ⬜ |
| Contrato + DTO + provider BrasilAPI | 03-02 | 2 | HU-021 | feature (Http fake) | `php artisan test --compact --filter=CnpjLookupTest` | tests/Feature/Companies/CnpjLookupTest.php | ⬜ |
| Endpoint consultar-cnpj + toggle + auditoria | 03-02 | 2 | HU-021 | feature | `php artisan test --compact --filter=CnpjLookupTest` | tests/Feature/Companies/CnpjLookupTest.php | ⬜ |
| Payload de referência + fixtures (Wave 0) | 03-03 | 2 | HU-022 | dados | `php -r 'json_decode(file_get_contents("database/data/redesim-exemplo.json"), flags: JSON_THROW_ON_ERROR);'` | database/data/redesim-exemplo.json | ⬜ |
| RedesimImportService | 03-03 | 2 | HU-022 | feature | `php artisan test --compact --filter=RedesimImportTest` | tests/Feature/Companies/RedesimImportTest.php | ⬜ |
| Comando redesim:importar | 03-03 | 2 | HU-022 | feature (artisan) | `php artisan test --compact --filter=RedesimImportTest` | tests/Feature/Companies/RedesimImportTest.php | ⬜ |
| Policy + store transacional | 03-04 | 3 | HU-023 | feature | `php artisan test --compact --filter=CompanyRegistrationTest` | tests/Feature/Companies/CompanyRegistrationTest.php | ⬜ |
| Index com representação + paginação | 03-04 | 3 | HU-027 | feature | `php artisan test --compact --filter=MyCompaniesTest` | tests/Feature/Companies/MyCompaniesTest.php | ⬜ |
| Show + update com CNPJ imutável | 03-05 | 4 | HU-024 | feature | `php artisan test --compact --filter=CompanyUpdateTest` | tests/Feature/Companies/CompanyUpdateTest.php | ⬜ |
| Encerramento de vínculo + último responsável | 03-05 | 4 | HU-028 | feature | `php artisan test --compact --filter=EndCompanyLinkTest` | tests/Feature/Companies/EndCompanyLinkTest.php | ⬜ |
| CompanyCnaeService (invariantes) | 03-06 | 5 | HU-025/HU-026 | feature (service) | `php artisan test --compact --filter='PrimaryCnaeTest\|SecondaryCnaesTest'` | tests/Feature/Companies/PrimaryCnaeTest.php + SecondaryCnaesTest.php | ⬜ |
| Endpoints CNAE + busca /portal/cnaes | 03-06 | 5 | HU-025/HU-026 | feature (HTTP) | `php artisan test --compact --filter='PrimaryCnaeTest\|SecondaryCnaesTest'` | tests/Feature/Companies/PrimaryCnaeTest.php + SecondaryCnaesTest.php | ⬜ |
| Sidebar + tela Minhas empresas | 03-07 | 6 | HU-027 | build | `npm run typecheck && npm run build` | resources/js/pages/portal/empresas/index.tsx | ⬜ |
| Tela de cadastro com lookup | 03-07 | 6 | HU-021/HU-023 | build + regressão | `npm run typecheck && npm run build && php artisan test --compact --filter='CnpjLookupTest\|CompanyRegistrationTest'` | resources/js/pages/portal/empresas/cadastrar.tsx | ⬜ |
| Detalhe — seção Dados | 03-08 | 6 | HU-024 | build + regressão | `npm run typecheck && npm run build && php artisan test --compact --filter=CompanyUpdateTest` | resources/js/pages/portal/empresas/detalhe.tsx | ⬜ |
| Detalhe — CNAEs + Vínculos | 03-08 | 6 | HU-025/HU-026/HU-028 | build + regressão | `npm run typecheck && npm run build && php artisan test --compact --filter='PrimaryCnaeTest\|SecondaryCnaesTest\|EndCompanyLinkTest'` | resources/js/pages/portal/empresas/detalhe.tsx | ⬜ |
| Seeds dev (CompanySeeder) + verificação integral | 03-09 | 7 | todas | feature (seeds) + full suite + comando | `php artisan test --compact --filter=DatabaseSeederTest && php artisan test --compact && npm run typecheck && npm run build` | database/seeders/CompanySeeder.php + tests/Feature/Seeders/DatabaseSeederTest.php | ⬜ |
| Smoke E2E (checkpoint humano) | 03-09 | 7 | todas | manual | roteiro de 8 passos (Manual-Only abaixo) | — | ⬜ |

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

- [ ] Suíte atual verde no início da fase (regressão: 209/209 pós-fase 2.4) — baseline pelo ORQUESTRADOR antes de despachar waves paralelas
- [ ] `database/data/redesim-exemplo.json` (payload de referência) criado ANTES do RedesimImportTest
- [ ] Coordenação com a fase 2.4 (componentes de listagem) verificada antes das tasks de UI

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Smoke E2E navegável (roteiro de 8 passos) | Critério de pronto da fase | Validação visual/UX + integração CNPJ viva | Roteiro na "Validação manual no browser" do 03-RESEARCH.md (inclui busca real de CNPJ do Banco do Brasil) |
| Chamada REAL ao provider de CNPJ | HU-021 (integração real) | Evidência de integração viva contra a BrasilAPI | Passo 2 do roteiro + auditoria da consulta em activity_log |
| Import REDESIM via comando | HU-022 | Evidência fresca de execução real | `php artisan redesim:importar database/data/redesim-exemplo.json` (relatório no console + idempotência) |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies (mapa acima — 19 tasks automatizadas + 1 checkpoint manual)
- [x] Sampling continuity: no 3 consecutive tasks without automated verify (toda task tem verify próprio)
- [x] Wave 0 covers all MISSING references (redesim-exemplo.json é a Task 1 do 03-03, antes do RedesimImportTest; baseline da suíte é do orquestrador; coordenação 2.4 instruída nos planos de UI)
- [x] No watch-mode flags
- [x] Feedback latency < 60s (grupos filtrados ~5s; full-suite só no fechamento)
- [x] `nyquist_compliant: true` set in frontmatter

Nota: o RESEARCH citava "ParameterRegistryTest ganha as 3 chaves novas"; o teste correto do catálogo é `ParameterSeederTest` (ParameterRegistryTest cobre o model) — o mapa aponta para ParameterSeederTest.

**Approval:** planned 2026-06-11 (gsd-planner)
