---
phase: 15-relatorios-e-indicadores
plan: 15
subsystem: backend+verificacao
tags: [hu-131, hu-122, hu-129, hu-145, retencao, prunable, seeds, golden-smoke, verificacao-integral, guardiao, anti-fachada, fechamento]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-02/15-08)
    provides: "ExportFile + ReportExporter (contrato único CSV/XLSX/PDF) e GerarExportacaoJob"
  - phase: 15-relatorios-e-indicadores (15-09/15-10/15-11/15-13/15-14)
    provides: "Camada HTTP, retrofit dos CSVs/listagens, dashboard e telas — tudo sobre dado real"
provides:
  - "Pruning de retenção do ExportFile (Prunable + Storage::delete, retenção parametrizada) agendado idempotente"
  - "Comando relatorios:exportar — evidência end-to-end de 1 arquivo REAL por formato a partir de seeds"
  - "RelatoriosDevSeeder — massa de dev pelo FLUXO REAL (decisões/transições/quedas), nunca número cravado"
  - "Golden/smoke do export (CSV/XLSX/PDF + vazio honesto) e verificação integral fresca + veredito APROVADO do guardião"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Prunable (não MassPrunable) quando há efeito colateral físico: pruning() remove o arquivo do Storage antes de apagar o registro (sem órfão), corte por relatorios.export.retencao_dias via Settings — parametrização sem deploy (3.1)"
    - "Comando de evidência reusa o caminho REAL de produção (write() dos drivers, contagem via builder()->count()) — anti-fachada: prova a lógica, não simula"
    - "Seed dev gated por ambiente (local), idempotente por requerente dedicado — fora de testing para não contaminar as contagens exatas da suíte"

key-files:
  - app/Models/ExportFile.php
  - routes/console.php
  - app/Console/Commands/RelatoriosExportarCommand.php
  - database/seeders/RelatoriosDevSeeder.php
  - database/seeders/DatabaseSeeder.php
  - tests/Feature/Relatorios/ExportRetencaoPruningTest.php
  - tests/Feature/Relatorios/RelatoriosGoldenSmokeTest.php
  - tests/Feature/Seeders/DatabaseSeederTest.php (baseline corrigido)
  - tests/Feature/Roles/ManageRolesTest.php (baseline corrigido)

requirements: [HU-131, HU-122, HU-129, HU-145]
verification: passed
guardiao: APROVADO
status: complete
---

# 15-15 — Fechamento da Fase 15 (retenção, evidência de export, verificação integral, guardião)

## O que foi entregue

- **Pruning de retenção (HU-131, padrão 3.1):** `ExportFile` usa `Prunable` com `prunable()` (corte por `created_at < now()->subDays(Settings::get('relatorios.export.retencao_dias'))`) e `pruning()` que remove o arquivo do `Storage` ANTES de apagar o registro — sem arquivo órfão. `routes/console.php` agenda o prune diariamente, idempotente (`withoutOverlapping`/`onOneServer`), espelhando o pruning de `access_logs`. Mudar `retencao_dias` via parâmetro muda o corte sem deploy (Dimensão 5).
- **Comando de evidência `relatorios:exportar {source?} {--formato=}`:** monta um `ReportFilters` e chama o `ReportExporter` pelo MESMO `write()` dos drivers que o `GerarExportacaoJob` (caminho real de produção); contagem via `builder()->count()` (nunca cravada); recusa disco público (LGPD); conjunto vazio gera arquivo só com cabeçalho; source desconhecido → exit 1. Prova anti-fachada do export ponta a ponta.
- **`RelatoriosDevSeeder`:** gera massa de dev pelo FLUXO REAL (factories de `ViabilityRequest`/`ViabilityDecision`/transições/`ExpressoQueda` em estados variados — deferidas/indeferidas/em análise/quedas), gated por ambiente `local`, idempotente. A tela calcula sobre o dado; nada é cravado.
- **Golden/smoke (`RelatoriosGoldenSmokeTest`):** CSV (cabeçalho + N linhas), XLSX (relido pelo `Reader` do openspout com N linhas), PDF (`%PDF` + "Total de registros: N"); caso vazio = arquivo só com cabeçalho (nunca número inventado).

## Correção de regressão detectada pela verificação integral

A suíte global (rodada pela 1ª vez desde o 15-01) revelou que o commit `1265c1a` (15-01) atualizou `RolesAndPermissionsSeederTest`/`ParameterSeederTest` para os baselines da Fase 15, mas **esqueceu** `DatabaseSeederTest` e `ManageRolesTest` (ficaram em 27 permissões / 85 parâmetros). Como cada plano rodava só `--filter=Relatorios`, a regressão passou despercebida até aqui. Corrigida no commit `9fa2376`: baselines → **29 permissões** (27 + as 2 de relatórios) e **90 parâmetros** (85 + os 5 de relatórios), o estado commitado real.

## Verificação integral fresca (evidência, não "deve passar")

- `php artisan test --compact --filter=Relatorios` → **126/126 (553 asserções)**.
- `php artisan test --compact --filter=DashboardKpis` → **5/5 (89 asserções)**.
- `php artisan test --compact --exclude-group postgis` (global) → **1465 testes, 1461 passaram**. As 4 falhas eram baseline: 1 (parâmetros) era regressão real da fase, **corrigida** em `9fa2376`; as 3 restantes são **contaminação do working tree da Fase 14** (`manter-config-email` não commitado), todas uniformemente "30 is identical to 29". Prova: `git show HEAD:...RolesAndPermissionsSeeder.php | grep -c manter-config-email` = **0** (commitado tem 29); `git diff` mostra +`manter-config-email`. No estado commitado (29), os 3 passam. Reconciliação dos baselines para 30 cabe ao dono da Fase 14 ao commitar.
- `php artisan test --compact --group postgis` → **29/29 (184 asserções)**.
- `npx tsc --noEmit` → exit 0; `npm run build` → exit 0.
- Export real provado: `relatorios:exportar` gerou CSV/XLSX/PDF reais (24 registros) num SQLite temporário descartável (NÃO o banco de dev do usuário); pruning testado 3/3.
- **Guardião-entrega: APROVADO** com evidência fresca independente (anti-fachada CA-03: KPI sem delta; export == conjunto filtrado RN-005; export falho não vira "pronto"; zona→bairro com ressalva; feriado nunca inventado; gatilho null degradado; "meta não definida"; auditoria RN-002/008; parametrização HU-014; TDD).

## Decisões registradas

- **Feriados sem `<ExportMenu>`** (aceita pelo guardião): `HolidayController` é CRUD de parâmetro, não relatório (sem `?formato=`); um botão que recarrega a tela seria fachada. As 3 telas de relatório têm export real.
- **Telas de relatório filtram por período** (+ filtros que o serviço aplica): a UI não oferece controle de filtro sem efeito (anti-fachada).
- **`pint --dirty` global e `migrate:fresh --seed` global NÃO rodados** — preservam o trabalho não commitado e o banco de dev da Fase 14; pint rodado nos arquivos da fase; export provado por teste isolado + SQLite temporário.

## Baselines finais

- Parâmetros HU-014: **90** (grupo `relatorios` +5 sobre os 85 da Fase 12).
- Permissões: **29** commitadas (`consultar-relatorios`, `relatorios.produtividade.nominal` sobre as 27 da Fase 12).
- Suíte: 126/126 (Relatorios) + 29/29 (postgis); global 1461/1465 no working tree (3 falhas = `manter-config-email` da Fase 14, alheio ao EP15).

## Bloqueios/pendências honestos remanescentes (registrados, nunca simulados)

- **Zona urbanística oficial (Quadro LOUOS/GIS)** — pendência SEDUR; HU-124 degrada para bairro com rótulo explícito.
- **Lista oficial de feriados municipais de Salvador** — pendência SEDUR; cálculo desconta só os cadastrados, com ressalva visível (HU-137).
- **Meta da taxa de resposta expressa (`relatorios.expresso.meta_taxa`)** — decisão SEDUR; UI mostra "meta não definida".
- **Produtividade nominal / colunas SAPS / PII por coluna** — pendências SEDUR/DPO; default conservador (anônimo / sem PII).
- **Entrada na sidebar lateral (grupo "Relatórios")** — adicionada ao `gestao-layout.tsx`, que está não commitado (refator da Fase 14). NÃO é fachada: as telas são reais e acessíveis por rota direta (testadas), Cmd+K (commitado `76aba17`) e KPIs do dashboard. Integração na sidebar depende do commit da Fase 14.
- **Smoke navegável humano** — único gate de UI pendente (a critério do usuário, como nas Fases 8–12).

## Commits (staging seletivo; nada da Fase 14 commitado)

- `29c5f07` — Task 1: pruning de retenção (`ExportFile`, `routes/console.php`, `ExportRetencaoPruningTest`).
- `2ce5657` — Task 2: comando `relatorios:exportar` + `RelatoriosDevSeeder` + `DatabaseSeeder` (+6) + `RelatoriosGoldenSmokeTest`.
- `9fa2376` — fix de baseline: `DatabaseSeederTest`/`ManageRolesTest` → 29 permissões / 90 parâmetros (regressão do 15-01).
