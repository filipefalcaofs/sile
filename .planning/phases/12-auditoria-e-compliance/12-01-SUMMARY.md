---
phase: 12-auditoria-e-compliance
plan: 01
subsystem: database
tags: [activity-log, auditoria, lgpd, indices, postgres, sqlite, spatie-activitylog]

# Dependency graph
requires:
  - phase: 01-identidade-acesso-e-auditoria-transversal
    provides: "AuditService::log + activity_log (RN-002) com result/rules_version via forceFill"
provides:
  - "Índices de consulta na espinha activity_log: created_at; composto (log_name, created_at); event"
  - "Coluna aditiva personal_data (boolean nullable, indexada) marcando acesso a dado pessoal (LGPD HU-102)"
  - "AuditService::log(..., bool $personalData = false) gravando personal_data via forceFill (aditivo, backward-compatible)"
affects: [12-04 AuditTrailQueryService, 12-06 LGPD monitor, 12-07 marcação real de PII nos call sites, 12-11 verificação integral]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Migration ADITIVA driver-agnóstica (sem ->after(); no down() índices caem antes da coluna)"
    - "Extensão ADITIVA de serviço por parâmetro final com default (compatibilidade total dos call sites)"

key-files:
  created:
    - "database/migrations/2026_06_15_080934_add_audit_query_indexes_and_personal_data_to_activity_log.php"
    - "tests/Feature/Auditoria/AuditServicePersonalDataTest.php"
  modified:
    - "app/Support/Audit/AuditService.php"

key-decisions:
  - "personal_data nasce nullable/false — nada é PII por engano (anti-fachada LGPD); a marcação real fica para 12-07"
  - "Sem ->after() na migration (específico de MySQL; banco de teste é SQLite) — posição da coluna é irrelevante"
  - "Índice composto (log_name, created_at) coexiste com o índice single de log_name já existente (não há duplicação)"

patterns-established:
  - "Migration aditiva reversível: índices removidos antes da coluna no down() para drop seguro em todos os drivers"
  - "personalData é o ÚLTIMO parâmetro de AuditService::log (nomeado), preservando toda a assinatura posicional usada hoje"

# Metrics
duration: ~11min
completed: 2026-06-15
---

# Phase 12 Plan 01: Fundação de leitura e LGPD sobre activity_log Summary

**Índices de consulta (created_at; (log_name, created_at); event) + coluna aditiva personal_data indexada em activity_log, e AuditService::log gravando personal_data de forma aditiva — sem regressão nos call sites das Fases 1–11.**

## Performance

- **Duration:** ~11 min
- **Started:** 2026-06-15T08:08:00Z
- **Completed:** 2026-06-15T08:19:31Z
- **Tasks:** 2
- **Files modified:** 3 (1 migration, 1 service, 1 teste)

## Accomplishments
- Migration ADITIVA na espinha `activity_log`: índices `created_at`, composto `(log_name, created_at)` e `event` (consulta da trilha em escala — HU-100) + coluna `personal_data` boolean nullable INDEXADA (marca LGPD — HU-102). Sem tocar colunas/registros existentes; reversível no `down()`.
- `AuditService::log` ganhou o parâmetro final `bool $personalData = false`, gravado no `forceFill` junto de `result`/`rules_version`. Backward-compatible: todos os call sites atuais seguem funcionando sem alteração.
- `AuditServicePersonalDataTest` (TDD RED→GREEN): `personalData: true` marca; default NÃO marca PII; `result`/`rules_version` preservados.

## Assinatura final do AuditService::log

```php
public function log(
    string $logName,
    string $event,
    string $description,
    array $properties = [],
    ?Model $subject = null,
    string $result = 'sucesso',
    ?string $rulesVersion = null,
    bool $personalData = false,   // <- ADITIVO (último parâmetro)
): Activity
```

`forceFill(['result' => $result, 'rules_version' => $rulesVersion, 'personal_data' => $personalData])->save()`.

## Índices criados em activity_log

| Índice | Colunas | Uso (HU-100/102) |
|---|---|---|
| `activity_log_created_at_index` | `created_at` | ordenação/janela por período |
| `activity_log_log_name_created_at_index` | `(log_name, created_at)` | filtro por fonte + ordenação |
| `activity_log_event_index` | `event` | filtro por ação |
| `activity_log_personal_data_index` | `personal_data` | recorte LGPD |

Preservados (criação original): `log_name` (single), `result`, `rules_version`, morphs `subject`/`causer`.

## Task Commits

1. **Task 1: Migration aditiva (índices + personal_data)** - `f5bccd3` (feat)
2. **Task 2: AuditService::log com personalData + teste** - `b1f7f1f` (feat, TDD)

## Files Created/Modified
- `database/migrations/2026_06_15_080934_add_audit_query_indexes_and_personal_data_to_activity_log.php` - índices de consulta + coluna `personal_data`, reversível
- `app/Support/Audit/AuditService.php` - parâmetro aditivo `personalData` gravado via `forceFill`
- `tests/Feature/Auditoria/AuditServicePersonalDataTest.php` - cobertura do parâmetro (true marca / default não marca / preserva result+rules_version)

## Decisions Made
- `personal_data` nullable com default `false` no `AuditService` — nada é marcado como dado pessoal por engano (anti-fachada LGPD). A marcação nos call sites reais de leitura sensível é escopo do 12-07.
- Migration sem `->after()` (driver-agnóstica; banco de teste é SQLite) e com `down()` removendo índices antes da coluna (drop seguro em qualquer driver).
- Índice composto `(log_name, created_at)` não duplica o índice single de `log_name` já existente.

## Deviations from Plan

None - plan executed exactly as written. Escopo respeitado: apenas a infra (índices + coluna + parâmetro), sem marcar PII em call sites e sem tocar 12-02/12-03.

## Issues Encountered
- Ao rodar a suíte SQLite completa em processo único, 5 testes de `Tests\Feature\Auditoria\AbuseAlertModelTest` falharam (`App\Models\AbuseAlert` not found). Causa-raiz: são arquivos UNTRACKED do plano **12-03** (Wave 1 paralela) em andamento no working tree — não pertencem a este plano e não têm relação com a migration nem com o `AuditService`. Evidência de isolamento abaixo.

## Evidência de verificação (output real)
- `php artisan migrate --no-interaction` + ciclo `migrate:rollback`/`migrate` → DONE no Postgres de dev; índices e coluna confirmados via `pg_indexes`/`information_schema`.
- `php artisan test --compact --filter=AuditService` → **3 passed, 4 assertions**.
- `php artisan test --compact --group postgis` → **29 passed, 184 assertions** (inclui `ImportGeoLayerTest` auditado, com `personal_data` aplicada no `sile_testing` via migrate:fresh).
- `php artisan test --compact --exclude-group postgis --filter='/^(?!.*AbuseAlert).*$/'` → **1208 passed, 6158 assertions** (todos os consumidores reais do `AuditService` das Fases 1–11 + os 3 testes novos; as únicas falhas da suíte completa eram do 12-03 paralelo).
- `vendor/bin/pint` nos arquivos do plano → passed. Sem lints.

## Next Phase Readiness
- **12-04** (AuditTrailQueryService): índices `created_at`/`(log_name, created_at)`/`event` prontos para a paginação por período/fonte/ação direto na espinha.
- **12-06/12-07** (LGPD): coluna `personal_data` + parâmetro `AuditService::log(..., personalData:)` prontos para marcar e medir acessos a PII nos call sites reais.
- **12-11** (verificação integral): atenção ao gotcha de processo único do `RefreshDatabaseState::$migrated` — rodar a suíte como `composer test` (2 processos: `--exclude-group postgis` e depois `--group postgis`) para que o `sile_testing` seja migrado corretamente.

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*
