---
phase: 02-administracao-base
plan: 08
subsystem: admin
tags: [seeders, smoke-e2e, checkpoint, navegacao]

# Dependency graph
requires:
  - phase: 02-04
    provides: CnaeSeeder com import oficial auditado
  - phase: 02-02
    provides: ParameterSeeder com catálogo de 10 chaves
  - phase: 02-05, 02-06, 02-07
    provides: telas administrativas verificadas no smoke
provides:
  - DatabaseSeeder completo (papéis + termo LGPD + admin dev + parâmetros + CNAEs)
  - Navegação permissionada do GestaoLayout (Painel, CNAEs, Usuários, Perfis, Parâmetros)
  - Verificação integral da fase com evidência fresca
  - Aprovação humana do smoke E2E (checkpoint)
affects: [todas as fases seguintes (seeds), 03-cadastro-empresarial]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Navegação da gestão filtrada por permissão via props compartilhadas auth.permissions"

key-files:
  created: []
  modified:
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Seeders/DatabaseSeederTest.php
    - resources/js/layouts/gestao-layout.tsx

key-decisions:
  - "Seed completo idempotente: ParameterSeeder preserva valores administrados em re-seed (teste dedicado)"
  - "Nav da gestão renderiza itens conforme permissões do usuário — analista sem manter-* não vê links de manutenção"

patterns-established: []

# Verificação integral (evidência fresca — 2026-06-10)
verification:
  - "php artisan test --compact → 197/197 testes, 915 asserções"
  - "vendor/bin/pint --dirty → passed; npm run typecheck e npm run build → verdes"
  - "Seed real (pgsql): Cnae::count() = 1331; 0111301 = 'Cultivo de arroz'; Parameter::count() = 10; auditoria do import com esperado_publicacao 1332 e divergência 9900-8/00"
  - "route:list --path=gestao: 16 rotas"
  - "git diff dos testes protegidos da Fase 1: vazio"
  - "Smoke E2E navegável (roteiro de 7 passos) aprovado pelo usuário em 2026-06-10 — checkpoint humano"

concerns:
  - "migrate:fresh --seed recriou o banco de desenvolvimento (exigência do plano) — usuários de teste anteriores apagados"
---

# Plano 02-08 — Fechamento da Fase 2: seeds, navegação e smoke E2E

Fecha a Fase 2: DatabaseSeeder integrado (papéis, termo LGPD, admin dev, parâmetros, CNAEs oficiais), navegação permissionada da gestão, verificação integral verde e smoke E2E aprovado por humano.

## Tasks

| # | Task | Status | Commits |
|---|------|--------|---------|
| 1 | DatabaseSeeder completo + navegação permissionada (TDD) | Completa | `e13223e` (RED), `d25ff8e` (GREEN) |
| 2 | Verificação integral com evidência fresca | Completa | — |
| 3 | Smoke E2E navegável (checkpoint humano) | Aprovado pelo usuário | — |

## Resultado

- Seed completo em um comando (`php artisan migrate:fresh --seed`): 4 papéis, 8 permissões, termo LGPD v1, admin dev, 10 parâmetros, 1.331 CNAEs oficiais com relatório auditado.
- Navegação da gestão por permissão: cada item do nav aparece apenas para quem pode usá-lo.
- Smoke E2E dos 7 passos aprovado: CNAEs (busca/edição/desativação), usuários (inativação real, link acessos), perfis (anti-lockout), parâmetros (efeito sem deploy, validação, histórico), toggle de procurações (degradação comunicada), 403 auditado.

---
*Concluído: 2026-06-10 — checkpoint humano aprovado*
