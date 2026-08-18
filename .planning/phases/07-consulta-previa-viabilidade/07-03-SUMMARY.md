---
phase: 07-consulta-previa-viabilidade
plan: 03
subsystem: database
tags: [laravel, eloquent, sqlite, json-casts, immutable-table, viability-history, hu-060]

# Dependency graph
requires:
  - phase: 01-identidade
    provides: "access_logs — padrão de tabela imutável (só created_at, nullOnDelete)"
  - phase: 03-cadastro-empresarial
    provides: "Company::countForUser — escopo por dono (precedente do forUser)"
provides:
  - "Tabela imutável viability_queries (snapshot input + result + rules_versions json + dono)"
  - "Model ViabilityQuery com casts array e scope forUser (histórico só do dono)"
  - "ViabilityQueryFactory com snapshot padrão e estado anonima()"
affects: [07-07, 07-09]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Tabela imutável de snapshot: timestamp('created_at') sem timestamps(); model com $timestamps = false"
    - "Casts json em array (input/result/rules_versions) para snapshot reproduzível e auditável"
    - "scopeForUser espelha Company::countForUser — leitura escopada pelo dono"

key-files:
  created:
    - "database/migrations/2026_06_14_080224_create_viability_queries_table.php"
    - "app/Models/ViabilityQuery.php"
    - "database/factories/ViabilityQueryFactory.php"
    - "tests/Feature/Viabilidade/ViabilityQuerySchemaTest.php"
  modified: []

key-decisions:
  - "$timestamps = false (em vez do const UPDATED_AT = null de access_logs): created_at gravado à mão pela factory/controller — imutabilidade total, sem updated_at"
  - "ip_address via $table->ipAddress() (varchar 45), nullable: origem registrável tanto na consulta anônima quanto autenticada"
  - "resultado desnormalizado (string 30, nullable) para filtro/listagem do histórico sem desserializar o json result"
  - "Verificação migrate:fresh adaptada para SQLite seguro: o comando do plano (--env=testing) seria destrutivo neste ambiente (sem .env.testing; .env aponta pgsql dev)"

# Metrics
duration: 8 min
completed: 2026-06-14
---

# Phase 7 Plan 03: Persistência do Histórico de Consultas (HU-060) Summary

**Tabela imutável `viability_queries` (snapshot json input/result/rules_versions + dono), model `ViabilityQuery` com casts array e `scopeForUser`, e factory com estado anônima — fundação de dados do histórico de consulta prévia, tabular em SQLite.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-06-14T08:02:23Z
- **Completed:** 2026-06-14T08:10:54Z
- **Tasks:** 2
- **Files modified:** 4 (criados)

## Accomplishments

- Migration `viability_queries` imutável e tabular: `user_id` (nullable, nullOnDelete), `entry_type`, `input`/`result`/`rules_versions` como `json`, `resultado` desnormalizado, `ip_address`, e `created_at` SEM `updated_at`. Índice `(user_id, created_at)` para a listagem do histórico.
- Model `ViabilityQuery`: `$timestamps = false` (imutável), casts `array` para os três campos json + `datetime` em `created_at`, relação `user()` e `scopeForUser` (histórico só do dono — precedente "Minhas empresas").
- Factory com snapshot padrão (entry_type endereço, input com cnae/área, result com veredito pendente, rules_versions por motor) e estado `anonima()` (sem dono, com IP).
- Schema test com 4 casos travando casts array, gravação anônima sem dono, escopo `forUser` e a imutabilidade (tem `created_at`, não tem `updated_at`).

## Task Commits

Cada task foi commitada atomicamente:

1. **Task 1: migration + model + factory** — `955a32b` (feat)
2. **Task 2: schema test** — `cceac2b` (test)

**Plan metadata:** este SUMMARY (docs).

_Commits intercalados com executores paralelos da mesma fase (07-02, 07-04) — sem colisão de arquivos; os dois commits de 07-03 estão íntegros._

## Files Created/Modified

- `database/migrations/2026_06_14_080224_create_viability_queries_table.php` — tabela imutável tabular (json), nullOnDelete, só created_at.
- `app/Models/ViabilityQuery.php` — model imutável: casts array, `$timestamps = false`, `user()` e `scopeForUser`.
- `database/factories/ViabilityQueryFactory.php` — snapshot padrão + estado `anonima()`.
- `tests/Feature/Viabilidade/ViabilityQuerySchemaTest.php` — 4 testes (casts, anônima, forUser, imutabilidade).

## Decisions Made

- **Imutabilidade via `$timestamps = false`** (e não `const UPDATED_AT = null` como em `access_logs`): o snapshot grava `created_at` explicitamente (factory hoje; controller no 07-07), garantindo zero `updated_at`. A coluna `created_at` é NOT NULL — quem grava deve fornecer o timestamp.
- **`resultado` desnormalizado** (string 30, nullable): permite filtrar/ordenar o histórico (permitido | permitido_com_condicoes | nao_permitido | pendente) sem desserializar o `result` json — insumo direto da listagem (07-07) e da UI (07-09).
- **`ip_address` via `$table->ipAddress()`** (nullable): origem registrável na consulta anônima e na autenticada; a regra de gravar histórico só quando autenticado é do controller (07-07), não do schema.
- **Escopo `forUser`** espelha `Company::countForUser` — o dono lê apenas as próprias consultas; gravação anônima é aceita pelo schema (user_id null), mas não vira histórico pessoal.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Verificação `migrate:fresh --env=testing` substituída por verificação SQLite segura**

- **Found during:** Task 1 (verify do plano)
- **Issue:** O ambiente não tem `.env.testing`; o `.env` aponta `DB_CONNECTION=pgsql` / `DB_DATABASE=sile` (banco de desenvolvimento). O SQLite só existe no `phpunit.xml` (em memória). Rodar o `php artisan migrate:fresh --env=testing` literal cairia no `.env` e **dropparia o banco de dev** — destrutivo e fora da intenção do plano ("migra em SQLite").
- **Fix:** Verificação equivalente e não destrutiva — `DB_CONNECTION=sqlite DB_DATABASE=/tmp/sile_verify.sqlite php artisan migrate:fresh` (arquivo temporário descartado), confirmando que TODAS as migrations (incluindo `viability_queries`) aplicam em SQLite. A prova canônica do projeto é a suíte em SQLite `:memory:` (`php artisan test`), que roda todas as migrations via RefreshDatabase.
- **Files modified:** Nenhum (apenas o método de verificação).
- **Verification:** `migrate:fresh` em SQLite com todas as 19 tabelas `DONE`; suíte completa `--exclude-group postgis` 616/616 verde.
- **Committed in:** N/A (mudança de procedimento de verificação, não de código).

---

**Total deviations:** 1 auto-fixed (1 blocking — método de verificação).
**Impact on plan:** Sem mudança de escopo nem de código. A intenção ("migra em SQLite, sem quebrar a baseline") foi cumprida de forma segura, sem risco ao banco de desenvolvimento.

## Issues Encountered

- **Execução paralela na mesma fase:** durante o plano, executores concorrentes commitaram 07-02 (contrato PropertyRegistryLookup) e 07-04 (DTOs) — arquivos distintos dos de 07-03. Não houve colisão; os arquivos-alvo de 07-03 não existiam ao iniciar e os dois commits de 07-03 (955a32b, cceac2b) estão íntegros. (Recorrência do alerta do STATE sobre sessões GSD simultâneas.)

## User Setup Required

None - nenhuma configuração de serviço externo é necessária.

## Next Phase Readiness

- Fundação de dados pronta para **07-07** (persistência no controller quando autenticado + listagem `forUser`) e **07-09** (UI do histórico). O controller deve fornecer `created_at` na gravação (imutável; `$timestamps = false`) e usar `resultado` para filtro/listagem.
- Sem bloqueios introduzidos por este plano. A degradação honesta (sem zona → veredito pendente) é propriedade do motor LOUOS (Fase 5), apenas armazenada aqui como snapshot.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*
