---
phase: 15-relatorios-e-indicadores
plan: 09
subsystem: relatorios-http
tags: [hu-122, hu-123, hu-127, hu-128, hu-129, hu-130, hu-131, hu-137, hu-145, inertia, export, signed-url, audit, rn-002, rn-008, lgpd]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-02)
    provides: "ReportExporter::export(source, filtros, formato, user) + ReportFilters (bag) + ExportFile + Notification ExportacaoPronta (aponta para gestao.relatorios.exportacoes.download)"
  - phase: 15-relatorios-e-indicadores (15-03)
    provides: "IndicadoresViabilidadeService (porPeriodo/porZona/porCnae/porRisco/taxaDeferimento/taxaIndeferimento) + SolicitacoesReportSource"
  - phase: 15-relatorios-e-indicadores (15-05)
    provides: "TempoAnaliseService (tempoPorEtapa/tempoEmissaoTvl) + TempoAnaliseReportSource + EscritorioVirtualReportSource"
  - phase: 15-relatorios-e-indicadores (15-06)
    provides: "ProdutividadeAnalistaService::porAnalista(filtros, nominal, scope) + ProdutividadeReportSource (SyncOnly)"
  - phase: 15-relatorios-e-indicadores (15-07)
    provides: "ExpressoQuedaService (taxaRespostaExpressa/serieTemporal/rankingMotivos) + ExpressoQuedaReportSource"
  - phase: 15-relatorios-e-indicadores (15-04)
    provides: "Holiday model (HasAuditoria, fillable date/name/recurring_annually/active) + HolidayFactory"
  - phase: 15-relatorios-e-indicadores (15-08)
    provides: "XlsxExporter (terceiro formato do contrato — ?formato=xlsx já habilitado)"
  - phase: 10-analise-tecnica
    provides: "TvlDocumentController (padrão de download por URL temporária assinada de disco NÃO público)"
  - phase: 12-auditoria-e-compliance
    provides: "AuditService (RN-002/008) + auditoria do 403 no ponto único (bootstrap/app.php)"
provides:
  - "RelatorioController (Inertia indicadores/tempo/produtividade/quedas com dado REAL + branch ?formato= delegando ao ReportExporter — RN-004/009)"
  - "RelatorioFiltersRequest (valida o vocabulário comum e gera o ReportFilters bag — RN-005)"
  - "ExportacaoController::download — URL TEMPORÁRIA ASSINADA servindo o ExportFile do disco NÃO público por streaming (alvo do link da ExportacaoPronta)"
  - "HolidayController CRUD (HU-137) auditado, sem destroy (RN-004) + HolidayRequest (data única normalizada)"
  - "Rotas gestao.relatorios.* (indicadores/tempo/produtividade/quedas/exportacoes.download) e gestao.feriados.* (index/store/update/ativacao.update)"
affects: [15-13-telas-relatorios, 15-14-telas-relatorios, 15-15-retencao-exportacoes]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Controller de relatório FINO: serve a tela Inertia com dado real OU, quando vem ?formato=, delega ao ReportExporter (RN-009) — um único método por relatório serve tela + export"
    - "Um endpoint pode servir mais de um ReportSource via ?relatorio= (tempo: default tempo-por-etapa, escritorio-virtual → sedes)"
    - "Download de exportação por URL TEMPORÁRIA ASSINADA (middleware signed) de disco NÃO público por streaming, espelhando o TvlDocumentController (HU-132) — nunca URL pública"
    - "CRUD de cadastro auditado via HasAuditoria do model (created/updated) quando o model já o tem — controller fino, sem auditoria explícita redundante"

key-files:
  created:
    - app/Http/Controllers/Gestao/RelatorioController.php
    - app/Http/Requests/Gestao/RelatorioFiltersRequest.php
    - app/Http/Controllers/Gestao/ExportacaoController.php
    - app/Http/Controllers/Gestao/HolidayController.php
    - app/Http/Requests/Gestao/HolidayRequest.php
    - tests/Feature/Relatorios/RelatorioControllerTest.php
    - tests/Feature/Relatorios/ExportacaoDownloadTest.php
    - tests/Feature/Relatorios/HolidayControllerTest.php
  modified:
    - routes/gestao.php

key-decisions:
  - "Feriados (HU-137) reusam a permissão manter-parametros (como setores/textos-padrão) — sem 6ª permissão; pendência SEDUR se a coordenação exigir permissão própria"
  - "HolidayController fino: a auditoria CRUD (RN-002) vem do HasAuditoria do model (created/updated) — sem AuditService explícito (evita auditoria dupla, pois o Holiday de 15-04 já loga)"
  - "O endpoint de tempo serve dois ReportSource pelo ?relatorio=: default tempo-por-etapa (TempoAnaliseReportSource), escritorio-virtual → EscritorioVirtualReportSource"
  - "Produtividade nominal resolvida pela permissão relatorios.produtividade.nominal no controller (RN-007); sem ela, escopo = próprio usuário + dados anonimizados (default conservador)"
  - "Download assinado recusa disco public (404) mesmo com assinatura válida (defesa em profundidade LGPD) e devolve 404 honesto para arquivo podado por retenção (15-15) — anti-fachada"

# Metrics
duration: ~10 min
completed: 2026-06-16
---

# Phase 15 Plan 09: Camada HTTP dos relatórios (HU-122..131/145 + HU-137) Summary

**`RelatorioController` serve indicadores/tempo/produtividade/quedas como páginas Inertia com dado REAL dos serviços route-free e dá a cada tela o export transversal por `?formato=csv|xlsx|pdf` delegando ao `ReportExporter`; `ExportacaoController` baixa o arquivo assíncrono por URL temporária assinada de disco NÃO público (alvo do link da `ExportacaoPronta`); e `HolidayController` entrega o CRUD auditado de feriados (HU-137) — tudo gated por permissão e auditado, com `routes/gestao.php` como dono único da fase.**

## Performance

- **Duration:** ~10 min (finalização/verificação; código entregue em 3 commits atômicos)
- **Completed:** 2026-06-16
- **Tasks:** 3 (cada uma com commit atômico)
- **Files created:** 8 (2 controllers + 1 request de relatório + 1 controller/1 request de feriados + 3 testes); 1 modificado (routes/gestao.php)

## Accomplishments

- **Relatórios (HU-122..131/145):** os quatro endpoints servem props reais dos serviços de 15-03/05/06/07 e exportam pelo contrato único de 15-02/08 via `?formato=`, auditando a consulta (RN-002) e a exportação (RN-008).
- **Produtividade gated (HU-130/RN-007):** `relatorios.produtividade.nominal` libera o modo nominal; sem ela o controller anonimiza e escopa ao próprio analista — no streaming e na tela.
- **Download assinado (HU-131):** `gestao.relatorios.exportacoes.download` com middleware `signed` serve o `ExportFile` do disco NÃO público por streaming — fecha a pendência aberta em 15-02 (alvo do link da `ExportacaoPronta`).
- **Feriados (HU-137):** CRUD administrável auditado (sem destroy — RN-004), reusando `manter-parametros`.

## Task Commits

1. **Task 1: RelatorioController (Inertia + ?formato=) + RelatorioFiltersRequest + rotas relatorios.*** — `94c6dc6` (feat)
2. **Task 2: ExportacaoController — download assinado do ExportFile (disco não-público)** — `9b1ddd3` (feat)
3. **Task 3: HolidayController CRUD (HU-137) + rotas feriados.* + auditoria** — `e2123e1` (feat)

**Plan metadata:** este SUMMARY + STATE.md (docs: complete plan)

## Rotas criadas (nomes finais)

Grupo `gestao.relatorios.*` (gated por `consultar-relatorios`):

| Método | Caminho | Nome | Observação |
|---|---|---|---|
| GET | `gestao/relatorios/exportacoes/{exportFile}/download` | `gestao.relatorios.exportacoes.download` | middleware `signed`; rota estática ANTES dos relatórios |
| GET | `gestao/relatorios/indicadores` | `gestao.relatorios.indicadores` | tela + export (`?formato=`) |
| GET | `gestao/relatorios/tempo` | `gestao.relatorios.tempo` | tela + export; `?relatorio=` escolhe o source |
| GET | `gestao/relatorios/produtividade` | `gestao.relatorios.produtividade` | tela + export; nominal gated |
| GET | `gestao/relatorios/quedas` | `gestao.relatorios.quedas` | tela + export |

Grupo `gestao.feriados.*` (gated por `manter-parametros`):

| Método | Caminho | Nome |
|---|---|---|
| GET | `gestao/feriados` | `gestao.feriados.index` |
| POST | `gestao/feriados` | `gestao.feriados.store` |
| PUT | `gestao/feriados/{holiday}` | `gestao.feriados.update` |
| PUT | `gestao/feriados/{holiday}/ativacao` | `gestao.feriados.ativacao.update` |

## Endpoints do controller e contratos

- **`RelatorioController::indicadores`** → `Inertia::render('gestao/relatorios/indicadores', [porPeriodo, porZona, porCnae, porRisco, taxaDeferimento, taxaIndeferimento, filtros])`; export via `SolicitacoesReportSource`.
- **`RelatorioController::tempo`** → `gestao/relatorios/tempo` [tempoPorEtapa, tempoEmissaoTvl, filtros]; export via `TempoAnaliseReportSource` (default) OU `EscritorioVirtualReportSource` (`?relatorio=escritorio-virtual`).
- **`RelatorioController::produtividade`** → `gestao/relatorios/produtividade` [produtividade, nominal, filtros]; export via `new ProdutividadeReportSource($nominal, $escopo)`.
- **`RelatorioController::quedas`** → `gestao/relatorios/quedas` [taxa, serie, ranking, filtros]; export via `ExpressoQuedaReportSource`.
- **`ExportacaoController::download(Request, ExportFile)`** → `Storage::disk($exportFile->disk)->download(...)` com guarda anti-`public` (404), gate de dono/`consultar-relatorios` (403) e 404 honesto se o arquivo foi podado.
- **`HolidayController`** → `index` (`gestao/feriados/index`, paginação server-side), `store`, `update`, `toggleActivation` (sem destroy).

### Componentes Inertia que 15-13/15-14 devem criar

- `gestao/relatorios/indicadores`
- `gestao/relatorios/tempo`
- `gestao/relatorios/produtividade`
- `gestao/relatorios/quedas`
- `gestao/feriados/index`

### Mapeamento `?relatorio=` (endpoint que serve mais de um ReportSource)

- `gestao.relatorios.tempo`:
  - default (sem `?relatorio=` ou qualquer outro valor) → `TempoAnaliseReportSource` (detalhamento por etapa)
  - `?relatorio=escritorio-virtual` → `EscritorioVirtualReportSource` (sedes de escritório virtual)

## Como ficaram o `?formato=` e o download assinado

- **`?formato=`** (transversal a TODA tela — RN-004/009): cada método resolve o formato normalizado (`csv|xlsx|pdf`); se houver formato, NÃO renderiza a tela — delega a `app(ReportExporter::class)->export($source, $filtros, $formato, $user)`. O `ReportExporter` (15-02) valida o formato contra os drivers disponíveis (`csv/xlsx/pdf` após 15-08), audita SEMPRE (RN-008), e escolhe o caminho síncrono (streaming) ou assíncrono (`GerarExportacaoJob` + `ExportacaoPronta`). Sem formato, o método audita a consulta (RN-002) e renderiza o Inertia com os números reais.
- **Download assinado:** a rota `gestao.relatorios.exportacoes.download` tem middleware `signed`; o link nasce de `URL::temporarySignedRoute(...)` (consumido pela `ExportacaoPronta` de 15-02). O `ExportacaoController` espelha o `TvlDocumentController` (HU-132): streaming do disco NÃO público, nunca URL pública. Camadas de defesa: assinatura inválida/ausente → 403 (middleware); disco `public` → 404; arquivo inexistente (retenção 15-15) → 404 honesto; download auditado (`event=baixa-exportacao`, `personalData=true`).

## Decisions Made

- **Feriados reusam `manter-parametros`** (como setores/textos-padrão) — sem 6ª permissão. Decisão registrada; pendência SEDUR caso a coordenação queira permissão própria.
- **Auditoria do CRUD de feriados via `HasAuditoria` do model** (eventos `created`/`updated`) em vez de `AuditService` explícito: o `Holiday` de 15-04 já tem o trait, então auditar explicitamente no controller duplicaria a trilha. O controller fica fino; a prova de auditoria é a linha `activity_log` com `subject_type`/`subject_id`/`event`.
- **`HolidayRequest` normaliza a data** para o formato gravado pelo cast `date` (meia-noite) ANTES da validação, para o `Rule::unique` comparar o MESMO valor armazenado (evita o falso negativo data-só × datetime). O unique ignora o próprio registro no update.
- **Produtividade nominal pela permissão** `relatorios.produtividade.nominal` no controller (RN-007); o mesmo gate define o estado do `ProdutividadeReportSource` no export.

## Deviations from Plan

### Coordenação (continuação + trabalho paralelo — sem mudança de escopo)

**1. [Coordenação] Execução em continuação com staging seletivo e pint escopado**
- **Contexto:** o plano foi executado em 3 commits atômicos de continuação (Tasks 1→2→3 já presentes no HEAD: `94c6dc6`/`9b1ddd3`/`e2123e1`). O working tree mantém o trabalho paralelo NÃO commitado do usuário (Fase 14 IA/e-mail + sidebar/vitest + `RolesAndPermissionsSeeder`/`bootstrap/providers.php`/`package*.json`).
- **Resolução:** cada commit recebeu APENAS os arquivos do 15-09 (controllers, requests, testes) + `routes/gestao.php` (limpo no HEAD, dono único da fase). Nenhum arquivo da Fase 14 do usuário foi tocado/staged/commitado. `pint` rodado com caminhos explícitos dos arquivos do 15-09 (não `--dirty`, que reformataria o trabalho paralelo) → `passed`.

**2. [Refinamento] `RelatorioFiltersRequest` sem `manter-parametros`/`relatorio` nas rules**
- O `?relatorio=` (seleção de source no endpoint de tempo) e o `?formato=` são lidos como query no controller, não validados como filtro de negócio — mantêm o request focado no vocabulário dos indicadores (data/setor/bairro/cnae/categoria/analista). Sem impacto no plano.

**Total deviations:** 1 coordenação + 1 refinamento. **Impacto no plano:** nenhum (escopo intacto; trabalho paralelo do usuário 100% preservado).

## Issues Encountered

- **Trabalho paralelo concorrente na mesma frente:** durante a sessão houve atividade concorrente sobre os mesmos arquivos do 15-09 (criação dos controllers/testes e edição de `routes/gestao.php` em tempo real). Resolvido convergindo para o estado commitado estável (3 commits, todos os testes verdes) sem reescrever o trabalho já correto — apenas verificação fresca + finalização dos artefatos de planejamento (SUMMARY/STATE). Nenhum clobber.

## Next Phase Readiness

- **15-13/15-14 (telas React):** os 5 componentes Inertia listados acima são os contratos a implementar; props e nomes de rota estão fixados. O `?formato=` e o `?relatorio=` já funcionam no backend.
- **15-15 (retenção):** o pruning dos `ExportFile` (`relatorios.export.retencao_dias`) pode remover arquivos com segurança — o download já devolve 404 honesto para arquivo ausente.
- **Pendência SEDUR (degrada honesto):** calendário municipal oficial de feriados de Salvador (o CRUD e o seeder de partida existem; a lista oficial substitui o seed) e layout/colunas oficiais dos relatórios SAPS.

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-16*
