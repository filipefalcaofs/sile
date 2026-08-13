---
phase: 15-relatorios-e-indicadores
plan: 01
subsystem: infra
tags: [openspout, echarts, parametros, permissoes, indices, hu-014, relatorios]

# Dependency graph
requires:
  - phase: 02-cadastros-estruturantes
    provides: ParameterSeeder + config/sile.php (catálogo HU-014) e RolesAndPermissionsSeeder
  - phase: 12-auditoria-e-compliance
    provides: baseline 85 parâmetros / 27 permissões
provides:
  - "openspout/openspout ^4.0 (driver XLSX streaming) sem subir o piso PHP ^8.3"
  - "echarts ^6.1 (gráficos do dashboard/série temporal)"
  - "migration aditiva de índices de desempenho dos relatórios (Pitfall 1)"
  - "5 parâmetros HU-014 do grupo relatorios (catálogo em 90)"
  - "2 permissões: consultar-relatorios e relatorios.produtividade.nominal (em 29)"
affects: [15-02-export-base, 15-03-indicadores, 15-04-tempo-produtividade, 15-05-quedas, 15-06-http, 15-09-dashboard]

# Tech tracking
tech-stack:
  added: [openspout/openspout ^4.0, echarts ^6.1]
  patterns:
    - "Parâmetro de negócio no catálogo + constante técnica só no config (precedente [02-02])"
    - "Parâmetro com default null NÃO espelhado no config → fallback honesto do call site"
    - "Migration ADITIVA de índices (sem alterar colunas), nome explícito quando o default estoura o limite do PostgreSQL"

key-files:
  created:
    - database/migrations/2026_06_15_000001_add_relatorios_indexes_to_viability_tables.php
  modified:
    - composer.json
    - package.json
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - database/seeders/RolesAndPermissionsSeeder.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Authorization/RolesAndPermissionsSeederTest.php

key-decisions:
  - "openspout ^4.0 (não v5): v5 exige ~8.4/8.5 e quebraria a promessa php ^8.3"
  - "meta_taxa OMITIDO do config/sile.php: null no config devolve null (Arr::get); ausente cai no fallback do call site (meta não definida, nunca inventada)"
  - "Sem permissão relatorios.exportar: a exportação herda a leitura da própria tela (RN-007)"
  - "Índice composto de transitions com nome explícito vrt_request_created_index (limite de 63 chars do PostgreSQL)"
  - "ReportFilters movido para 15-02 (mora com o contrato de export, evita acoplamento)"

patterns-established:
  - "Dono único da fundação (deps/config/catálogo/permissões/índices) por wave 1, espelhando Fases 10–12"

# Metrics
duration: ~45 min
completed: 2026-06-15
---

# Phase 15 Plan 01: Fundação dos Relatórios e Indicadores Summary

**openspout ^4.0 + echarts ^6.1 instalados sem subir o piso PHP ^8.3, migration aditiva de índices de relatório (protocoled_at, decided_at, (flow,outcome), decided_by_user_id, transitions), 5 parâmetros HU-014 (catálogo em 90) e 2 permissões (em 29) — fundação para destravar as waves seguintes.**

## Performance

- **Duration:** ~45 min
- **Started:** 2026-06-15T18:02:00Z (aprox.)
- **Completed:** 2026-06-15T18:31:00Z
- **Tasks:** 3
- **Files modified:** 7 (1 criado + 6 alterados)

## Accomplishments

- `openspout/openspout v4.32.0` instalado (require `~8.3.0 || ~8.4.0 || ~8.5.0`) — `composer.json` mantém `"php": "^8.3"`.
- `echarts ^6.1.0` em `dependencies` (resolvido para 6.1.0).
- Migration aditiva de índices de desempenho (RESEARCH Pitfall 1): `viability_requests.protocoled_at`; `viability_decisions.decided_at`, `(flow, outcome)`, `decided_by_user_id`; `viability_request_transitions (viability_request_id, created_at)` — up/down validados em PostgreSQL.
- 5 parâmetros HU-014 no grupo `relatorios` (catálogo 85 → 90) + espelho em `config/sile.php` com as constantes técnicas fora do catálogo.
- 2 permissões aditivas `consultar-relatorios` e `relatorios.produtividade.nominal` para gestor/admin (27 → 29).
- Testes de seeder atualizados (RED→GREEN): 90 parâmetros e 29 permissões, com testes dedicados às novas chaves/permissões.

## Task Commits

1. **Task 1: deps + migration de índices** — `e1802c5` (chore)
2. **Task 2: parâmetros + config + permissões** — `74a8d47` (feat)
3. **Task 3: testes de seeder 90/29** — `1265c1a` (test)

## Files Created/Modified

- `database/migrations/2026_06_15_000001_add_relatorios_indexes_to_viability_tables.php` — índices aditivos de relatório (up/down simétricos)
- `composer.json` / `composer.lock` — openspout/openspout ^4.0
- `package.json` / `package-lock.json` — echarts ^6.1 (staging seletivo, sem o vitest do usuário)
- `database/seeders/ParameterSeeder.php` — 5 parâmetros do grupo relatorios
- `config/sile.php` — bloco `relatorios.*` (parâmetros + constantes técnicas; meta_taxa omitida)
- `database/seeders/RolesAndPermissionsSeeder.php` — consultar-relatorios + relatorios.produtividade.nominal (gestor/admin)
- `tests/Feature/Seeders/ParameterSeederTest.php` — catálogo 90 + grupo relatorios + teste das 5 chaves
- `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` — idempotência 29 + teste das permissões de relatórios

## Decisions Made

- **openspout ^4.0 (não v5):** v5 exige `~8.4/8.5` e quebraria o piso `php ^8.3` do projeto; a API de escrita streaming é idêntica entre v4 e v5. Instalou v4.32.0.
- **`meta_taxa` omitido do `config/sile.php`:** o `Settings::get` resolve `config("sile.{chave}", $default)` primeiro; um leaf `null` no config faz `Arr::get` devolver `null` (não o default). Para a meta nascer honestamente "não definida" (`Settings::get("relatorios.expresso.meta_taxa", "indefinida")` → `"indefinida"`), a chave fica ausente do config. O parâmetro segue no catálogo com `default_value null` (admin pode definir). Espelha o padrão dos parâmetros nulos existentes (`govbr.client_id`).
- **Sem `relatorios.exportar`:** a exportação herda a permissão de leitura da própria tela (RN-007) — registrado no plano.
- **Índice composto de transitions com nome explícito (`vrt_request_created_index`):** o nome default (`viability_request_transitions_viability_request_id_created_at_index`, 67 chars) estouraria o limite de 63 chars do PostgreSQL e causaria mismatch no `dropIndex` do `down()`.
- **`ReportFilters` permanece em 15-02** (mora com o contrato de export).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug no plano] `meta_taxa` removido do espelho `config/sile.php`**
- **Found during:** Task 2 (config + acceptance do tinker)
- **Issue:** O plano mandava `'expresso' => ['meta_taxa' => null, ...]` no config, mas a própria acceptance exige que `Settings::get("relatorios.expresso.meta_taxa", "indefinida")` imprima `"indefinida"`. Verificado empiricamente que `Arr::get`/`config()` devolve o `null` existente (não o default) para um leaf null — o que faria a acceptance imprimir vazio.
- **Fix:** Omitir `meta_taxa` do bloco `relatorios.expresso` no config (mantendo `janela_dias`). O parâmetro segue no catálogo com `default_value null`.
- **Files modified:** config/sile.php, database/seeders/ParameterSeeder.php
- **Verification:** `php artisan config:show sile.relatorios.export.assincrono_limiar_linhas` → 5000; `Settings::get("relatorios.expresso.meta_taxa", "indefinida")` → "indefinida".
- **Committed in:** 74a8d47 (Task 2)

**2. [Rule 3 - Coordenação com trabalho paralelo] Migration validada em isolamento + staging seletivo**
- **Found during:** Tasks 1, 2 e 3 (havia trabalho paralelo NÃO commitado do usuário — Fase 14 IA/e-mail, vitest, sidebar)
- **Issue:** (a) Havia uma migration pendente do usuário (`create_ai_configurations_table`); um `php artisan migrate` global a executaria. (b) `package.json`/`package-lock.json` tinham mudanças de vitest do usuário. (c) Durante a sessão, o usuário modificou o próprio `RolesAndPermissionsSeeder.php` adicionando a permissão `manter-config-email` (28ª), colidindo com a contagem-alvo (29).
- **Fix:** (a) Migration aplicada/revertida/re-aplicada via `--path` (isolada), deixando a migration da Fase 14 intocada (segue Pending). (b) Staging seletivo via plumbing do git (`hash-object`/`update-index`) para commitar só `echarts` em `package.json`/lock, preservando o vitest do usuário não-staged. (c) `manter-config-email` foi isolado (seeder resetado para HEAD), permitindo testar/commitar a contagem limpa de 29; depois restaurado no working tree como alteração NÃO commitada (preservado).
- **Files modified (apenas no working tree, não commitados): RolesAndPermissionsSeeder.php (manter-config-email do usuário), package.json/lock (vitest do usuário).**
- **Verification:** índice vazio ao fim (nada do usuário staged); `git diff` do seeder = apenas +manter-config-email (mudança do usuário preservada); meus 3 commits contêm só relatorios.
- **Committed in:** e1802c5 / 74a8d47 / 1265c1a (somente conteúdo de relatorios)

---

**Total deviations:** 2 auto-corrigidas (1 bug do plano, 1 coordenação com trabalho paralelo)
**Impact on plan:** Sem mudança de escopo. As correções garantem a acceptance honesta da meta e preservam o trabalho paralelo do usuário.

## Issues Encountered

- **Edição paralela concorrente:** o usuário editava arquivos durante a sessão (Fase 14: IA + servidores de e-mail). A colisão relevante foi `manter-config-email` no `RolesAndPermissionsSeeder.php` (mesmo arquivo da Task 2). Resolvida isolando a mudança do usuário, commitando a base limpa (29) e restaurando a mudança dele como pendência não commitada.
- **Verificação evitando disrupção:** não rodei `php artisan migrate:fresh --seed` no banco de dev (apagaria o estado de dev e executaria as migrations pendentes da Fase 14). A evidência de "90/29 aplicados" vem dos testes de seeder (RefreshDatabase, exit 0), que migram e semeiam em banco isolado. A suíte completa (`composer test`) não foi rodada por incluir testes paralelos possivelmente incompletos do usuário (Ai/Email).

## User Setup Required

None - nenhuma configuração externa.

## Next Phase Readiness

- Fundação pronta: deps (openspout/echarts), índices, catálogo (90) e permissões (29) disponíveis para as waves seguintes (15-02 export base, 15-03+ serviços route-free, 15-06 HTTP, 15-09 dashboard).
- `ReportFilters` será entregue em 15-02 junto ao contrato de export.
- **Atenção (trabalho paralelo):** o working tree contém a permissão `manter-config-email` do usuário (Fase 14) NÃO commitada. Enquanto ela não for commitada com a respectiva atualização de contagem, o `RolesAndPermissionsSeederTest` (que assere 29) divergirá do working tree (30 permissões). Reconciliação a cargo do dono da Fase 14.

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-15*
