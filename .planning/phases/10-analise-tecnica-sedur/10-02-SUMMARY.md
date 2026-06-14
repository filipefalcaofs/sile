---
phase: 10-analise-tecnica-sedur
plan: 02
subsystem: database
tags: [postgresql, sqlite, eloquent, enum, jsonb, factory, versioned-record, audit, sla, fine-mesh, tvl]

# Dependency graph
requires:
  - phase: 08-solicitacao-viabilidade
    provides: "ViabilityRequest (aggregate), padrão de colunas FORA do fillable (protocoled_at), jsonb portável, PostgisTestCase + @group postgis"
  - phase: 09-fluxo-expresso/09-02
    provides: "viability_decisions (FK do tvl_documents), ViabilityDecision/ViabilityDecisionFactory (default deferida), padrão de model imutável e de factory com states"
provides:
  - "8 tabelas novas: sectors + pivot sector_user; analysis_records (ficha versionada); analysis_divergences; analysis_pendencies; fine_mesh_referrals; standard_texts; tvl_documents"
  - "8 colunas de análise em viability_requests (sector_id, assigned_user_id, assigned_at, analysis_category, in_fine_mesh, analysis_stage, analysis_stage_started_at, analysis_due_at) — FORA do fillable, índices nos campos filtrados"
  - "4 enums: AnalysisRecordStatus, AnalysisCategory, AnalysisStage, AnalysisPendencyStatus (todos com label() pt-BR)"
  - "7 models novos + ViabilityRequest/User estendidos (casts/relations); revisão da ficha imutável (unique viability_request_id+revision)"
  - "7 factories com states úteis"
affects: [10-04, 10-05, 10-06, 10-07, 10-08, 10-09, 10-10, 10-11, 10-12, 10-13, 10-14]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Ficha versionada append-only: unique(viability_request_id, revision) + currentAnalysisRecord via latestOfMany('revision')"
    - "Malha fina ORTOGONAL ao status: flag in_fine_mesh (índice) + tabela fine_mesh_referrals (repetível), não um estado"
    - "Colunas de análise FORA do fillable (como protocoled_at/bap_*): escritas por serviços de distribuição/SLA, nunca pelo cidadão"
    - "jsonb portável (SQLite/pgsql), sem geometry própria (precedentes reusam a geometry da Fase 8)"

key-files:
  created:
    - database/migrations/2026_06_14_215040_create_sectors_table.php
    - database/migrations/2026_06_14_215041_create_sector_user_table.php
    - database/migrations/2026_06_14_215042_add_analysis_columns_to_viability_requests_table.php
    - database/migrations/2026_06_14_215043_create_analysis_records_table.php
    - database/migrations/2026_06_14_215044_create_analysis_divergences_table.php
    - database/migrations/2026_06_14_215045_create_analysis_pendencies_table.php
    - database/migrations/2026_06_14_215046_create_fine_mesh_referrals_table.php
    - database/migrations/2026_06_14_215047_create_standard_texts_table.php
    - database/migrations/2026_06_14_215048_create_tvl_documents_table.php
    - app/Enums/AnalysisRecordStatus.php
    - app/Enums/AnalysisCategory.php
    - app/Enums/AnalysisStage.php
    - app/Enums/AnalysisPendencyStatus.php
    - app/Models/Sector.php
    - app/Models/AnalysisRecord.php
    - app/Models/AnalysisDivergence.php
    - app/Models/AnalysisPendency.php
    - app/Models/FineMeshReferral.php
    - app/Models/StandardText.php
    - app/Models/TvlDocument.php
    - database/factories/SectorFactory.php
    - database/factories/AnalysisRecordFactory.php
    - database/factories/AnalysisDivergenceFactory.php
    - database/factories/AnalysisPendencyFactory.php
    - database/factories/FineMeshReferralFactory.php
    - database/factories/StandardTextFactory.php
    - database/factories/TvlDocumentFactory.php
    - tests/Feature/Analise/AnaliseSchemaTest.php
    - tests/Feature/Analise/AnaliseModelsTest.php
  modified:
    - app/Models/ViabilityRequest.php
    - app/Models/User.php

key-decisions:
  - "jsonb (não json) para engine_snapshot/engine_rules_versions/per_cnae/conditions/parking — espelha a Fase 8/9, portável SQLite/pgsql"
  - "Colunas de análise FORA do #[Fillable] do ViabilityRequest (escritas via forceFill por serviços de distribuição/SLA)"
  - "currentAnalysisRecord = hasOne()->latestOfMany('revision') (maior revisão; a finalizada é imutável, recalcular gera nova revisão)"
  - "Ordem determinística das migrations por timestamp sequencial (215040..215048) — garante FK válida no pgsql (analysis_records antes de analysis_divergences; sectors antes do pivot/alter)"

patterns-established:
  - "Revisão única por processo: unique(viability_request_id, revision)"
  - "verification_code único por documento TVL (a mesma decisão pode ter N emissões/reimpressões)"

# Metrics
duration: ~22min
completed: 2026-06-14
---

# Phase 10 Plan 02: Fundação de Dados e Domínio da Análise Técnica

**8 tabelas novas (setores+pivot, ficha versionada, divergências, pendências, malha fina, textos-padrão, documentos TVL) + 8 colunas de análise em `viability_requests` (atribuição/SLA/categoria/malha fina, FORA do fillable), 4 enums, 7 models novos + `ViabilityRequest`/`User` estendidos e 7 factories — tudo jsonb portável (SQLite/pgsql), sem geometry própria. TDD estrito: `AnaliseSchemaTest` 9/9 + `AnaliseModelsTest` 11/11.**

## Performance

- **Duration:** ~22 min
- **Tasks:** 3 (cada uma com commit atômico)
- **Files:** 31 (29 criados, 2 modificados)

## Accomplishments
- Schema completo da análise técnica, portável e indexado nos campos filtrados pela consulta HU-082.
- Ficha de análise versionada com revisão IMUTÁVEL (unique `viability_request_id`+`revision`) e helper `currentAnalysisRecord` (maior revisão).
- Malha fina ORTOGONAL ao status (flag `in_fine_mesh` + tabela `fine_mesh_referrals`).
- `tvl_documents` 1:N para a `viability_decisions` da Fase 9 (cada emissão/reimpressão auditada, `verification_code` único).
- Verificação fresca: `AnaliseSchemaTest|AnaliseModelsTest` 20/20; `@group postgis` 22/22 (schema portável validado no PostgreSQL real).

## Contrato para os planos seguintes (nomes EXATOS)

### Tabelas (jsonb portável, sem geometry)
- **`sectors`**: `id`, `name` (string **unique**), `active` (boolean default true), timestamps.
- **`sector_user`** (pivot N:N): `id`, `sector_id` (FK→sectors cascadeOnDelete), `user_id` (FK→users cascadeOnDelete), **unique(sector_id, user_id)**, timestamps.
- **`viability_requests`** GANHOU (aditivo, FORA do fillable): `sector_id` (FK→sectors nullOnDelete, nullable, **índice**), `assigned_user_id` (FK→users nullOnDelete, nullable, **índice**), `assigned_at` (timestamp nullable), `analysis_category` (string nullable, **índice**), `in_fine_mesh` (boolean default false, **índice**), `analysis_stage` (string nullable), `analysis_stage_started_at` (timestamp nullable), `analysis_due_at` (timestamp nullable, **índice** — base do SLA/fila).
- **`analysis_records`**: `id`, `viability_request_id` (FK→viability_requests cascadeOnDelete, **índice**), `revision` (unsignedInteger), `status` (string default `'rascunho'`), `analyst_user_id` (FK→users nullOnDelete, nullable), `engine_snapshot` (jsonb nullable), `engine_rules_versions` (jsonb nullable), `engine_available` (boolean default true), `per_cnae` (jsonb nullable), `conditions` (jsonb nullable), `parking` (jsonb nullable), `parecer` (text nullable), `finalized_at` (timestamp nullable), timestamps. **unique(viability_request_id, revision)**.
- **`analysis_divergences`**: `id`, `analysis_record_id` (FK→analysis_records cascadeOnDelete, **índice**), `cnae` (string), `field` (string), `suggested_value` (text nullable), `final_value` (text nullable), `justification` (text nullable), timestamps.
- **`analysis_pendencies`**: `id`, `viability_request_id` (FK→viability_requests cascadeOnDelete, **índice**), `requested_by_user_id` (FK→users nullOnDelete, nullable), `description` (text), `status` (string default `'aberta'`, **índice**), `due_at` (timestamp nullable), `responded_at` (timestamp nullable), `response` (text nullable), timestamps.
- **`fine_mesh_referrals`**: `id`, `viability_request_id` (FK→viability_requests cascadeOnDelete, **índice**), `referred_by_user_id` (FK→users nullOnDelete, nullable), `reason` (text), `resolved_at` (timestamp nullable), timestamps (**created_at = momento do encaminhamento**).
- **`standard_texts`**: `id`, `category` (string, **índice**), `content` (text), `active` (boolean default true), `version` (unsignedInteger default 1), timestamps.
- **`tvl_documents`**: `id`, `viability_decision_id` (FK→viability_decisions cascadeOnDelete, **índice**), `disk` (string), `path` (string), `verification_code` (string **unique**), `generated_by_user_id` (FK→users nullOnDelete, nullable), `generated_at` (timestamp), timestamps.

### Enums (string, namespace `App\Enums`)
- **`AnalysisRecordStatus`**: `Rascunho='rascunho'`, `Finalizada='finalizada'`. Métodos: `label()` (`'Rascunho'`/`'Finalizada'`), `isFinalizada(): bool`.
- **`AnalysisCategory`**: `Expresso='expresso'`, `SemiExpresso='semi_expresso'`. `label()` (`'Expresso'`/`'Semi-expresso'`). _A categoria de consulta da HU-082 combina este enum + `in_fine_mesh` + `is_virtual_office` — derivada lá, não persistida._
- **`AnalysisStage`**: `Distribuicao='distribuicao'`, `Analise='analise'`. `label()` (`'Distribuição'`/`'Análise'`). _Alinha com os parâmetros `analise.sla.<etapa>_dias`._
- **`AnalysisPendencyStatus`**: `Aberta='aberta'`, `Respondida='respondida'`, `Expirada='expirada'`. `label()`.

### Models (namespace `App\Models`)
- **`Sector`** — `#[Fillable(['name','active'])]`; cast `active`→bool; `analysts(): BelongsToMany(User, 'sector_user')->withTimestamps()`; `requests(): HasMany(ViabilityRequest)`.
- **`AnalysisRecord`** — fillable: `viability_request_id, revision, status, analyst_user_id, engine_snapshot, engine_rules_versions, engine_available, per_cnae, conditions, parking, parecer, finalized_at`; casts: `status`→`AnalysisRecordStatus`, `engine_snapshot`/`engine_rules_versions`/`per_cnae`/`conditions`/`parking`→`array`, `engine_available`→bool, `finalized_at`→datetime; relations `viabilityRequest(): BelongsTo`, `analyst(): BelongsTo(User,'analyst_user_id')`, `divergences(): HasMany`; helper `isFinalizada(): bool`.
- **`AnalysisDivergence`** — fillable: `analysis_record_id, cnae, field, suggested_value, final_value, justification`; `analysisRecord(): BelongsTo`.
- **`AnalysisPendency`** — fillable: `viability_request_id, requested_by_user_id, description, status, due_at, responded_at, response`; casts: `status`→`AnalysisPendencyStatus`, `due_at`/`responded_at`→datetime; relations `viabilityRequest(): BelongsTo`, `requestedBy(): BelongsTo(User,'requested_by_user_id')`.
- **`FineMeshReferral`** — fillable: `viability_request_id, referred_by_user_id, reason, resolved_at`; cast `resolved_at`→datetime; helper `isResolved(): bool`; relations `viabilityRequest(): BelongsTo`, `referredBy(): BelongsTo(User,'referred_by_user_id')`.
- **`StandardText`** — fillable: `category, content, active, version`; casts `active`→bool, `version`→int.
- **`TvlDocument`** — fillable: `viability_decision_id, disk, path, verification_code, generated_by_user_id, generated_at`; cast `generated_at`→datetime; relations `viabilityDecision(): BelongsTo`, `generatedBy(): BelongsTo(User,'generated_by_user_id')`.

### `App\Models\ViabilityRequest` (estendido)
- **casts adicionados**: `analysis_category`→`AnalysisCategory`, `analysis_stage`→`AnalysisStage`, `in_fine_mesh`→bool, `assigned_at`/`analysis_stage_started_at`/`analysis_due_at`→datetime.
- **relações adicionadas**: `sector(): BelongsTo`, `assignedTo(): BelongsTo(User,'assigned_user_id')`, `analysisRecords(): HasMany(AnalysisRecord)`, `currentAnalysisRecord(): HasOne(AnalysisRecord)->latestOfMany('revision')`, `pendencies(): HasMany(AnalysisPendency)`, `fineMeshReferrals(): HasMany(FineMeshReferral)`.
- **As 8 colunas de análise NÃO estão no `#[Fillable]`** — gravar via `forceFill([...])->save()` nos serviços de distribuição (HU-080/081) e SLA (HU-144).

### `App\Models\User` (estendido)
- `sectors(): BelongsToMany(Sector, 'sector_user')->withTimestamps()` (vínculo do analista, HU-138 RN-005).

### Factories (namespace `Database\Factories`)
- **`SectorFactory`** — default `name 'Setor {word}'` (único), `active true`; states `ativo()`, `inativo()`.
- **`AnalysisRecordFactory`** — default RASCUNHO rev 1 (`engine_available true`, `engine_snapshot`/`engine_rules_versions`/`per_cnae`/`parking` arrays plausíveis, `conditions []`, `parecer null`, `finalized_at null`, `viability_request_id = ViabilityRequest::factory()->protocoled()`); states `finalizada()` (status Finalizada, `analyst_user_id`=User::factory(), `parecer` preenchido, `finalized_at now()`) e `semMotor()` (FA-01: `engine_available false`, `engine_snapshot`/`engine_rules_versions`/`per_cnae` null).
- **`AnalysisDivergenceFactory`** — `analysis_record_id = AnalysisRecord::factory()`; `cnae`/`field`/`suggested_value`/`final_value`/`justification` plausíveis.
- **`AnalysisPendencyFactory`** — default ABERTA (`status Aberta`, `due_at now()+15d`, `requested_by_user_id`=User::factory(), `viability_request_id`=protocoled()); states `respondida()` (Respondida, `responded_at now()`, `response`) e `expirada()` (Expirada, `due_at` no passado).
- **`FineMeshReferralFactory`** — default `reason` preenchido, `resolved_at null`; state `resolvido()` (`resolved_at now()`).
- **`StandardTextFactory`** — default `category` ∈ {deferimento, indeferimento, condicionante, pendencia}, `active true`, `version 1`; state `inativo()`.
- **`TvlDocumentFactory`** — `viability_decision_id = ViabilityDecision::factory()` (deferida), `disk 'local'`, `path 'tvl/{uuid}.pdf'`, `verification_code` único, `generated_by_user_id`=User::factory(), `generated_at now()`.

> ATENÇÃO (armadilha de teste herdada da Fase 8/9): `ViabilityRequestFactory::protocoled()` usa `protocol_number` HARDCODED (`VIA-{ano}-000001`) — NÃO é único. Ao criar VÁRIOS registros que cada um geraria sua própria solicitação protocolada num mesmo teste (ex.: várias `AnalysisRecord`/`AnalysisPendency`/`FineMeshReferral` por factory default, ou criar uma 2ª `ViabilityDecision`), COMPARTILHE uma única `ViabilityRequest`/`ViabilityDecision` e passe o `*_id` explícito — senão estoura `unique` em `protocol_number`. (Não alterei a factory: fora do escopo deste plano.)

## Task Commits

1. **Task 1: migrations (8 tabelas + colunas) + AnaliseSchemaTest** — `82ef5cd` (feat)
2. **Task 2: enums (4) + models (7) + ViabilityRequest/User estendidos** — `3d70339` (feat)
3. **Task 3: factories (7) + AnaliseModelsTest** — `95a3a00` (test)

_Cada peça seguiu RED→GREEN com evidência fresca._

## Decisions Made
- **jsonb** (não `json`) para os campos estruturados, espelhando Fase 8/9 — portável e provado em SQLite e pgsql (@group postgis).
- **Colunas de análise fora do fillable** — escrita controlada por serviços (distribuição/SLA), consistente com `protocoled_at`/`bap_*`.
- **`currentAnalysisRecord` via `latestOfMany('revision')`** — a ficha vigente é a de MAIOR revisão (a finalizada é imutável; recalcular gera nova revisão).
- **Timestamps sequenciais determinísticos (215040..215048)** — a ordem alfabética padrão do `make:migration` colocaria `analysis_divergences` antes de `analysis_records` (FK quebraria no pgsql); renomeei para garantir `sectors`→pivot/alter e `analysis_records`→`analysis_divergences`.

## Deviations from Plan

### Auto-fixed Issues
Nenhum desvio no código de produção — plano executado como escrito.

**Bug de teste próprio (Task 3, ciclo GREEN):** o teste de unicidade do `verification_code` criava uma 2ª `ViabilityDecision` (que gera uma 2ª `ViabilityRequest` protocolada com `protocol_number` repetido → `QueryException` pelo motivo errado). Corrigido reusando a decisão do 1º documento (`viability_decision_id` explícito). Resolvido dentro do mesmo ciclo RED→GREEN; `AnaliseModelsTest` 11/11.

## Issues Encountered (cross-plan — FORA do escopo do 10-02)
- **`ManageRolesTest::test_administrador_lista_perfis_com_permissoes_e_vinculos` FALHA** (suíte completa SQLite: 962/963): espera `->has('permissions', 19)` mas o seeder agora tem **24**. Causa: commit **`1ceb70c` (plano 10-01)** "adiciona 5 permissoes aditivas" alterou `database/seeders/RolesAndPermissionsSeeder.php` (19→24) e atualizou só o `RolesAndPermissionsSeederTest`, deixando o `ManageRolesTest` desatualizado. **Não é regressão deste plano** (nenhum arquivo de permissão/role/seeder foi tocado pelo 10-02; o commit das permissões precede os commits do 10-02). É território do 10-01 ("não tocar seeder-tests do 10-01") — **pendência para o 10-01/orquestrador**: atualizar a contagem de 19 para 24 em `tests/Feature/Roles/ManageRolesTest.php` (linha 44).

## Verification (evidência fresca)
- `vendor/bin/pint --dirty --format agent` → passed.
- `php artisan test --compact --filter="AnaliseSchemaTest|AnaliseModelsTest"` → **20/20** (69 asserções).
- `php artisan test --compact --group postgis` → **22/22** (131 asserções; schema portável no PostgreSQL real).
- `php artisan test --compact --exclude-group postgis` → **962/963** (a única falha é o `ManageRolesTest` do 10-01, acima).
- `migrate:fresh` em SQLite descartável → as 9 migrations aplicam limpo na ordem de dependência.

## Next Phase Readiness
- **10-04** (CRUD de Setores): usa `Sector` + `sector_user` + `User::sectors()`.
- **10-05** (SLA): grava `analysis_due_at`/`analysis_stage`/`analysis_stage_started_at` via forceFill; fila ordena por `analysis_due_at` (indexado).
- **10-06** (ficha pré-analisada): preenche `engine_snapshot`/`engine_rules_versions`/`per_cnae` da rev 1; degradação via `semMotor`/`engine_available=false`.
- **10-07** (distribuição/assumir): grava `sector_id`/`assigned_user_id`/`assigned_at`.
- **10-08** (pré-análise): cria `AnalysisRecord` rev 1 (rascunho).
- **10-09** (ficha/divergências): edita revisão (rascunho), registra `AnalysisDivergence` na finalização.
- **10-10** (decisão técnica): lê `currentAnalysisRecord` para montar a `ViabilityDecision` (flow `analise_tecnica`).
- **10-11** (pendências): `AnalysisPendency` + `pendencies()`.
- **10-12** (malha fina): `FineMeshReferral` + flag `in_fine_mesh`.
- **10-13** (TVL PDF): `TvlDocument` (1:N `viability_decisions`, `verification_code` único).
- **10-14** (consulta HU-082): filtra por `sector_id`/`assigned_user_id`/`analysis_category`/`in_fine_mesh`/`analysis_due_at` (índices prontos).
- ZERO dependência nova; sem geometry própria.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
