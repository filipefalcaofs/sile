---
phase: 15-relatorios-e-indicadores
plan: 02
subsystem: relatorios-export
tags: [hu-131, export, csv, pdf, queue, notification, report-contract, rn-005, lgpd]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-01)
    provides: parâmetros relatorios.* + bloco config/sile.php relatorios.export/job + deps (openspout/echarts) + índices
  - phase: 02-cadastros-estruturantes
    provides: App\Support\Settings (banco→config→default) + ParameterSeeder
  - phase: 12-auditoria-e-compliance
    provides: App\Support\Audit\AuditService (RN-002/RN-008)
  - phase: 09-fluxo-expresso
    provides: padrão DecidirFluxoExpressoJob (tries/timeout/backoff/failed auditado)
  - phase: 10-analise-tecnica
    provides: padrão TvlPdfService (dompdf + disco não-público) e download por URL assinada (TvlDocumentController)
provides:
  - "ReportFilters (bag serializável + round-trip toArray/fromArray + toProcessoFiltros + acessores tipados)"
  - "Contrato de exportação: ReportDefinition + ReportSource + SyncOnlyReportSource + ReportFormatExporter"
  - "Drivers síncronos CsvExporter + PdfExporter + Blade genérico relatorios.relatorio"
  - "ReportExporter (orquestra streaming síncrono OU GerarExportacaoJob acima do limiar + auditoria + guarda SyncOnly)"
  - "GerarExportacaoJob (export assíncrono resiliente, RN-005 no async, failed() auditado anti-fachada)"
  - "ExportacaoPronta (Notification standalone database+mail) + model/migration/factory export_files"
affects: [15-03-indicadores, 15-04-tempo-produtividade, 15-05-quedas, 15-06-http-telas, 15-08-xlsx, 15-09-dashboard-download, 15-15-retencao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Contrato único de exportação: ReportFilters (entrada) → ReportSource → ReportDefinition → ReportFormatExporter, orquestrado por ReportExporter (RN-009)"
    - "Bag serializável com round-trip como fonte de verdade do filtro no síncrono E no assíncrono (RN-005) — closures nunca serializadas"
    - "Marcador SyncOnlyReportSource para sources com estado de construtor (força síncrono)"
    - "Notification standalone (database+mail) para evento não-processual (NÃO NotificationDispatcher)"
    - "Migration com numeração baixa (000002) para nascer cedo na ordem e não depender de tabelas de outras frentes"

key-files:
  created:
    - app/Services/Relatorios/ReportFilters.php
    - app/Services/Relatorios/Export/ReportDefinition.php
    - app/Services/Relatorios/Export/ReportSource.php
    - app/Services/Relatorios/Export/SyncOnlyReportSource.php
    - app/Services/Relatorios/Export/ReportFormatExporter.php
    - app/Services/Relatorios/Export/CsvExporter.php
    - app/Services/Relatorios/Export/PdfExporter.php
    - app/Services/Relatorios/Export/ReportExporter.php
    - resources/views/relatorios/relatorio.blade.php
    - app/Models/ExportFile.php
    - database/migrations/2026_06_15_000002_create_export_files_table.php
    - database/factories/ExportFileFactory.php
    - app/Jobs/GerarExportacaoJob.php
    - app/Notifications/ExportacaoPronta.php
    - tests/Feature/Relatorios/ReportFiltersTest.php
    - tests/Feature/Relatorios/ExportDriversTest.php
    - tests/Feature/Relatorios/ReportExporterTest.php
    - tests/Feature/Relatorios/GerarExportacaoJobTest.php
    - tests/Feature/Relatorios/Stubs/ProcessoBairroSource.php
    - tests/Feature/Relatorios/Stubs/GrandeSyncOnlySource.php
  modified: []

key-decisions:
  - "Contrato (a): ReportFilters é um BAG serializável (vocabulário completo das telas), não os 7 campos do indicador — round-trip toArray/fromArray garante RN-005 no async e evita dump de PII da trilha (RN-007)"
  - "Contrato (b): marcador SyncOnlyReportSource força o caminho síncrono para sources com estado de construtor (nunca vão ao Job)"
  - "Auditoria event = definition.event + '-' + formato (refinamento do plano literal 'exporta-'.formato): mantém a identidade da tela + o formato; com event default 'exporta' resulta em 'exporta-csv'"
  - "Formato validado contra (formatos_habilitados ∩ drivers disponíveis): xlsx recusado honestamente até 15-08 (anti-fachada), mesmo constando do catálogo"
  - "Notification ExportacaoPronta é STANDALONE (database+mail) — NÃO o NotificationDispatcher (process-bound; exige viabilityRequestId)"
  - "PdfExporter::dados() é público para verificar o conteúdo textual via render do Blade (o dompdf comprime os streams do PDF) — mesmo padrão de TvlPdfService::montarDados"
  - "Migration export_files validada ISOLADA em pgsql via --path (up+down); migrate global NÃO rodado por causa das migrations pendentes da Fase 14 (trabalho paralelo do usuário)"

# Metrics
duration: ~50 min
completed: 2026-06-15
---

# Phase 15 Plan 02: Contrato único de exportação (HU-131) Summary

**O coração da HU-131 entregue como contrato único e testado: `ReportFilters` (bag serializável com round-trip que garante RN-005 no síncrono E no assíncrono) → `ReportSource` → `ReportDefinition` → `ReportFormatExporter` (drivers CSV/PDF), orquestrado por `ReportExporter` (streaming abaixo do limiar OU `GerarExportacaoJob` acima, com guarda `SyncOnly`), auditando toda exportação e gravando `ExportFile` baixável; export falho audita a falha sem fabricar "pronto".**

## Performance

- **Duration:** ~50 min
- **Tasks:** 3 (cada uma com commit atômico)
- **Files created:** 20 (8 contrato/drivers/orquestrador + Blade + model/migration/factory + Job + Notification + 4 testes + 2 stubs)

## Accomplishments

- **Task 1** (commit `2bfdc6e`): `ReportFilters` (bag) + `ReportDefinition`/`ReportSource`/`SyncOnlyReportSource`/`ReportFormatExporter` + model/migration/factory `export_files` + `ReportFiltersTest` (7).
- **Task 2** (commit `d79b8dd`): `CsvExporter` + `PdfExporter` + Blade `relatorios.relatorio` (rodapé "Total de registros: N") + `ExportDriversTest` (4).
- **Task 3** (commit `544fec0`): `ReportExporter` (sync/async + auditoria + guarda SyncOnly) + `GerarExportacaoJob` + `ExportacaoPronta` + `ReportExporterTest` (6) + `GerarExportacaoJobTest` (3) + stubs reconstrutíveis pelo bag.

## API final do contrato (para as próximas waves)

### `App\Services\Relatorios\ReportFilters` (`final readonly class`)
- `__construct(public array $bag = [])` — mapa normalizado chave => valor escalar.
- `static fromArray(array $f): self` — trim nas strings, descarta vazios/nulos, **preserva o vocabulário completo das telas** (o whitelisting é do source).
- `toArray(): array` — round-trip COMPLETO (o Job serializa isto e reconstrói idêntico — RN-005 no assíncrono).
- `get(string $chave, $default = null)`, `only(array $chaves): array`, `aplicados(): array` (auditoria/rodapé do PDF).
- `toProcessoFiltros(): array` — recorta as **17 chaves** do `ProcessoQueryService::filtered` (`grupo/status/protocolo/bap/produto_tvl/servico/setor/analista/inscricao/cep/logradouro/bairro/nome/cnpj/data_de/data_ate/categoria`).
- Acessores tipados (normalizam como o ProcessoQueryService, **sem duplicar SQL**): `from(): ?Carbon` (data_de startOfDay), `to(): ?Carbon` (data_ate endOfDay), `setorId(): ?int`, `analistaId(): ?int`, `bairro(): ?string`, `cnae(): ?string`, `categoria(): ?string` (validada contra `ProcessoQueryService::CATEGORIAS`, fora do whitelist → `null`).
- Const pública `ReportFilters::PROCESSO_CHAVES` (as 17 chaves).

### `App\Services\Relatorios\Export\ReportDefinition` (`final readonly class`)
- `__construct(string $titulo, array $colunas /* list<['key','label']> */, Closure $builder /* (): Builder */, Closure $mapRow /* (mixed): array */, array $filtrosAplicados = [], string $logName = 'relatorios', string $event = 'exporta', bool $personalData = false, string $arquivoBase = 'relatorio')`.
- Métodos: `columnLabels(): list<string>`, `builder(): Builder`, `mapRow(mixed): array`, `fileName(string $ext): string` (slug + carimbo de data/hora).

### `App\Services\Relatorios\Export\ReportSource` (interface)
- `definition(ReportFilters $filtros): ReportDefinition`.
- **INVARIANTE:** reconstrutível SÓ a partir do bag — `app($sourceClass)->definition(ReportFilters::fromArray($bag))`. As Closures builder/mapRow **nunca** são serializadas (só `sourceClass` + `bag` trafegam).

### `App\Services\Relatorios\Export\SyncOnlyReportSource extends ReportSource` (marcador vazio)
- Sources com estado de construtor (ex.: produtividade nominal — 15-06) o implementam; o `ReportExporter` força o síncrono para elas.

### `App\Services\Relatorios\Export\ReportFormatExporter` (interface)
- `stream(ReportDefinition): \Symfony\Component\HttpFoundation\Response` (síncrono).
- `write(ReportDefinition, string $absolutePath): void` (assíncrono).

### `App\Services\Relatorios\Export\ReportExporter`
- `export(ReportSource $source, ReportFilters $filtros, string $formato, ?User $user = null): Response`.
- Valida formato (∩ drivers disponíveis: `['csv','pdf']` hoje); audita SEMPRE (RN-008); abaixo do limiar OU SyncOnly → `driver($formato)->stream($definition)`; acima → `GerarExportacaoJob::dispatch($source::class, $filtros->toArray(), $formato, $user?->id)` + `response()->noContent(202)`.

### Integração de uma tela (RN-009, para 15-06)
```php
return app(ReportExporter::class)->export(
    new MinhaTelaReportSource(),
    ReportFilters::fromArray($request->query()),
    $request->string('formato')->toString(), // csv|pdf
    $request->user(),
);
```

## Decisions Made

- **(a) Bag serializável primário:** `ReportFilters` carrega o vocabulário COMPLETO de filtros (não só os campos do indicador). O round-trip `toArray`/`fromArray` é a fonte de verdade do filtro tanto no streaming síncrono quanto na reconstrução do Job — garante RN-005 nos dois caminhos e evita dump integral da trilha de auditoria com PII (RN-007).
- **(b) Guarda SyncOnly:** sources com estado de construtor (não reconstrutíveis só pelo bag) implementam `SyncOnlyReportSource`; o `ReportExporter` força o síncrono para elas, jamais despachando o Job.
- **Auditoria `event`:** usei `definition.event . '-' . formato` (refinamento do literal `'exporta-'.formato` do plano) — preserva a identidade da tela e o formato; com `event` default `'exporta'` resulta em `'exporta-csv'` (compatível com o plano).
- **Formato sem driver recusado:** validação contra `formatos_habilitados ∩ drivers disponíveis` — `xlsx` (no catálogo) é honestamente recusado até 15-08 (anti-fachada).
- **Notification standalone:** `ExportacaoPronta` usa os canais `database`+`mail` diretamente (NÃO o `NotificationDispatcher`, que é process-bound e exige `viabilityRequestId`).
- **PDF — conteúdo verificável:** `PdfExporter::dados()` é público para renderizar o Blade nos testes (o dompdf comprime os streams do PDF, então o texto não é assertável nos bytes — só a assinatura `%PDF`). Mesmo padrão de `TvlPdfService::montarDados`.

## Pendência para 15-09 (registrada, sem fachada)

- **Rota/endpoint de download:** `ExportacaoPronta` e a futura UI apontam para a rota nomeada **`gestao.relatorios.exportacoes.download`** (URL temporária assinada servindo o `ExportFile` do disco NÃO público, espelhando `TvlDocumentController`). A rota nasce em **15-09**; neste plano os testes usam `Notification::fake()`. Enquanto a rota não existir, o caminho assíncrono não tem chamador em produção (os controllers/telas entram a partir de 15-06).
- **Driver XLSX:** entra em **15-08** (openspout, já instalado no 15-01); basta adicionar `'xlsx'` ao resolver de driver e à lista de drivers disponíveis.

## Deviations from Plan

### Refinamentos (auto-aplicados, dentro do escopo)

**1. [Refinamento] Arquivo de teste extra `ExportDriversTest` (Task 2)**
- O plano deixava a prova de conteúdo dos drivers para o `ReportExporterTest` (Task 3). Para manter o ciclo TDD por task (RED→GREEN), adicionei `tests/Feature/Relatorios/ExportDriversTest.php` exercitando `CsvExporter::stream/write` e `PdfExporter` diretamente. Cobertura complementar (não redundante): testa `write()` num caminho real, que o caminho síncrono do exporter não cobre.

**2. [Refinamento] Stubs de teste reconstrutíveis pelo bag**
- Criei `tests/Feature/Relatorios/Stubs/{ProcessoBairroSource,GrandeSyncOnlySource}.php` (autoload `Tests\` PSR-4) para provar a invariante do contrato (RN-005 sync/async) e a guarda SyncOnly sem acoplar a uma tela real (que só nasce em 15-06).

**3. [Refinamento] `ReportExporterTest` com caso de formato recusado**
- Além dos 5 casos do plano (a–e), adicionei `formato_nao_disponivel_e_recusado` (xlsx) para travar o anti-fachada do limite atual de drivers.

### Coordenação com trabalho paralelo (sem mudança de escopo)

**4. [Coordenação] Migration validada isolada + staging seletivo + pint escopado**
- **Contexto:** o working tree tem trabalho paralelo NÃO commitado do usuário (Fase 14 IA/e-mail: `AiConfiguration`, `EmailServer`, migrations `*_ai_configurations`/`*_email_servers`; + sidebar/vitest/`RolesAndPermissionsSeeder`/`routes/gestao.php`/`bootstrap/providers.php`).
- **Migration:** validei `create_export_files_table` (up+down) ISOLADA em pgsql via `php artisan migrate --path=...` / `migrate:rollback --path=...` — **não** rodei `migrate` global nem `migrate:fresh` (disparariam as migrations pendentes da Fase 14). A evidência funcional em SQLite vem dos testes (RefreshDatabase).
- **Staging:** cada commit recebeu APENAS os arquivos do 15-02 (`git add` por caminho). Nenhum arquivo do usuário foi tocado/staged/commitado (working tree do usuário 100% preservado).
- **Pint:** rodado com caminhos explícitos dos meus arquivos (não `--dirty`, que reformataria os arquivos não commitados do usuário).

**Total deviations:** 3 refinamentos + 1 coordenação. **Impacto no plano:** nenhum (escopo intacto; mais cobertura de teste e preservação do trabalho paralelo).

## Verification (evidência fresca)

- `php artisan test --compact --filter=Relatorios` → **23/23, 112 asserções** (20 dos 4 arquivos do 15-02 — `ReportFiltersTest` 7, `ExportDriversTest` 4, `ReportExporterTest` 6, `GerarExportacaoJobTest` 3 — + 3 testes pré-existentes que referenciam "relatorios").
- `php artisan migrate --path=database/migrations/2026_06_15_000002_create_export_files_table.php` → DONE (up); `migrate:rollback --path=...` → DONE (down). Ambos em pgsql, isolados.
- `vendor/bin/pint` nos arquivos do 15-02 → `passed` (sem pendências).
- Provas anti-fachada: RN-005 no síncrono (linha fora do filtro NÃO aparece no CSV) e no assíncrono (`GerarExportacaoJob::handle` filtrado); guarda SyncOnly (acima do limiar ainda streama, `assertNothingPushed`); PDF começa com `%PDF`; `failed()` audita `result=falha` e NÃO cria `ExportFile`.

## Issues Encountered

- **Trabalho paralelo concorrente:** o usuário tem a Fase 14 (IA/servidores de e-mail) e sidebar/vitest em andamento, não commitados. Resolvido com staging seletivo e validação de migration isolada (ver Deviation 4). Nenhuma colisão de arquivo (frentes em diretórios/arquivos distintos dos meus). A suíte completa (`composer test`) NÃO foi rodada por incluir testes paralelos possivelmente incompletos do usuário (Ai/Email) e migrations pendentes; a evidência do 15-02 é o filtro `Relatorios` (RefreshDatabase, isolado).

## Next Phase Readiness

- Contrato pronto e herdável por qualquer tela (15-03+ serviços de indicador, 15-06 HTTP/telas).
- **15-08:** adicionar driver `XlsxExporter` (openspout) ao resolver + `DRIVERS_DISPONIVEIS`.
- **15-09:** criar a rota `gestao.relatorios.exportacoes.download` (URL assinada + `Storage::download` do disco não-público) — alvo do link da `ExportacaoPronta`.
- **15-15:** pruning de retenção dos `ExportFile` (`relatorios.export.retencao_dias`) no scheduler.

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-15*
