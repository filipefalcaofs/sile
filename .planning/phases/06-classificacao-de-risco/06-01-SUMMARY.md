---
phase: 06-classificacao-de-risco
plan: 01
subsystem: database
tags: [versionamento, regras-como-dados, auditoria, quatro-olhos, enum, eloquent, sqlite]

# Dependency graph
requires:
  - phase: 04-georreferenciamento
    provides: "Padrão de versionamento com vigência (GeoLayer/GeoLayerService::openVersion — fecha a anterior sem apagar)"
  - phase: 01-identidade
    provides: "AuditService::log (RN-002) e HasAuditoria; tabela users (FK created_by/published_by)"
provides:
  - "Cabeçalho genérico rule_versions (domain/version/status/vigência/autoria/publicação) com unique(domain,version)"
  - "Enums RuleDomain (sensibilidade por domínio) e RuleVersionStatus (rascunho/vigente/substituida)"
  - "Model RuleVersion com scopes vigente/naData/versao e auditoria automática"
  - "RuleVersionService.openDraft/publish (fecha vigente anterior, quatro olhos, auditoria com rules_version)"
  - "FourEyesViolationException para publicação por publicador distinto do autor"
affects: [06-02-risk-classifications, 06-03-risco-sanitario, 06-06-mantenedores-publicacao, 05-motor-louos, sandbox-HU-143]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Regras como dados versionados: cabeçalho genérico (rule_versions) separado das tabelas tipadas por domínio (FK rule_version_id)"
    - "Publicação por quatro olhos via isSensitive() no enum de domínio (classificação do dado, não decisão hardcoded)"
    - "Rascunho coexiste com a vigente (valid_to nulo nas duas) — scopeVigente filtra status para distinguir"

key-files:
  created:
    - app/Enums/RuleDomain.php
    - app/Enums/RuleVersionStatus.php
    - database/migrations/2026_06_14_013329_create_rule_versions_table.php
    - app/Models/RuleVersion.php
    - database/factories/RuleVersionFactory.php
    - app/Services/Rules/RuleVersionService.php
    - app/Exceptions/FourEyesViolationException.php
    - tests/Unit/Rules/RuleVersionTest.php
    - tests/Unit/Rules/RuleVersionServiceTest.php
  modified: []

key-decisions:
  - "scopeVigente filtra status=vigente além de valid_to nulo (rascunho também tem valid_to nulo — diferença vs GeoLayer)"
  - "Fechamento da vigente anterior filtra status=vigente e exclui o próprio rascunho (whereKeyNot) para não marcar o rascunho promovido como substituído"
  - "isSensitive() true para risco_municipal e risco_sanitario; condicionante false por ora (Fase 5 acrescenta domínios LOUOS sem tocar a 6)"
  - "Verificação SQLite via php artisan test --exclude-group postgis (comando canônico do CI); migrate:fresh --env=testing evitado por apontar ao Postgres de dev"

patterns-established:
  - "Versão de regra é cabeçalho; tabelas de domínio referenciam rule_version_id (planos 06-02/06-03)"
  - "Publicação é operação real auditada (log 'regras', event 'publicacao-versao', rules_version) — sem fachada"

# Metrics
duration: 12min
completed: 2026-06-14
---

# Phase 6 Plan 01: Fundação de Regras Versionadas Summary

**Cabeçalho genérico `rule_versions` (espelha `geo_layers`) + `RuleVersionService` que abre rascunho, publica fechando a vigente anterior sem apagar, aplica quatro olhos em domínio sensível e audita com a versão de regras — a infra que a Fase 6 e a Fase 5 herdam.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-06-14T01:28:00Z
- **Completed:** 2026-06-14T01:40:00Z
- **Tasks:** 3 (todas TDD RED→GREEN→pint)
- **Files created:** 9

## Accomplishments
- Enums `RuleDomain` (com `isSensitive()` para quatro olhos) e `RuleVersionStatus` (com estado `rascunho` que habilita o sandbox HU-143).
- Tabela tabular `rule_versions` (roda em SQLite, sem PostGIS) com `unique(domain,version)`, `created_by`/`published_by` (FK users) e `index(domain,valid_to)`.
- Model `RuleVersion` (HasAuditoria) com scopes `vigente`/`naData`/`versao` espelhando o `GeoLayer`.
- `RuleVersionService` (openDraft idempotente + publish transacional com quatro olhos e auditoria) espelhando `GeoLayerService::openVersion`.
- Suíte SQLite: 459/459 verde (448 baseline + 11 novos), sem regressão.

## Task Commits

Cada task foi commitada atomicamente (TDD):

1. **Task 1: Enums RuleDomain e RuleVersionStatus** - `9649aea` (feat)
2. **Task 2: Migration rule_versions + model RuleVersion + factory + scopes** - `29065f1` (feat)
3. **Task 3: RuleVersionService (openDraft/publish/4-olhos/auditoria)** - `cdde995` (feat)

**Plan metadata:** este SUMMARY (docs).

## Contrato do RuleVersionService (insumo dos planos 06-02..06)

```
App\Services\Rules\RuleVersionService (injeta AuditService)

openDraft(RuleDomain $domain, string $version, string $source, ?int $createdBy = null): RuleVersion
  - Idempotente por (domain, version): se existir, devolve sem duplicar.
  - Cria status Rascunho, valid_from/valid_to null, rules_version = version, created_by.
  - NÃO mexe na vigente (rascunho coexiste — base do sandbox HU-143).

publish(RuleVersion $draft, ?int $publishedBy = null, ?CarbonInterface $validFrom = null): RuleVersion
  - DB::transaction.
  - Quatro olhos: domínio sensível + publishedBy != null + publishedBy == created_by => FourEyesViolationException.
  - Fecha a vigente anterior do mesmo domínio (status=vigente, valid_to nulo, exceto o rascunho): Substituida + valid_to = validFrom (default today()).
  - Promove o rascunho: Vigente, valid_from = validFrom, published_at = now(), published_by.
  - Audita: AuditService->log(logName:'regras', event:'publicacao-versao', rulesVersion: draft->version, properties:[dominio, versao, substituiu[]]).
  - Retorna o rascunho promovido (refresh).
```

### Nomes finais de tabela/colunas (`rule_versions`)
`id`, `domain` (cast RuleDomain), `version`, `status` (cast RuleVersionStatus, default `rascunho`), `valid_from` (date, nullable), `valid_to` (date, nullable), `source` (nullable), `rules_version` (nullable), `published_at` (timestamp, nullable), `created_by` (FK users nullOnDelete), `published_by` (FK users nullOnDelete), `timestamps`. Índices: `unique(domain,version)`, `index(domain,valid_to)`.

### Scopes do RuleVersion
`vigente(domain)` (status=vigente E valid_to nulo), `naData(domain, date)` (valid_from <= date AND (valid_to nulo OR valid_to > date)), `versao(domain, version)`. Relações: `author()` (created_by), `publisher()` (published_by).

## Files Created/Modified
- `app/Enums/RuleDomain.php` - Domínios versionados (risco_municipal/risco_sanitario/condicionante) + `isSensitive()`.
- `app/Enums/RuleVersionStatus.php` - rascunho/vigente/substituida com label pt-BR.
- `database/migrations/2026_06_14_013329_create_rule_versions_table.php` - Cabeçalho tabular genérico.
- `app/Models/RuleVersion.php` - Model com casts, scopes e relações de autoria/publicação.
- `database/factories/RuleVersionFactory.php` - Default vigente + states `rascunho()`/`substituida()`.
- `app/Services/Rules/RuleVersionService.php` - openDraft/publish/quatro olhos/auditoria.
- `app/Exceptions/FourEyesViolationException.php` - Exceção de violação dos quatro olhos.
- `tests/Unit/Rules/RuleVersionTest.php` - Enums, casts e os três scopes (5 testes).
- `tests/Unit/Rules/RuleVersionServiceTest.php` - openDraft/publish/quatro olhos/auditoria (6 testes).

## Decisions Made
- **scopeVigente filtra status=vigente** além de `valid_to` nulo: diferente do GeoLayer, o rascunho também nasce com `valid_to` nulo; sem o filtro de status, `vigente()` confundiria rascunho com vigente. Locked para os consumidores.
- **isSensitive() por domínio** é o gatilho dos quatro olhos (classificação do dado, alimenta o service) — risco municipal e sanitário true; condicionante false. A Fase 5 adiciona domínios LOUOS sem tocar a 6.
- **Auditoria da publicação**: `log_name 'regras'`, `event 'publicacao-versao'`, `rules_version = draft->version`, `properties` com `dominio`/`versao`/`substituiu` (versões fechadas). Mantenedores (06-06) reusam esse contrato.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fechamento da vigente anterior não pode atingir o rascunho promovido**
- **Found during:** Task 3 (RuleVersionService.publish)
- **Issue:** O plano descreve fechar a anterior com `where domain + whereNull('valid_to')`. Como o rascunho que está sendo promovido também tem `valid_to` nulo, esse filtro o marcaria como `substituida` por engano.
- **Fix:** O fechamento filtra `status = vigente` E exclui o próprio rascunho (`whereKeyNot($draft->getKey())`), atingindo apenas a vigente real.
- **Files modified:** app/Services/Rules/RuleVersionService.php
- **Verification:** test_publish_promove_rascunho_e_fecha_vigente_anterior_sem_apagar verde (anterior Substituida com valid_to; nova Vigente; `vigente()->sole()` é a nova).
- **Committed in:** cdde995 (Task 3 commit)

**2. [Rule 3 - Blocking] Verificação SQLite via `--exclude-group postgis` em vez de `migrate:fresh --env=testing`**
- **Found during:** Verificação (Task 2 e fechamento)
- **Issue:** O projeto não tem `.env.testing`; `migrate:fresh --env=testing` rodaria contra o Postgres de desenvolvimento (destrutivo) e não é o driver da suíte. O `php artisan test` sem filtro inclui o grupo `postgis`, que crasha sem o container (comportamento de ambiente pré-existente, alheio à migration tabular).
- **Fix:** A migração foi provada em SQLite pelo `RefreshDatabase` dos testes (migra todo o schema em `:memory:`) e a não-regressão pelo comando canônico do CI `php artisan test --compact --exclude-group postgis` (`.github/workflows/tests.yml`).
- **Files modified:** nenhum (decisão de verificação).
- **Verification:** 459/459 verde com `--exclude-group postgis` (448 baseline + 11 novos).
- **Committed in:** n/a

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking de verificação)
**Impact on plan:** Ambas necessárias para correção e verificação honesta. Sem scope creep — todos os arquivos dentro do `files_modified` do 06-01; nada de risk_classifications/seeders (waves seguintes).

## Issues Encountered
- O `php artisan test --compact` sem filtro abortou em `GeoLayerSeederPostgisTest` (grupo `postgis`, exige o container PostGIS). É comportamento de ambiente pré-existente, não regressão: o grupo roda à parte no CI (`--group postgis`). A rodada SQLite canônica (`--exclude-group postgis`) ficou 459/459 verde.

## User Setup Required
None - nenhuma configuração de serviço externo. Sem dependência nova.

## Next Phase Readiness
- **06-02/06-03 (tabelas tipadas por domínio):** podem adicionar `rule_version_id` (FK para `rule_versions`) e usar `RuleVersionService.openDraft/publish` para versionar a carga das classificações (municipal e sanitária).
- **06-06 (mantenedores/publicação):** reusam `publish` (quatro olhos) e a auditoria `log 'regras'`.
- **Fase 5 (motor LOUOS) e sandbox HU-143:** herdam o cabeçalho genérico e o estado `rascunho` sem retrabalho — basta acrescentar domínios ao enum `RuleDomain`.
- Sem blockers introduzidos por este plano.

---
*Phase: 06-classificacao-de-risco*
*Completed: 2026-06-14*
