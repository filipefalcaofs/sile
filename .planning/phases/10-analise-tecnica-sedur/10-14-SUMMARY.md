---
phase: 10-analise-tecnica-sedur
plan: 14
subsystem: backend
tags: [hu-082, hu-144, consulta, fila, sla, semaforo, server-driven, whereLike, csv, busca-global, indices, inertia]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso/09-11
    provides: "padrão server-driven (ResultadoExpressoController): filtros/paginação/whereLike caseSensitive:false/auditoria da consulta"
  - phase: 10-analise-tecnica-sedur/10-01
    provides: "reuso da permissão consultar-solicitacoes para a consulta (sem permissão nova) + permissão distribuir-processos (visão do gestor)"
  - phase: 10-analise-tecnica-sedur/10-02
    provides: "colunas indexadas sector_id/assigned_user_id/analysis_category/in_fine_mesh/analysis_due_at + is_virtual_office (categoria) + status do processo"
  - phase: 10-analise-tecnica-sedur/10-05
    provides: "AnalysisSlaService::statusFor (semáforo on-the-fly) + SlaStatus.label() para a fila/badge"
provides:
  - "Consulta de processos HU-082 server-driven: ProcessoController@index (filtros completos do SAPS + analista + categoria derivada + paginação + CSV simples)"
  - "Fila do analista HU-144: ProcessoController@fila (meus/setor + semáforo + contadores + visão agregada do gestor)"
  - "Busca global Cmd+K HU-082 RN-009: ProcessoBuscaController (endpoint leve JSON)"
  - "ProcessoQueryService (filtros/derivação de categoria/fila/contadores/visão do setor) + ProcessoResource (3 identificadores RN-007 + SLA resumido)"
  - "4 rotas gestao.processos.{index,fila,busca,show} gated por consultar-solicitacoes, auditadas"
  - "migration aditiva de índices de consulta (property_registration, address_neighborhood, external_reference)"
affects: [10-15-acoes-do-processo, 10-16-ui-fila-consulta-detalhe-cmdk]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Consulta server-driven espelhando ResultadoExpressoController: filtros via ->when, whereLike caseSensitive:false (case-insensitive pgsql/sqlite), paginação ->through(Resource), auditoria da consulta"
    - "Categoria de processo DERIVADA (não persistida): combina analysis_category + in_fine_mesh + is_virtual_office — derivação única em ProcessoQueryService::categoriasDe (reusada pelo Resource e pelo filtro)"
    - "Semáforo do SLA calculado no Resource via AnalysisSlaService (on-the-fly, nunca persistido); fila e consulta compartilham o mesmo ProcessoResource"
    - "Rotas estáticas (fila, busca) registradas ANTES do wildcard {viabilityRequest} para não serem capturadas pelo route model binding"
    - "CSV simples via response()->streamDownload + chunk (não materializa tudo em memória)"

key-files:
  created:
    - database/migrations/2026_06_14_235144_add_consulta_indexes_to_viability_requests_table.php
    - app/Services/Analise/ProcessoQueryService.php
    - app/Http/Controllers/Gestao/ProcessoController.php
    - app/Http/Controllers/Gestao/ProcessoBuscaController.php
    - app/Http/Resources/ProcessoResource.php
    - tests/Feature/Analise/ProcessoConsultaTest.php
    - tests/Feature/Analise/ProcessoFilaTest.php
    - tests/Feature/Analise/ProcessoBuscaGlobalTest.php
  modified:
    - routes/gestao.php

key-decisions:
  - "Reuso de consultar-solicitacoes para consulta/fila/busca (decisão de 10-01); sem permissão nova"
  - "Categoria derivada pode ser MÚLTIPLA (ex.: malha fina E expresso) — Resource expõe `categorias` (lista) + `categoria` (primária); filtro por categoria aplica a condição correspondente"
  - "Contadores da fila por status SEM sobreposição: aguardando análise = em_analise sem analista; em análise = em_analise atribuído; em pendência = em_pendencia; vencendo hoje = prazo <= fim do dia"
  - "Filtro por ZONA não exposto: não há coluna de zona persistida/indexável (Quadro 10 oficial pendente SEDUR) — não criar filtro de fachada que não filtra (entrega-funcional)"
  - "CSV simples agora (RN-011); export pleno XLSX/PDF → HU-131/Fase 15"

patterns-established:
  - "ProcessoQueryService como ponto único de construção da query (consulta e fila), reaproveitando escopo meus/setor entre fila e contadores"
  - "Props Inertia inspecionadas por viewData('page') nos testes (a UI/.tsx é 10-16), como o CaixaSetorTest"

# Metrics
duration: ~15min
completed: 2026-06-15
---

# Phase 10 Plan 14: Consulta de Processos (HU-082) e Fila do Analista (HU-144) — Summary

**Consulta de processos server-driven (filtros completos do SAPS + analista + categoria derivada + paginação + índices + CSV simples), fila priorizada por SLA (meus/setor + semáforo on-the-fly + contadores + visão do gestor) e busca global Cmd+K — tudo gated por `consultar-solicitacoes` (reuso) e auditado. `ProcessoConsultaTest` 11/11, `ProcessoFilaTest` 7/7, `ProcessoBuscaGlobalTest` 5/5; suíte completa 1095/1095. Zero dependência nova; ÚNICO editor de `routes/gestao.php` na Wave 6.**

## Endpoints entregues (contrato para 10-15/10-16)

| Rota | Nome | Método | O que faz |
|---|---|---|---|
| `gestao/processos` | `gestao.processos.index` | GET | Consulta filtrável + paginada (HU-082); `?formato=csv` exporta o conjunto filtrado |
| `gestao/processos/fila` | `gestao.processos.fila` | GET | Fila do analista (HU-144); `?modo=meus|setor` (default `meus`) |
| `gestao/processos/busca` | `gestao.processos.busca` | GET | Busca global Cmd+K (HU-082 RN-009); `?q=` → JSON |
| `gestao/processos/{viabilityRequest}` | `gestao.processos.show` | GET | Detalhe do processo (props para a UI 10-16) |

Todas sob `middleware('permission:consultar-solicitacoes')`, prefixo `processos`. As estáticas (`fila`, `busca`) vêm **antes** do wildcard `{viabilityRequest}` (confirmado no `route:list`). Coexistem com o grupo `analisar-processos` `processos/{viabilityRequest}/ficha|precedentes` (10-09/10-06) sem colisão.

## `ProcessoQueryService` (filtros + derivações)

- **`filtered(array $filtros): Builder`** — aplica, só quando informado, os filtros do SAPS + analista + categoria, ordenado por `id` desc:
  - `grupo` (macro de status: `rascunho`/`em_andamento`/`concluido`/`cancelado`), `status` (exato), `protocolo` (whereLike), `bap` (`external_reference`), `produto_tvl` (via `whereHas('decision')`), `servico` (`service_type_id`), `setor` (`sector_id`), `analista` (`assigned_user_id`), `inscricao` (`property_registration`), `cep` (`address_zip`), `logradouro` (`address_street`), `bairro` (`address_neighborhood`), `nome` (empresa `legal_name`/`trade_name` OU requerente `name`), `cnpj` (`company.cnpj`), `data_de`/`data_ate` (range em `protocoled_at`).
  - **categoria** (`categoria`): `malha_fina`→`in_fine_mesh=true`; `sede_escritorio`→`is_virtual_office=true`; `expresso`/`semi_expresso`→`analysis_category`.
  - Todos os `whereLike` usam `caseSensitive: false` (case-insensitive em pgsql e sqlite). Eager-load `company`/`sector`/`assignedTo`/`decision` (sem N+1).
- **`fila(User $user, string $modo): Builder`** — escopo `meus` (`assigned_user_id`) ou `setor` (caixas do usuário, respeita o vínculo RN-005), só `em_analise`/`em_pendencia`, ordenado por `analysis_due_at` asc (índice de 10-02).
- **`contadores(User $user, string $modo): array`** — `aguardando_analise`, `em_analise`, `em_pendencia`, `vencendo_hoje` (não-sobrepostos; mesmo escopo da fila).
- **`visaoSetor(User $user): array`** — `carga` (por analista: `[{analista_id, analista, total}]`) + `vermelhos` (prazo estourado), agregados pelos setores do gestor (CA-03).
- **`categoriasDe(ViabilityRequest): list<{value,label}>`** (static) — derivação única da categoria, reusada pelo Resource.
- **`CATEGORIAS`** (const pública): `expresso`/`semi_expresso`/`malha_fina`/`sede_escritorio` → rótulos pt-BR.

## `ProcessoResource` (shape — insumo direto de 10-16)

`id`, **3 identificadores RN-007** (`protocol_number` = nº do processo SEDUR, `bap` = `external_reference`, `tvl_product_number` via decision), `status`/`status_label`, `empresa`, `cnpj` (formatado), `imovel` (logradouro - bairro), `inscricao`, `categorias` (lista) + `categoria` (primária), `analista`/`assigned_user_id`, `setor`/`sector_id`, `analysis_stage`/`analysis_stage_label`, `analysis_due_at` (ISO), **`sla`** (`{status, status_label, restante}` via AnalysisSlaService — `null` sem prazo/etapa), `protocoled_at`.

A fila reusa o MESMO Resource (o `sla` por item sai pronto); a UI (10-16) lê `processos`, `contadores`, `modo` e `visaoSetor` (apenas para o gestor; `null` para o analista). O `show` adiciona `timeline` (transições mapeadas: from/to + rótulos + `reason` + `em`).

## Decisão CSV-agora / export-pleno-depois

`?formato=csv` no index gera CSV simples (cabeçalho `Processo;BAP;Produto TVL;Empresa;CNPJ;Status;Categoria;Analista;Prazo` + uma linha por processo do conjunto filtrado), em streaming via `chunk`, auditado (`exporta-processos-csv`). A **exportação plena** (XLSX/PDF, layout/colunas configuráveis — RN-011 completo) fica para a **HU-131/Fase 15**, conforme o CONTEXT.

## Mapa CA → teste (todos verdes)

| HU / RN | Teste |
|---|---|
| HU-082 RN-004/005 — filtros SAPS + analista + categoria | `ProcessoConsultaTest` (status/analista/categoria/protocolo/cnpj) |
| HU-082 RN-008 — paginação + índices | `ProcessoConsultaTest::test_paginacao_server_side_expoe_meta` + migration de índices |
| HU-082 RN-009 — busca global | `ProcessoBuscaGlobalTest` (protocolo/cnpj/nome; vazio; 403) |
| HU-082 RN-011 — exportação CSV (pleno → HU-131) | `ProcessoConsultaTest::test_exporta_csv_do_conjunto_filtrado` |
| HU-082 CA-02/CA-04 — auditoria + segurança | `ProcessoConsultaTest` (consulta auditada; 403 auditado) |
| HU-144 CA-01/RN-003 — fila por prazo + semáforo | `ProcessoFilaTest` (ordenação; semáforo verde/vermelho; contadores) |
| HU-144 RN-005/CA-03 — caixa do setor / visão do gestor | `ProcessoFilaTest` (vínculo de setor; visão agregada; analista não vê) |

## Task Commits

1. **Task 1: consulta HU-082 (filtros + paginação + CSV + índices + show)** — `9493223` (feat)
2. **Task 2: fila HU-144 (meus/setor + semáforo + contadores + visão do gestor)** — `7d29949` (feat)
3. **Task 3: busca global Cmd+K** — `bba2577` (feat)

_TDD estrito em cada task: RED confirmado por 404 (rota/método ausente) antes do GREEN._

## Verification (evidência fresca)

- `vendor/bin/pint --dirty --format agent` → **passed** (em cada task).
- `php artisan test --compact --filter="ProcessoConsultaTest|ProcessoFilaTest|ProcessoBuscaGlobalTest"` → **23/23** (141 asserções).
- `php artisan test --compact --exclude-group postgis` → **1095/1095** (5534 asserções) — sem regressão.
- `php artisan route:list --path=processos` → ordem correta: `index`, `busca`, `fila` antes de `{viabilityRequest}`.

## Deviations from Plan

Nenhum desvio no código de produção. Ajuste de teste dentro do ciclo RED→GREEN da Task 2: a expectativa do contador `em_analise` foi corrigida (3→2) para refletir a decisão de **buckets de status não-sobrepostos** (um processo `em_pendencia` atribuído conta em "em pendência", não em "em análise"). A leitura literal "em análise = assigned" do plano foi interpretada como "em_analise atribuído" — coerente com o par "aguardando análise = em_analise sem analista" e evita dupla contagem na UI.

## Gaps conhecidos / pendências (registrados, não simulados)

- **Filtro por ZONA não exposto:** sem coluna de zona persistida/indexável (Quadro 10 oficial pendente SEDUR — CONTEXT). Não foi criado filtro de fachada; entra quando a zona vier oficial.
- **CPF / código do logradouro / status de tramitação:** filtros do SAPS legado sem campo correspondente no modelo atual (CPF do requerente não exposto por ora; busca por pessoa via `nome`). A confirmar com a SEDUR no refino da HU-082.
- **Export pleno (XLSX/PDF):** HU-131/Fase 15 (CSV simples agora).
- **Detalhe visual (mini-mapa Leaflet, abas, timeline visual — RN-006/010):** props prontas; a renderização é 10-16.

## Next Phase Readiness

- **10-16 (UI):** telas de fila/consulta/detalhe + Cmd+K consomem `processos.index/fila/busca/show`, o `ProcessoResource` (inclui `sla`), `contadores`, `modo`, `visaoSetor` e os `*Options`.
- **10-15 (ações do processo):** reusa o mesmo escopo/controller/permissão (`consultar-solicitacoes` para ver; ações sob as permissões próprias da análise).
- ZERO dependência nova; ÚNICO editor de `routes/gestao.php` na Wave 6 (file-disjunto com o 10-13, que rodou em paralelo).

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-15*
