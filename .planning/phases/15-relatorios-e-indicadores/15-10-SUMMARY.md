---
phase: 15-relatorios-e-indicadores
plan: 10
subsystem: relatorios-export-retrofit
tags: [hu-131, rn-004, rn-005, rn-007, rn-008, rn-009, export, csv, xlsx, pdf, retrofit, anti-regressao, auditoria, lgpd]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-02)
    provides: "Contrato único ReportExporter/ReportDefinition/ReportSource + CsvExporter/PdfExporter + ReportFilters (bag)"
  - phase: 15-relatorios-e-indicadores (15-08)
    provides: "XlsxExporter (terceiro formato do contrato)"
  - phase: 12-auditoria-e-compliance (12-04)
    provides: "AuditoriaController::export (CSV streaming a consolidar) + meta-auditoria personal_data (HU-101)"
  - phase: 10-analise-tecnica (10-14)
    provides: "ProcessoController::exportarCsv (CSV a consolidar) + filtros SAPS"
provides:
  - "AuditoriaController::export consolidado no ReportExporter via AtividadesReportSource + AcessosReportSource (ganha XLSX/PDF pelo mesmo ?formato=)"
  - "ProcessoController CSV consolidado no ReportExporter via ProcessosReportSource dedicado (9 colunas, event=exporta-processos-csv)"
  - "ReportDefinition.maxRows (+ CsvExporter/PdfExporter/XlsxExporter) — guarda de volume format-agnóstica, backward-compatible (preserva auditoria.export.max_linhas)"
affects: [15-11-retrofit-fases-1-2, 15-15-fechamento]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Retrofit de export bespoke (fputcsv cru) → contrato único ReportExporter via ReportSource dedicado por tela (RN-009) — preservando colunas/arquivo/personalData (anti-regressão Pitfall 7)"
    - "Guarda de volume maxRows no ReportDefinition (consumida pelos 3 drivers) preserva limites herdados (auditoria.export.max_linhas) de forma format-agnóstica e backward-compatible"
    - "Source DEDICADO quando reusar um source existente quebraria contrato em produção (ProcessosReportSource ≠ SolicitacoesReportSource para não derrubar 15-03/15-09)"

key-files:
  created:
    - app/Services/Relatorios/Export/Sources/AtividadesReportSource.php
    - app/Services/Relatorios/Export/Sources/AcessosReportSource.php
    - app/Services/Relatorios/Export/Sources/ProcessosReportSource.php
    - tests/Feature/Relatorios/RetrofitCsvAuditoriaTest.php
    - tests/Feature/Relatorios/RetrofitCsvProcessosTest.php
  modified:
    - app/Http/Controllers/Gestao/AuditoriaController.php
    - app/Http/Controllers/Gestao/ProcessoController.php
    - app/Services/Relatorios/Export/ReportDefinition.php
    - app/Services/Relatorios/Export/CsvExporter.php
    - app/Services/Relatorios/Export/PdfExporter.php
    - app/Services/Relatorios/Export/XlsxExporter.php

key-decisions:
  - "ProcessosReportSource DEDICADO (não reuso do SolicitacoesReportSource de 15-03): este já está em produção no RelatorioController (15-09), travado por teste (10 colunas, event=exporta-solicitacoes). Reusá-lo para o CSV de 9 colunas de processos quebraria 15-03/15-09. A fonte nova preserva EXATAMENTE as 9 colunas, o nome do arquivo e o audit analise/exporta-processos-csv."
  - "maxRows adicionado ao contrato (ReportDefinition + 3 drivers) para preservar auditoria.export.max_linhas de forma format-agnóstica — backward-compatible (default null = sem limite), nenhum source/teste anterior afetado."
  - "Auditoria e Processos passam a oferecer XLSX/PDF além do CSV, pelo MESMO ?formato= — a consolidação RN-009 paga a dívida e amplia os formatos sem reimplementar."
  - "Anti-regressão Pitfall 7 honrada: colunas, nome do arquivo e personalData/HU-101 preservados; testes existentes de Auditoria/Processos seguem verdes."

# Metrics
duration: ~15 min
completed: 2026-06-16
---

# Phase 15 Plan 10: Retrofit dos CSVs (Auditoria + Processos) ao contrato único Summary

**Os dois exports CSV bespoke do projeto (`AuditoriaController::export` — atividades e acessos — e `ProcessoController::exportarCsv`) deixaram de usar `fputcsv` cru e passaram a delegar ao `ReportExporter` por meio de `ReportSource`s dedicados (`AtividadesReportSource`, `AcessosReportSource`, `ProcessosReportSource`), pagando a dívida transversal da HU-131/RN-009: as telas ganham XLSX/PDF pelo mesmo `?formato=`, com colunas/arquivo/`personalData` preservados (anti-regressão) e uma guarda de volume `maxRows` format-agnóstica no contrato.**

## Performance

- **Duration:** ~15 min (3 commits atômicos + finalização/verificação)
- **Completed:** 2026-06-16
- **Tasks:** 3 (cada uma com commit atômico)
- **Files:** 5 criados (3 sources + 2 testes), 6 modificados (2 controllers + 4 do contrato)

## Accomplishments

- **Auditoria (HU-101 → RN-009):** `AuditoriaController::export` (atividades + acessos) consolidado no `ReportExporter` via `AtividadesReportSource` + `AcessosReportSource`; o `?formato=` agora aceita `csv|xlsx|pdf` (antes só CSV), mantendo a meta-auditoria `personal_data`.
- **Processos (HU-082 → RN-009):** `ProcessoController` (CSV de 9 colunas) consolidado via `ProcessosReportSource` dedicado, preservando colunas/arquivo e o audit `analise/exporta-processos-csv`; ganha XLSX/PDF.
- **Guarda de volume:** `ReportDefinition.maxRows` (lido por `CsvExporter`/`PdfExporter`/`XlsxExporter`) preserva `auditoria.export.max_linhas` de forma format-agnóstica e backward-compatible.
- **RN-005/RN-007 no assíncrono:** o `GerarExportacaoJob` recebe o bag filtrado e reconstrói só o recorte (sem dump da trilha); minimização de PII preservada.

## Task Commits

1. **Task 1: AtividadesReportSource + retrofit do export de atividades da auditoria** — `1530719` (feat)
2. **Task 2: AcessosReportSource + ProcessosReportSource + retrofit (auditoria acessos + processos)** — `844570a` (feat)
3. **Task 3: testes de retrofit (XLSX/PDF + RN-005) — RetrofitCsvAuditoriaTest + RetrofitCsvProcessosTest** — `8343f1b` (test)

**Plan metadata:** este SUMMARY + STATE.md (docs: complete plan)

## Anti-regressão (evidência fresca)

`php artisan test --compact --filter='Auditoria|Processo|Relatorio'` → **251/251, 1221 asserções, exit 0** (verificado pelo orquestrador). Colunas, nome do arquivo e `personalData` preservados; os testes existentes de Auditoria/Processos seguem verdes + novos casos XLSX/PDF e RN-005 assíncrono cobertos por `RetrofitCsvAuditoriaTest` (257 linhas) e `RetrofitCsvProcessosTest` (124 linhas). Pint limpo.

## Decisions Made

- **Source dedicado para processos** (`ProcessosReportSource`), NÃO reuso do `SolicitacoesReportSource` (15-03): o de 15-03 está em produção no `RelatorioController` e travado por teste (10 colunas, `event=exporta-solicitacoes`). Reusá-lo para o CSV de 9 colunas de processos quebraria 15-03 e 15-09. A fonte dedicada preserva o contrato exato do CSV de processos.
- **`maxRows` no contrato** (`ReportDefinition` + 3 drivers): preserva o limite `auditoria.export.max_linhas` herdado, sem acoplar o limite a um formato. Default `null` (sem limite) → nenhum source/teste anterior afetado.

## Deviations from Plan

**1. [Refinamento] Source dedicado em vez de ajuste do SolicitacoesReportSource** — o plano sugeria reusar/ajustar o source de solicitações para processos; criar um `ProcessosReportSource` dedicado evita quebrar o contrato já em produção de 15-03/15-09. Sem mudança de escopo (RN-009 atendida: contrato único, sem `fputcsv` duplicado).

**2. [Coordenação] Continuação + trabalho paralelo da Fase 14** — execução em commits de continuação com staging seletivo; o working tree mantém o trabalho NÃO commitado do usuário (Fase 14 IA/e-mail + sidebar). Nenhum arquivo da Fase 14 foi tocado/commitado; `routes/gestao.php` (que carrega o `config-email` não-commitado do usuário) NÃO foi tocado por este plano (o retrofit usa o `?formato=` já existente nas rotas do index). `pint` escopado aos arquivos do 15-10.

**Nota de orquestração:** o executor reportou uma suposta "execução concorrente", mas a auditoria do git confirmou que os 3 commits `feat/test(15-10)` (10:37/10:43/10:48) são do mesmo autor e sequenciais — foi um único executor; a narrativa de concorrência foi equívoco. O orquestrador finalizou o SUMMARY + STATE que o executor deixou pendente, após verificação fresca (251/251).

## Issues Encountered

- **Confusão de narrativa do executor** sobre "outro agente": resolvida por auditoria do git (autor/horários sequenciais). Nenhum clobber; código verde e commitado.

## Next Phase Readiness

- **15-11 (retrofit Fases 1–2):** o padrão de `ReportSource` dedicado por tela + branch `?formato=` no controller (sem nova rota) está estabelecido e provado aqui; as listagens de CNAEs/usuários/perfis/parâmetros/acessos seguem o mesmo molde, com o `<ExportMenu>` (15-12) nas páginas e a máscara `cpf_masked` (RN-007) onde houver PII.
- **15-15 (retenção):** sem pendências novas; o contrato e os drivers estão estáveis (maxRows incluído).

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-16*
