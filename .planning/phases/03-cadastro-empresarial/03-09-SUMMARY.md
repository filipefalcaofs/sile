---
phase: 03-cadastro-empresarial
plan: 09
subsystem: seeds-verificacao
tags: [seeders, dev-data, redesim-import, e2e, brasilapi, verification]

requires:
  - phase: 03-cadastro-empresarial
    plan: 03
    provides: RedesimImportService->import (lógica real de import REDESIM por CNPJ)
  - phase: 03-cadastro-empresarial
    plan: 04
    provides: Company/CompanyUser (vínculo responsavel) e listagem Minhas empresas
  - phase: 03-cadastro-empresarial
    plan: 06
    provides: company_cnae com is_primary (CNAE principal)
provides:
  - CompanySeeder (cidadão dev + empresa manual + empresas REDESIM importadas pela lógica real)
  - CompanySeeder integrado ao DatabaseSeeder após CnaeSeeder/DevAdminSeeder
  - Ambiente dev navegável com dados de exemplo reais (CNPJs públicos)
affects: [fase-4 a fase-15 (ambiente dev semeado), fase-13 (associação empresa importada ↔ usuário)]

tech-stack:
  added: []
  patterns:
    - "Seed de dev idempotente com firstOrCreate em usuário, empresa, vínculo e LGPD"
    - "Empresa REDESIM semeada pela MESMA lógica de produção (RedesimImportService) — muda a carga, nunca o comportamento (regra do projeto)"
    - "Vínculo usuário-empresa da carga REDESIM é conveniência de DEV explicitada (o serviço nunca cria vínculo por design — associação real na Fase 13)"

key-files:
  created:
    - database/seeders/CompanySeeder.php
  modified:
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "Cidadão dev cidadao@sile.dev / password, CPF 52998224725 (válido, distinto do DevAdmin 11144477735) — credenciais de DESENVOLVIMENTO, nunca produção"
  - "Empresa manual: MAGAZINE LUIZA S/A (CNPJ público 47.960.950/0001-21), CNAE principal 4713-0/04 (lojas de departamentos/magazines)"
  - "Empresas REDESIM importadas do payload de referência (Banco do Brasil 6422-1/00 + Petrobras 0600-0/01); cidadão dev vinculado ao Banco do Brasil para popular Minhas empresas"
  - "DatabaseSeederTest ampliado (não substituído): cidadão dev com >=2 vínculos ativos, 3 empresas, manual+redesim, CNAE principal presente; idempotência assertada (3 empresas, 2 vínculos após re-seed)"

requirements: [HU-021, HU-022, HU-023, HU-024, HU-025, HU-026, HU-027, HU-028]
---

# 03-09 — Fechamento da Fase 3: seeds dev + verificação integral + smoke E2E

## One-liner
Ambiente de desenvolvimento semeado com cidadão de teste e empresas de exemplo (manual + REDESIM via import real), verificação integral fresca verde e smoke E2E navegável validado no browser com chamada REAL à BrasilAPI.

## O que foi entregue (Task 1 — auto)
- `CompanySeeder`: cidadão dev (`cidadao@sile.dev`/`password`, papel cidadao, termo LGPD aceito), empresa manual (Magazine Luiza com CNAE principal 4713-0/04) e empresas REDESIM importadas por `RedesimImportService->import(redesim-exemplo.json)` — lógica de produção, carga de dev. Idempotente (firstOrCreate).
- `DatabaseSeeder`: `CompanySeeder` chamado após `CnaeSeeder`/`DevAdminSeeder`.
- `DatabaseSeederTest`: 2 cenários novos (cidadão dev com empresas; idempotência das empresas/vínculos).

## Evidência fresca da verificação integral (2026-06-13)
- `vendor/bin/pint --dirty --format agent` → **passed**.
- `php artisan test --compact` → **367 testes, 1876 assertions, 367 passed** (0 falhas).
- `npm run typecheck` → verde (tsc sem erros). `npm run build` → verde (vite, ~320 kB app).
- `php artisan migrate:fresh --seed --no-interaction` no banco dev real (pgsql `sile`) → 6 seeders DONE, sem erros.
- `php artisan redesim:importar database/data/redesim-exemplo.json` (2x) → `Lidos: 2 / Importados: 0 / Atualizados: 2` nas duas execuções (idempotente — empresas já vieram do seed).
- `route:list` → 9 rotas em `portal/empresas/*` + `portal.cnaes.search` presentes.
- Banco dev: `companies=3 (manual=1, redesim=2)`, cidadão dev com `vinculos_ativos=2`.

## Smoke E2E (Task 2 — validação automatizada por browser, Playwright)
Fluxos exercitados no navegador real contra o servidor dev (`composer run dev`):
1. **Login do cidadão** (CPF 529.982.247-25) → "Meu painel" (Cidadão SILE).
2. **Minhas empresas**: lista com Banco do Brasil (badge **REDESIM**) e Magazine Luiza (badge **Cadastro manual**), CNAE principal e vínculo **Ativo/Responsável** (screenshot `output/playwright/01-minhas-empresas.png`).
3. **Detalhe da empresa**: CNPJ em campo **desabilitado** com "O CNPJ não pode ser alterado.", seções CNAE principal/secundários, Vínculos (Cidadão SILE — Você — Responsável — Ativo) e "Encerrar meu vínculo" (`02-detalhe-empresa.png`).
4. **Cadastrar empresa + lookup VIVO (HU-021)**: CNPJ do Itaú (60.701.190/0001-04) → "Buscar CNPJ" → formulário preenchido com dados REAIS da BrasilAPI (ITAU UNIBANCO S.A., natureza jurídica, porte, endereço SAO PAULO/SP, CEP, telefone) (`03-cadastrar-empresa.png`, `04-lookup-vivo-cnpj.png`).
- Integração viva confirmada também via serviço: `app(CnpjLookup::class)->lookup('00000000000191')` retornou "BANCO DO BRASIL SA".

## Cobertura por testes automatizados (CAs das 8 HUs)
Grupo Companies 83 verdes na wave 6; full-suite 367 verde inclui `MyCompaniesTest`, `CompanyRegistrationTest`, `CnpjLookupTest` (toggle ON/OFF — degradação comunicada), `CompanyUpdateTest` (CNPJ imutável), `PrimaryCnaeTest`/`SecondaryCnaesTest` (CA-03 só ativos, auditoria antes/depois), `EndCompanyLinkTest` (proteção do último responsável), `RedesimImportTest`.

## Pendências para a pauta SEDUR
- Payload REDESIM é REFERÊNCIA a validar com a SEDUR (integrador REGIN/JUCEB) — transporte real é HU-103 (Fase 13).
- Associação empresa importada ↔ usuário do portal: o `RedesimImportService` não cria vínculo por design; o seed vincula em DEV por conveniência. A associação real (quem solicitou) chega com o transporte da Fase 13.

## Notas
- Screenshots ficam em `output/playwright/` (gitignored) — evidência local, não versionada.
- O checkpoint humano formal do plano (passos manuais de representação HU-027 e encerramento do último responsável HU-028) está coberto por feature tests; a validação visual cobriu os fluxos navegáveis principais.
