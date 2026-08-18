---
phase: 12-auditoria-e-compliance
plan: 03
subsystem: database
tags: [abuse-alerts, hu-149, hu-014, parametros, permissoes, idempotencia, postgres, sqlite, spatie-permission, has-auditoria]

# Dependency graph
requires:
  - phase: 01-identidade-acesso-e-auditoria-transversal
    provides: "HasAuditoria + activity_log (RN-002) e RolesAndPermissionsSeeder aditivo (HU-013)"
  - phase: 02-parametrizacao
    provides: "Catálogo de parâmetros administráveis (HU-014) com ParameterSeeder + typedValue()"
  - phase: 10-analise-tecnica-sedur
    provides: "fine_mesh_referrals (HU-136) — destino do encaminhamento à malha fina"
provides:
  - "Tabela abuse_alerts (model AbuseAlert + HasAuditoria + factory) — ledger dos alertas HU-149"
  - "Idempotência estrutural: índice ÚNICO PARCIAL (rule_key, fingerprint) WHERE status='aberto' (pgsql+sqlite)"
  - "Enums AbuseSeverity (com peso/ordenação) e AbuseAlertStatus"
  - "7 parâmetros HU-014: ui.auditoria.per_page, features.deteccao_abuso (toggle OFF) + grupo novo abuso.* (5)"
  - "3 permissões aditivas: consultar-auditoria, monitorar-lgpd, gerenciar-alertas-abuso"
  - "Baselines atualizadas: 85 parâmetros, 27 permissões (dono único da Wave 1)"
affects:
  - "12-04 (AuditTrailQueryService): ui.auditoria.per_page + permissão consultar-auditoria"
  - "12-06 (LGPD monitor): permissão monitorar-lgpd"
  - "12-07 (detectores): parâmetros abuso.* + features.deteccao_abuso + abuse_alerts (upsert idempotente)"
  - "12-08 (painel de alertas): permissão gerenciar-alertas-abuso + model AbuseAlert"
  - "12-09 (consumo do ledger e efetividade)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Índice ÚNICO PARCIAL via DB::statement (WHERE status='aberto') — pgsql e sqlite usam a MESMA sintaxe"
    - "Idempotência estrutural no schema (não em código): o banco garante 1 alerta aberto por (regra, fingerprint)"
    - "Enum string-backed com weight()/isAtLeast() para comparar severidade com o limiar parametrizável"
    - "Atribuição de permissão SEMPRE aditiva (givePermissionTo, nunca sync) — preserva ajustes da UI (HU-013)"
    - "Dono único do catálogo/permissões + TODAS as suítes de contagem no MESMO plano (lição Fase 10)"

key-files:
  created:
    - "database/migrations/2026_06_15_081545_create_abuse_alerts_table.php"
    - "app/Enums/AbuseSeverity.php"
    - "app/Enums/AbuseAlertStatus.php"
    - "app/Models/AbuseAlert.php"
    - "database/factories/AbuseAlertFactory.php"
    - "tests/Feature/Auditoria/AbuseAlertModelTest.php"
  modified:
    - "database/seeders/ParameterSeeder.php"
    - "database/seeders/RolesAndPermissionsSeeder.php"
    - "tests/Feature/Seeders/ParameterSeederTest.php"
    - "tests/Feature/Seeders/DatabaseSeederTest.php"
    - "tests/Feature/Authorization/RolesAndPermissionsSeederTest.php"
    - "tests/Feature/Roles/ManageRolesTest.php"

key-decisions:
  - "features.deteccao_abuso nasce '0' (OFF): HU-149 nunca pune por default — só registra alerta e encaminha à malha fina"
  - "Os 5 abuso.* vão para o grupo NOVO 'abuso' (convenção prefixo→grupo); per_page em 'ui'; toggle em 'features'"
  - "Idempotência por índice parcial: confirmados/descartados NÃO bloqueiam novo alerta aberto futuro do mesmo fingerprint"
  - "Analista NÃO recebe nenhuma das 3 permissões (default gestor/admin; sem papel auditor dedicado — pendência SEDUR)"
  - "Reusa retencao.access_logs.dias (não recria); constantes técnicas ficam em config/sile.php (12-04/12-07)"

patterns-established:
  - "abuse_alerts: ledger auditável (HasAuditoria) com idempotência estrutural — base dos detectores Strategy (12-07)"
  - "Severidade comparável (AbuseSeverity::isAtLeast) para a decisão de encaminhar à malha fina (severity >= limiar)"

# Metrics
duration: ~19min
completed: 2026-06-15
---

# Phase 12 Plan 03: Fundação de dados, parâmetros e permissões da auditoria/abuso Summary

**Ledger `abuse_alerts` (AbuseAlert + HasAuditoria + factory) com idempotência estrutural por índice único parcial, 7 parâmetros HU-014 (toggle de abuso OFF default) e 3 permissões aditivas — baselines atualizadas para 85 parâmetros e 27 permissões no mesmo plano (dono único da Wave 1), sem regressão.**

## Performance

- **Duration:** ~19 min
- **Started:** 2026-06-15T08:08:52Z
- **Completed:** 2026-06-15T08:28:26Z
- **Tasks:** 3 (TDD estrito RED→GREEN em cada)
- **Files:** 6 criados, 6 modificados

## Accomplishments
- Tabela `abuse_alerts` real, auditável (`HasAuditoria`/RN-002) e idempotente por desenho — pronta para os detectores (12-07) e o painel (12-08).
- 7 parâmetros HU-014 administráveis com a detecção de abuso DESLIGADA por default (nunca pune); contagem-baseline 78→85 e grupo novo `abuso` na lista ordenada.
- 3 permissões aditivas atribuídas (gestor/admin/admin) sem `sync`; contagem-baseline 24→27 atualizada nas 3 suítes (RolesAndPermissions/ManageRoles/DatabaseSeeder).

## Schema final de abuse_alerts

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | bigint PK | |
| `rule_key` | string (indexado) | chave do detector (ex.: volume_cnpj) |
| `severity` | string → `AbuseSeverity` | baixa/media/alta (com peso) |
| `status` | string (indexado, default 'aberto') → `AbuseAlertStatus` | aberto/confirmado/descartado |
| `fingerprint` | string | assinatura determinística da ocorrência (idempotência) |
| `evidence` | json nullable → array | evidências do detector |
| `viability_request_id` | FK nullable (indexado, nullOnDelete) | processo relacionado |
| `subject_type`/`subject_id` | nullableMorphs | alvo opcional (empresa/usuário/contador) |
| `window_start`/`window_end` | timestamp nullable | janela analisada |
| `detected_at` | timestamp | momento da detecção |
| `resolved_by_user_id` | FK users nullable (nullOnDelete) | gestor que deu baixa |
| `resolved_at` | timestamp nullable | baixa |
| `justification` | text nullable | justificativa da baixa |
| `fine_mesh_referral_id` | FK fine_mesh_referrals nullable (nullOnDelete) | vínculo com a malha fina |
| `created_at`/`updated_at` | timestamps | |

**Índice único parcial (idempotência estrutural):**

```sql
CREATE UNIQUE INDEX abuse_alerts_open_unique
  ON abuse_alerts (rule_key, fingerprint) WHERE status = 'aberto'
```

Garante 1 alerta ABERTO por (regra, fingerprint) — reprocessar a mesma janela NÃO duplica. Confirmados/descartados liberam novo alerta futuro. Criado via `DB::statement` para pgsql e sqlite (mesma sintaxe); confirmado em ambos os drivers.

## Enums

- `AbuseSeverity` (string): `Baixa='baixa'`, `Media='media'`, `Alta='alta'` + `label()`, `weight()` (1/2/3) e `isAtLeast(self)` — base da regra "severity >= abuso.severidade_malha_fina → malha fina".
- `AbuseAlertStatus` (string): `Aberto='aberto'`, `Confirmado='confirmado'`, `Descartado='descartado'` + `label()` e `isResolved()`.

## 7 parâmetros HU-014 (78 → 85)

| Key | Grupo | Tipo | Default | Validação |
|---|---|---|---|---|
| `ui.auditoria.per_page` | ui | integer | `20` | required, integer, min:5, max:100 |
| `features.deteccao_abuso` | features | boolean | `0` (OFF) | required, boolean |
| `abuso.janela_dias` | abuso | integer | `30` | required, integer, min:1, max:365 |
| `abuso.volume_cnpj.limite` | abuso | integer | `5` | required, integer, min:1, max:1000 |
| `abuso.volume_contador.limite` | abuso | integer | `20` | required, integer, min:1, max:1000 |
| `abuso.escritorio_virtual.limite` | abuso | integer | `3` | required, integer, min:1, max:1000 |
| `abuso.severidade_malha_fina` | abuso | string | `alta` | required, in:baixa,media,alta |

Grupo NOVO `abuso` adicionado à lista ordenada de grupos. Reusa `retencao.access_logs.dias` (não recriado).

## 3 permissões aditivas (24 → 27)

| Permissão | gestor | administrador | analista | cidadão |
|---|---|---|---|---|
| `consultar-auditoria` (HU-097..101) | sim | sim | não | não |
| `monitorar-lgpd` (HU-102; DPO/admin) | não | sim | não | não |
| `gerenciar-alertas-abuso` (HU-149) | sim | sim | não | não |

Atribuição via `givePermissionTo` (aditivo, nunca `sync`).

## Task Commits

1. **Task 1: Ledger abuse_alerts (migration + enums + model + factory + teste)** - `352423e` (feat, TDD)
2. **Task 2: 7 parâmetros HU-014 (78→85)** - `e21f6c8` (feat, TDD)
3. **Task 3: 3 permissões aditivas (24→27)** - `842b275` (feat, TDD)

## Deviations from Plan

None - plano executado exatamente como escrito. Escopo respeitado: nenhum toque em activity_log/AuditService (12-01) nem viability_decisions/FluxoExpressoService (12-02).

## Issues Encountered
- **Migration stub aplicada no pgsql de dev antes do conteúdo final.** A primeira `php artisan migrate` encontrou a migração já marcada como Ran com apenas `id`/`timestamps` (stub do `make:model -m`), sem as colunas nem o índice. Causa-raiz investigada: a tabela existia incompleta e a migração constava aplicada. Correção TARGETada (sem rollback que pudesse atingir 12-01/12-02): `Schema::dropIfExists('abuse_alerts')` + remoção apenas do registro desta migração na tabela `migrations`, seguido de `php artisan migrate` — reaplicou a versão completa. Confirmado por `pg_indexes`/`getColumnListing` (18 colunas + índice parcial). Os testes rodam em SQLite `:memory:` (RefreshDatabase), portanto sempre usaram a versão correta.

## Evidência de verificação (output real)
- RED Task 1: `php artisan test --compact --filter=AbuseAlertModelTest` → 5 errors (classes ausentes), confirmando o RED.
- GREEN Task 1: `php artisan migrate --no-interaction` (pgsql) → DONE; `getColumnListing(abuse_alerts)` = 18 colunas; `pg_indexes` confirma `abuse_alerts_open_unique ... WHERE ((status)::text = 'aberto'::text)`; `AbuseAlertModelTest` → **5 passed, 13 assertions**.
- RED Task 2: `ParameterSeederTest` → 3 falhas (78≠85 ×2 + parâmetros ausentes).
- GREEN Task 2: `php artisan test --compact --filter="ParameterSeederTest|DatabaseSeederTest"` → **26 passed, 512 assertions**.
- RED Task 3: `RolesAndPermissionsSeederTest|ManageRolesTest` → 2 falhas (24≠27) + 1 erro (permissão ausente).
- GREEN Task 3: `php artisan test --compact --filter="RolesAndPermissionsSeederTest|ManageRolesTest|DatabaseSeederTest"` → **31 passed, 295 assertions**.
- Verificação combinada do plano: `php artisan test --compact --filter="ParameterSeederTest|DatabaseSeederTest|RolesAndPermissionsSeederTest|ManageRolesTest|AbuseAlert"` → **54 passed, 708 assertions** (85 parâmetros / 27 permissões).
- Anti-regressão: `php artisan test --compact --exclude-group=postgis` → **1219 passed, 6282 assertions** (suíte completa SQLite verde).
- `vendor/bin/pint --dirty --format agent` → passed em todas as tasks. Sem lints (`ReadLints` limpo).

## Next Phase Readiness
- **12-04** (AuditTrailQueryService/HU-100): `ui.auditoria.per_page` e a permissão `consultar-auditoria` prontas.
- **12-06** (LGPD/HU-102): permissão `monitorar-lgpd` pronta.
- **12-07** (detectores/HU-149): parâmetros `abuso.*` + toggle `features.deteccao_abuso` (OFF) + `abuse_alerts` com upsert idempotente (índice parcial) e `AbuseSeverity::isAtLeast` para o limiar de malha fina.
- **12-08** (painel de alertas): permissão `gerenciar-alertas-abuso` + model `AbuseAlert` (relações `viabilityRequest`/`resolvedBy`/`fineMeshReferral`/`subject`).
- Gotcha de processo único do `RefreshDatabaseState::$migrated`: rodar a suíte como `composer test` (2 processos) na verificação integral (12-11/12-12).

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*
