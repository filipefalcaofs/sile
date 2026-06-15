---
phase: 15-relatorios-e-indicadores
plan: 08
subsystem: relatorios-export
tags: [hu-131, export, xlsx, openspout, streaming, rn-005, queue]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-01)
    provides: dependência openspout/openspout ^4.0 (v4.32.0) + bloco config/sile.php relatorios.export (formatos_habilitados já com 'xlsx')
  - phase: 15-relatorios-e-indicadores (15-02)
    provides: contrato de exportação (ReportDefinition/ReportSource/ReportFormatExporter) + CsvExporter/PdfExporter + ReportExporter + GerarExportacaoJob + ExportFile
provides:
  - "XlsxExporter (driver XLSX por streaming openspout v4, baixa memória via cursor())"
  - "Terceiro ramo 'xlsx' no resolver de driver do caminho SÍNCRONO (ReportExporter) E do ASSÍNCRONO (GerarExportacaoJob)"
  - "Contrato único HU-131 com os três formatos (CSV/XLSX/PDF) disponíveis e testados de ponta a ponta"
affects: [15-06-http-telas, 15-09-dashboard-download]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "XLSX por streaming de baixa memória: cursor() do Builder filtrado (RN-005), nunca all() em memória"
    - "stream() gera o XLSX (ZIP) num arquivo temporário e o devolve por response()->streamDownload + fpassthru — evita openToBrowser (que chama header()/ob_end_clean por fora e conflita com o ciclo da Response do Symfony)"
    - "write() grava em caminho de filesystem REAL via openToFile (Pitfall 4) — encaixe direto no GerarExportacaoJob"

key-files:
  created:
    - app/Services/Relatorios/Export/XlsxExporter.php
    - tests/Feature/Relatorios/XlsxExporterTest.php
  modified:
    - app/Services/Relatorios/Export/ReportExporter.php
    - app/Jobs/GerarExportacaoJob.php
    - tests/Feature/Relatorios/ReportExporterTest.php

key-decisions:
  - "stream() final: response()->streamDownload envolvendo um XLSX construído em tempnam e enviado por fpassthru (NÃO openToBrowser, NÃO StreamedResponse cru, NÃO openToFile('php://output')). Motivo: openToBrowser do openspout chama header()/ob_end_clean() por fora, conflitando com o ciclo de headers da Response do Symfony e impedindo asserir o Content-Type na resposta (conflito previsto pelo plano)."
  - "write() usa openToFile(caminho real) — Pitfall 4 (XLSX é ZIP e exige arquivo de filesystem gravável); mesmo loop cursor() do stream()."
  - "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet."
  - "API de objeto do openspout v4 (new Writer(new Options()) + Row::fromValues + Style::setFontBold) — NUNCA WriterEntityFactory (v3)."
  - "O resolver de driver é DUPLICADO (ReportExporter::driver no síncrono e GerarExportacaoJob::driver no assíncrono); por isso o terceiro formato precisou ser habilitado nos DOIS. Registrado como dívida: extrair um resolver único evitaria a classe de bug do 07d1c8a."

# Metrics
duration: ~continuação (Task 1/2 + fix já commitados por execução concorrente; verificação + docs nesta sessão)
completed: 2026-06-15
---

# Phase 15 Plan 08: Driver XLSX por streaming (HU-131) Summary

**O terceiro formato do contrato único (HU-131) entregue como o único código de export genuinamente novo: `XlsxExporter` por streaming openspout v4 (baixa memória via `cursor()`, RN-005), ligado ao resolver de driver tanto do caminho SÍNCRONO (`ReportExporter`) quanto do ASSÍNCRONO (`GerarExportacaoJob`). Os três formatos (CSV/XLSX/PDF) ficam disponíveis e testados de ponta a ponta — o XLSX é reaberto com o `Reader` do openspout nos testes e prova conter exatamente o conjunto filtrado.**

## Accomplishments

- **Task 1** (commit `817bd0b`): `XlsxExporter` (`final class implements ReportFormatExporter`) — `write()` grava por `openToFile` (Pitfall 4) e `stream()` gera o XLSX em `tempnam` e o envia por `response()->streamDownload` + `fpassthru`; ambos iteram o Builder filtrado com `cursor()` (RN-005, baixa memória). `XlsxExporterTest::write_gera_xlsx_legivel_...` reabre o arquivo com o `Reader` do openspout e prova cabeçalho + apenas o conjunto filtrado.
- **Task 2** (commit `6eeaa09`): `ReportExporter` — `DRIVERS_DISPONIVEIS` ampliado para `['csv','xlsx','pdf']` e `match` ganhou `'xlsx' => app(XlsxExporter::class)`; `formatos_habilitados` já trazia `'xlsx'` desde 15-01 (config/sile.php). Casos de teste xlsx síncrono (200 + Content-Type xlsx + auditoria `exporta-processos-xlsx` + volume) e assíncrono (202 + `assertPushed(GerarExportacaoJob)` com formato `xlsx`). Teste anti-fachada `formato_nao_disponivel_e_recusado` do `ReportExporterTest` REapontado de `'xlsx'` para `'json'` (xlsx deixou de ser indisponível em 15-08; o guarda permanece para formatos desconhecidos) — anti-regressão mantida verde.
- **Fix async** (commit `07d1c8a`): `GerarExportacaoJob::driver()` (resolver DUPLICADO do caminho assíncrono) também ganhou `'xlsx' => app(XlsxExporter::class)`. Bug pego pelo teste de ponta a ponta `job_assincrono_grava_xlsx_legivel_e_filtrado_no_disco`, que roda o `handle()` com formato `xlsx`, confere o `ExportFile` (format/row_count) e reabre o XLSX gravado no disco provando o conjunto filtrado (RN-005 no assíncrono).

## API do XlsxExporter

### `App\Services\Relatorios\Export\XlsxExporter` (`final class implements ReportFormatExporter`)
- `stream(ReportDefinition $definition): Response` — constrói o XLSX num `tempnam`, devolve `response()->streamDownload(fn() => fpassthru($tmp), fileName('xlsx'), ['Content-Type' => '…spreadsheetml.sheet'])` e remove o temporário ao fim do envio.
- `write(ReportDefinition $definition, string $absolutePath): void` — `new Writer(new Options())` → `openToFile($absolutePath)` (caminho REAL, Pitfall 4) → cabeçalho em negrito (`Row::fromValues(columnLabels(), (new Style)->setFontBold())`) → loop `builder()->cursor()` com `Row::fromValues(array_values(mapRow($model)))` → `close()`.
- Mesmo loop privado serve ao síncrono e ao assíncrono; sempre `cursor()` (nunca `all()`/`get()`) — baixa memória (RN-005).

### Como o xlsx entrou no ReportExporter (e no Job)
- `ReportExporter::driver()` (síncrono): `match` agora resolve `'xlsx' => app(XlsxExporter::class)`; `DRIVERS_DISPONIVEIS = ['csv','xlsx','pdf']` faz a validação `formatos_habilitados ∩ DRIVERS_DISPONIVEIS` aceitar xlsx.
- `GerarExportacaoJob::driver()` (assíncrono): `match` espelhado com `'xlsx' => app(XlsxExporter::class)` — necessário porque o resolver é duplicado; sem isso, exportações xlsx acima do limiar quebrariam no worker (a fachada que o teste e2e impediu).

## Deviations from Plan

- **[Rule 1/2 — Bug crítico] Driver xlsx ausente no caminho assíncrono.** O plano assumia que habilitar o driver no `ReportExporter` bastava, mas o resolver de driver é DUPLICADO (`GerarExportacaoJob::driver()` tem o próprio `match`). Sem o ramo xlsx lá, toda exportação xlsx acima do limiar lançaria `InvalidArgumentException` no worker — feature de fachada. Corrigido em `07d1c8a` adicionando o ramo (arquivo fora da lista literal de 3 arquivos, mas é arquivo da própria frente de relatórios — 15-02 — e a correção é exigida pela regra entrega-funcional). Coberto pelo teste e2e `job_assincrono_grava_xlsx_legivel_e_filtrado_no_disco`.
- **[Refinamento] Teste e2e do caminho assíncrono.** O plano deixava a prova do write assíncrono para os testes de 15-02; foi adicionado um teste que roda o `GerarExportacaoJob::handle()` em xlsx e reabre o arquivo do disco (prova RN-005 async de ponta a ponta) — foi ele que revelou o bug acima.
- **[Coordenação] Execução concorrente no mesmo working tree.** Tasks 1/2 e o fix foram commitados por uma execução paralela do próprio 15-08 (commits acima) enquanto outras frentes (15-03/15-06 + Fase 14 do usuário) commitavam em paralelo. Esta sessão VERIFICOU o estado no HEAD (verde) e produziu os artefatos de fechamento (SUMMARY/STATE) por pathspec seletivo, sem tocar arquivos de outras frentes.

## Verification (evidência fresca)

- `php artisan test --compact --filter='XlsxExporterTest|ReportExporterTest'` → **10/10, 31 asserções** (XlsxExporterTest 4: write/Reader RN-005, xlsx síncrono, xlsx assíncrono, job→disco e2e; ReportExporterTest 6: anti-regressão verde com o anti-fachada reapontado para `json`).
- openspout em uso: **v4.32.0** (`composer.lock`). NÃO foi rodado composer/npm (dependência já instalada em 15-01).
- Acceptance da Task 1/2: `XlsxExporter implements ReportFormatExporter` ✓, `openToFile` ✓, `Row::fromValues` ✓, sem `WriterEntityFactory` ✓, `'xlsx' => app(XlsxExporter::class)` no `ReportExporter` ✓.

## Next Phase Readiness

- Três formatos do contrato (CSV/XLSX/PDF) prontos para as telas/relatórios (15-06) e para o download assinado (15-09).
- Dívida registrada: unificar os dois resolvers de driver (síncrono/assíncrono) num único ponto evita repetir a habilitação por formato.

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-15*
