---
phase: 08-solicitacao-de-viabilidade
plan: 01
subsystem: domain
tags: [solicitacao-viabilidade, hu-061, hu-067, hu-068, hu-070, driver-aware, postgis, geometry, state-machine, protocolo, factories, eloquent, rn-002, auditoria, anti-fachada]

# Dependency graph
requires:
  - phase: 04-georreferenciamento
    plan: "(geo_features)"
    provides: "padrão de geometria driver-aware (geometry só pgsql + GiST condicional; fonte GeoJSON jsonb) + GeoJsonLayerImporter (ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON,4326)))"
  - phase: 03-cadastro-empresarial
    plan: "01/06"
    provides: "padrão de aggregate + pivot (companies, company_cnae withPivot is_primary, restrictOnDelete) + Cnae model/factory"
  - phase: 02-administracao-base
    plan: "02"
    provides: "App\\Support\\Settings::get (banco→cache→config/sile.php) + Parameter (typedValue) — lido pelo ProtocolNumberGenerator"
  - phase: 01-identidade
    plan: "02"
    provides: "App\\Concerns\\HasAuditoria + App\\Support\\Audit\\AuditService::log(logName,event,description,properties,subject,result,rulesVersion) (RN-002)"
provides:
  - "8 tabelas: viability_requests (aggregate + imóvel 1:1) + satélites (viability_request_cnaes/_documents/_transitions) + cadastros (viability_service_types/document_requirements + pivot cnae_document_requirement) + protocol_sequences"
  - "geometria property_polygon (geometry(Polygon,4326)) DERIVADA nullable + índice GiST só no pgsql; fonte de verdade portável é property_polygon_geojson (jsonb)"
  - "Enums ViabilityRequestStatus (3 ativos + 5 ganchos) com label()/publicLabel() e ViabilityRequestOrigin (portal/contingencia + gancho regin)"
  - "6 models com casts/relations (ViabilityRequest com HasAuditoria + markSimulationStale) + 6 factories com states draft/protocoled/cancelled/contingency"
  - "App\\Services\\Solicitacao\\ProtocolNumberGenerator::generate(?int $year): string — {prefixo}-{AAAA}-{NNNNNN} concorrência-seguro (lockForUpdate)"
  - "App\\Services\\Solicitacao\\ViabilityRequestStateMachine::transition(...) com mapa explícito + timeline + auditoria + InvalidStatusTransitionException (CA-03)"
affects: [08-03-criar-rascunho, 08-05-cnaes, 08-06-imovel-geometry, 08-07-area, 08-08-anexos, 08-09-simulacao, 08-10-protocolo, 08-11-consulta-protocolo, 08-12-cancelar, 08-13-evento-dominio, 08-14-contingencia, 08-15-atendimento-presencial, 08-16-fechamento]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Geometria derivada driver-aware: coluna geometry + GiST adicionados via DB::statement só quando DB::getDriverName()==='pgsql' (em SQLite a coluna NEM EXISTE; fonte = jsonb) — espelha geo_features/GeoJsonLayerImporter (Fase 4)"
    - "Máquina de estados com mapa EXPLÍCITO (const TRANSITIONS) só das transições ativas; ganchos do enum NÃO entram no mapa — as fases seguintes só ADICIONAM entradas"
    - "Número sequencial concorrência-seguro: protocol_sequences->lockForUpdate() em DB::transaction (FOR UPDATE real no pgsql, no-op em SQLite; unique como defesa final)"
    - "Parametrização HU-014 com default inline (Settings::get(chave, config('sile.{chave}', literal))) — serviço independe do banco/seeder do 08-02"

key-files:
  created:
    - database/migrations/2026_06_14_104826_create_viability_service_types_table.php
    - database/migrations/2026_06_14_104827_create_document_requirements_table.php
    - database/migrations/2026_06_14_104828_create_cnae_document_requirement_table.php
    - database/migrations/2026_06_14_104829_create_protocol_sequences_table.php
    - database/migrations/2026_06_14_104830_create_viability_requests_table.php
    - database/migrations/2026_06_14_104832_create_viability_request_cnaes_table.php
    - database/migrations/2026_06_14_104833_create_viability_request_documents_table.php
    - database/migrations/2026_06_14_104834_create_viability_request_transitions_table.php
    - app/Enums/ViabilityRequestStatus.php
    - app/Enums/ViabilityRequestOrigin.php
    - app/Models/ViabilityRequest.php
    - app/Models/ViabilityServiceType.php
    - app/Models/DocumentRequirement.php
    - app/Models/ViabilityRequestDocument.php
    - app/Models/ViabilityRequestTransition.php
    - app/Models/ProtocolSequence.php
    - database/factories/ViabilityRequestFactory.php
    - database/factories/ViabilityServiceTypeFactory.php
    - database/factories/DocumentRequirementFactory.php
    - database/factories/ViabilityRequestDocumentFactory.php
    - database/factories/ViabilityRequestTransitionFactory.php
    - database/factories/ProtocolSequenceFactory.php
    - app/Services/Solicitacao/ProtocolNumberGenerator.php
    - app/Services/Solicitacao/ViabilityRequestStateMachine.php
    - app/Services/Solicitacao/InvalidStatusTransitionException.php
    - tests/Feature/Solicitacao/SolicitacaoSchemaTest.php
    - tests/Feature/Solicitacao/ProtocolNumberGeneratorTest.php
    - tests/Feature/Solicitacao/ProtocolNumberGeneratorPostgisTest.php
    - tests/Feature/Solicitacao/ViabilityRequestStateMachineTest.php
  modified: []

key-decisions:
  - "property_polygon (geometry) NÃO existe em SQLite (driver-aware): a coluna e o GiST são adicionados por DB::statement só no pgsql; a suíte prova a ausência (test_property_polygon_geometry_nao_existe_em_sqlite). A fonte é property_polygon_geojson (jsonb); a derivada é gravada via ST_* no 08-06."
  - "protocol_number e status FORA do #[Fillable] do ViabilityRequest: o número vem do ProtocolNumberGenerator e o status só muda pela StateMachine (que audita). HasAuditoria (logFillable) não registra status — sem auditoria dupla com a transição explícita."
  - "Ganchos (aguardando_bap/em_analise/deferida/indeferida/em_pendencia) existem como CASOS do enum mas NÃO no mapa de transições — provado por test_ganchos_nao_sao_transicionaveis (CA-03). Regin é caso do enum Origin, não usado nesta fase."
  - "ProtocolNumberGenerator lê solicitacao.protocolo.prefixo/.padding via Settings::get com DEFAULT INLINE VIA/6 — não depende do seeder do 08-02 (que rodou em paralelo e adicionou o fallback em config/sile.php; integra sem conflito)."
  - "transitions() é hasMany()->latest() (timeline mais recente primeiro, HU-069); cnaes() belongsToMany withPivot('is_primary') espelhando company_cnae; viability_request_cnaes.cnae_id é restrictOnDelete."

patterns-established:
  - "Migração de aggregate com geometria derivada: Schema::create com a coluna jsonb fonte + bloco if(pgsql){ALTER TABLE ADD COLUMN geometry; CREATE INDEX ... USING GIST}"
  - "StateMachine não abre transação própria — o caller (08-10/08-12) controla a transação; a máquina só valida, atualiza status, grava timeline e audita"

# Metrics
duration: ~18 min
completed: 2026-06-14
---

# Phase 8 Plan 01: Fundação do Domínio da Solicitação de Viabilidade Summary

**A Fase 8 ganhou seu esqueleto de dados e domínio (irmão paralelo do 08-02, zero sobreposição de arquivos): 8 tabelas driver-aware — o aggregate `viability_requests` (com o imóvel embutido 1:1) + satélites `viability_request_cnaes`/`_documents`/`_transitions` + cadastros `viability_service_types`/`document_requirements` (+ pivot `cnae_document_requirement`) + `protocol_sequences`. A geometria `property_polygon` (geometry(Polygon,4326)) é DERIVADA nullable com índice GiST só no Postgres (em SQLite nem existe — a fonte portável é `property_polygon_geojson` jsonb), espelhando `geo_features`/`GeoJsonLayerImporter` da Fase 4. Os enums `ViabilityRequestStatus` (3 ativos + 5 ganchos com `label()`/`publicLabel()`) e `ViabilityRequestOrigin` (portal/contingencia + gancho regin), 6 models (com `HasAuditoria` + `markSimulationStale` no aggregate) e 6 factories (states draft/protocoled/cancelled/contingency) sustentam toda a fase. O `ProtocolNumberGenerator` gera `{prefixo}-{AAAA}-{NNNNNN}` sob `lockForUpdate` (concorrência real provada em `@group postgis`); a `ViabilityRequestStateMachine` tem mapa explícito só dos estados ativos, lança `InvalidStatusTransitionException` na transição inválida (CA-03) e grava timeline + auditoria (RN-002) em cada transição. ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca): 25 testes SQLite + 1 `@group postgis`; suíte completa 700/700.**

## Performance

- **Duration:** ~18 min
- **Started:** 2026-06-14T07:51:12Z (1º commit)
- **Completed:** 2026-06-14T08:09:14Z (último commit)
- **Tasks:** 4 (migrations; enums+models+factories; ProtocolNumberGenerator; ViabilityRequestStateMachine)
- **Files created:** 30 (8 migrations + 2 enums + 6 models + 6 factories + 3 serviços + 4 testes + este SUMMARY) — ZERO dependência nova

## Accomplishments

- **8 tabelas** migradas e provadas em SQLite via RefreshDatabase (sem geometry/GiST quebrando — Pitfall 1 respeitado).
- **Geometria driver-aware**: `property_polygon` (geometry) + GiST adicionados via `DB::statement` só no `pgsql`; em SQLite a coluna não existe (fonte = `property_polygon_geojson` jsonb).
- **2 enums** com casos ativos + ganchos e rótulos técnico/público (HU-069 RN-004).
- **6 models** com casts/relations; `ViabilityRequest` com `HasAuditoria` (RN-002) e `markSimulationStale()` (escrita única — HU-063 RN-005).
- **6 factories** com states `draft`/`protocoled`/`cancelled`/`contingency` + helpers `withPrimaryCnae`/`withCnaes` — base de todos os testes da fase.
- **`ProtocolNumberGenerator`** concorrência-seguro e parametrizado (default inline VIA/6); unicidade lógica em SQLite, concorrência real (FOR UPDATE) em `@group postgis`.
- **`ViabilityRequestStateMachine`** com mapa explícito (só ativos), exceção na transição inválida (CA-03) e timeline + auditoria em toda transição.

## As 8 tabelas (nomes exatos — insumo dos planos seguintes)

| # | Tabela | Papel | Colunas-chave |
|---|--------|-------|---------------|
| 1 | `viability_service_types` | cadastro (HU-061 RN-005) | `code` (unique), `name`, `flow_hint`, `active` |
| 2 | `document_requirements` | cadastro "Requisito" (HU-067) | `code` (unique), `name`, `description`, `required`, `active`, `validation_instructions` |
| 3 | `cnae_document_requirement` | pivot N:N | `cnae_id` (cascade), `document_requirement_id` (cascade), `unique(cnae_id, document_requirement_id)` |
| 4 | `protocol_sequences` | contador por ano | `year` (unique), `last_number` (default 0) |
| 5 | `viability_requests` | aggregate root + imóvel 1:1 | ver lista abaixo |
| 6 | `viability_request_cnaes` | pivot atividades | `viability_request_id` (cascade), `cnae_id` (restrict), `is_primary`, `unique(viability_request_id, cnae_id)` |
| 7 | `viability_request_documents` | anexos (HU-066) | `viability_request_id` (cascade), `requirement_id` (nullOnDelete), `disk`, `path`, `original_name`, `mime_type`, `size`, `sha256`, `uploaded_by_user_id` |
| 8 | `viability_request_transitions` | timeline (HU-069) | `viability_request_id` (cascade), `from_status` (nullable), `to_status`, `reason`, `public_label`, `actor_user_id` (nullOnDelete) |

### Colunas de `viability_requests`

- **Identidade/estado:** `protocol_number` (string nullable, **unique**), `status` (default `'rascunho'`, **index**), `origin` (default `'portal'`).
- **FKs:** `service_type_id` (→viability_service_types, nullOnDelete), `company_id` (→companies, nullOnDelete), `requester_user_id` (→users, beneficiário), `created_by_user_id` (→users, ator real), `cancelled_by_user_id` (→users, nullable nullOnDelete).
- **Imóvel/área:** `used_area_m2` (decimal 10,2), `property_registration`, `address_street`, `address_number`, `address_complement` (texto livre — HU-139 adiado), `address_neighborhood`, `address_zip`, `address_reference` — todos nullable (rascunho nasce sem imóvel).
- **Polígono:** `property_polygon_geojson` (**jsonb, FONTE**) + `property_polygon` (`geometry(Polygon,4326)` **derivada, nullable, SÓ pgsql** + GiST `viability_requests_property_polygon_gist`).
- **Indicadores:** `is_virtual_office`, `is_public_area`, `has_independent_access` (bool default false).
- **Simulação (HU-141):** `simulation_snapshot` (jsonb), `simulation_rules_versions` (jsonb), `simulation_resultado`, `simulated_at`.
- **Contingência/decisão:** `applicant_proceeded_despite` (bool default false), `contingency_reason`, `external_reference` (BAP/Regin futuro), `protocoled_at`, `cancelled_at`, `cancelled_reason`.

## Enums (casos exatos)

- **`ViabilityRequestStatus`** (string): ATIVOS `Rascunho='rascunho'`, `Protocolada='protocolada'`, `Cancelada='cancelada'`; GANCHOS `AguardandoBap='aguardando_bap'`, `EmAnalise='em_analise'`, `Deferida='deferida'`, `Indeferida='indeferida'`, `EmPendencia='em_pendencia'`. `label()` técnico + `publicLabel()` ao cidadão (ex.: Protocolada → "Recebida — em processamento"; AguardandoBap → "Aguardando confirmação da Junta Comercial — nada a fazer por enquanto").
- **`ViabilityRequestOrigin`** (string): `Portal='portal'`, `Contingencia='contingencia'`, `Regin='regin'` (gancho Fase 13). `label()` pt-BR.

## Models, factories e assinaturas (contrato dos planos seguintes)

- **`ViabilityRequest`** (HasAuditoria): casts `status`/`origin` (enums), `property_polygon_geojson`/`simulation_snapshot`/`simulation_rules_versions` (array), `used_area_m2` (decimal:2), `is_*`/`applicant_proceeded_despite` (bool), `simulated_at`/`protocoled_at`/`cancelled_at` (datetime). Relations: `serviceType()`, `company()`, `requester()`, `createdBy()`, `cancelledBy()` (belongsTo); `cnaes()` (belongsToMany `viability_request_cnaes` withPivot `is_primary`), `primaryCnae()` (wherePivot is_primary), `documents()` (hasMany), `transitions()` (hasMany **->latest()**). Domínio: **`markSimulationStale(): void`** (zera snapshot/rules_versions/resultado/simulated_at).
- **`ViabilityServiceType`**: fillable code/name/flow_hint/active; cast active bool; **`scopeActive()`**.
- **`DocumentRequirement`**: fillable code/name/description/required/active/validation_instructions; `cnaes()` belongsToMany `cnae_document_requirement`.
- **`ViabilityRequestDocument`**: fillable requirement_id/disk/path/original_name/mime_type/size/sha256/uploaded_by_user_id; `request()`, `requirement()`, `uploader()`.
- **`ViabilityRequestTransition`**: fillable from_status/to_status/reason/public_label/actor_user_id; casts from/to (enum); `request()`, `actor()`.
- **`ProtocolSequence`**: fillable year/last_number.
- **`ViabilityRequestFactory`**: `definition()`=rascunho/portal (requester+created_by via User::factory, company via Company::factory, polígono de 4 pontos em Salvador). States: `draft()`, `protocoled()` (`protocol_number='VIA-{ano}-000001'`), `cancelled()`, `contingency()`. Helpers `withPrimaryCnae(?Cnae)`, `withCnaes(int $n)`. Demais factories simples (+ `required()`/`optional()` em DocumentRequirementFactory; `inactive()` em ViabilityServiceTypeFactory).
- **`ProtocolNumberGenerator::generate(?int $year = null): string`** — `$year ??= now()->year`; `DB::transaction` com `ProtocolSequence->where(year)->lockForUpdate()->first() ?? create(...)` + `increment('last_number')`; formata com `Settings::get('solicitacao.protocolo.prefixo', config(...,'VIA'))` e `.padding` (6). NÃO grava em viability_requests.
- **`ViabilityRequestStateMachine`** (injeta `AuditService`): `canTransition(from,to): bool`; `transition(ViabilityRequest, ViabilityRequestStatus $to, ?User $actor=null, ?string $reason=null, ?string $publicLabel=null): ViabilityRequestTransition` — valida (senão `InvalidStatusTransitionException::para(...)`), atualiza status, grava transition e `AuditService::log('solicitacoes','transicao',...)`. NÃO abre transação própria. Mapa: `rascunho⇒[protocolada,cancelada]`, `protocolada⇒[cancelada]`.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: migrations driver-aware + SolicitacaoSchemaTest** — `ad88011` (feat) — RED: 5 falhas (tabelas inexistentes) → GREEN: 5/5.
2. **Task 2: enums + 6 models + 6 factories** — `c8face4` (feat) — RED: 11 erros (classes não encontradas) → GREEN: 16/16 (79 asserções).
3. **Task 3: ProtocolNumberGenerator + testes** — `c241369` (feat) — RED: 4 erros → GREEN: 4/4 SQLite + 1/1 `@group postgis`.
4. **Task 4: ViabilityRequestStateMachine + exceção** — `b620abd` (feat) — RED: 5 erros → GREEN: 5/5 (19 asserções).

**Plan metadata:** `docs(08-01)` (este SUMMARY + STATE).

## Decisions Made

- **Geometria derivada só pgsql**: a coluna `property_polygon` e o GiST são adicionados por `DB::statement` fora do `Schema::create`, guardados por `DB::getDriverName()==='pgsql'`. Em SQLite a coluna não existe — a suíte prova a ausência. Fonte de verdade = `property_polygon_geojson`.
- **`protocol_number`/`status` fora do fillable**: governados por serviço/máquina; `HasAuditoria` (logFillable) não os registra → sem auditoria dupla (a transição já audita explicitamente).
- **Ganchos não transicionáveis**: presentes no enum (timeline/listeners futuros) mas ausentes do mapa — `test_ganchos_nao_sao_transicionaveis` trava a regressão.
- **Default inline no gerador**: `Settings::get(chave, config('sile.solicitacao.protocolo.*', 'VIA'/6))` — independe do banco e do seeder do 08-02.

## Deviations from Plan

None — plano executado como escrito. (Nota cosmética: `vendor/bin/pint` normalizou o nome do método de teste `markSimulationStale` para snake_case via `php_unit_method_casing`; a chamada ao método de produção `markSimulationStale()` permanece em camelCase.)

## Issues Encountered

- **08-02 executando em paralelo na MESMA working dir:** o plano 08-02 (parâmetros/permissões) commitou intercalado (`a98f80a`/`fe6d42d`/`9d353ec`) antes do meu Task 2. **Boundary respeitado**: não toquei `ParameterSeeder`/`config/sile.php`/`RolesAndPermissionsSeeder` (escopo do 08-02); staging sempre individual (nunca `git add -A`). A integração é limpa — o 08-02 adicionou `solicitacao.protocolo.prefixo='VIA'`/`.padding=6` em `config/sile.php`, exatamente o fallback que o `ProtocolNumberGenerator` consome (e o default inline já protegia se ausente).
- **Suíte completa inclui `@group postgis` inline** (container `sile-pgsql` de pé na porta 5433): os testes espaciais rodaram contra Postgres real e passaram junto com os de SQLite (700/700).

## Verification (evidência fresca)

- **RED Task 1:** `--filter=SolicitacaoSchemaTest` → 5 falhas (tabelas inexistentes). **GREEN:** 5/5 (21 asserções).
- **RED Task 2:** 11 erros ("Class not found"). **GREEN:** `--filter=SolicitacaoSchemaTest` → 16/16 (79 asserções).
- **RED Task 3:** 4 erros. **GREEN:** `--filter=ProtocolNumberGeneratorTest` → 4/4; `--group=postgis --filter=ProtocolNumberGeneratorPostgisTest` (POSTGIS_TESTS_REQUIRED=true) → 1/1 (5 asserções, FOR UPDATE real, `pgsql`).
- **RED Task 4:** 5 erros. **GREEN:** `--filter=ViabilityRequestStateMachineTest` → 5/5 (19 asserções).
- **Filtros do plano juntos:** `--filter="SolicitacaoSchemaTest|ProtocolNumberGeneratorTest|ViabilityRequestStateMachineTest"` → **25/25** (103 asserções) + postgis **1/1**.
- **Suíte completa:** `php artisan test --compact` → **700 testes, 700 passaram, 0 falhas** (3581 asserções; inclui as adições do 08-02 e os `@group postgis` inline).
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).
- **`php artisan geo:preparar-banco-de-testes`** → "Banco sile_testing já existe / Extensão postgis garantida".

## Next Phase Readiness

- **Insumo direto de TODA a fase**: 08-03 (criar rascunho — aggregate/factory/origin), 08-05 (CNAEs — pivot is_primary + limite 99 na app), 08-06 (imóvel — gravar `property_polygon` via ST_* no pgsql a partir do jsonb), 08-07 (área — used_area_m2 + markSimulationStale), 08-08 (anexos — viability_request_documents), 08-09 (simulação — simulation_* + markSimulationStale), 08-10 (protocolo — ProtocolNumberGenerator dentro da transação + StateMachine rascunho→protocolada + evento), 08-11 (consulta — transitions()/publicLabel()), 08-12 (cancelar — StateMachine →cancelada), 08-14/08-15 (contingência/atendimento — origin Contingencia + created_by).
- **Geometria reversa pronta**: o gancho espacial (`property_polygon` + GiST) deixa os precedentes do imóvel (HU-142/Fase 10) sem retrabalho.
- **Bloqueios herdados (não introduzidos aqui):** origem `regin` (Fase 13), HU-139 (complemento texto livre), formato oficial do protocolo (parametrizado, default `VIA-AAAA-NNNNNN`) — degradam honesto, sem fachada.

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
