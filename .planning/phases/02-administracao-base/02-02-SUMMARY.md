---
phase: 02-administracao-base
plan: 02
subsystem: parameters
tags: [laravel, eloquent, cache, crypt, settings, feature-toggles, seeder, hu-014]

# Dependency graph
requires:
  - phase: 01-identidade-acesso-e-auditoria-transversal
    provides: "App\\Support\\Settings (wrapper config), config/sile.php, call sites Settings::get (FortifyServiceProvider, AccessHistoryControllers, AppServiceProvider)"
provides:
  - "Tabela parameters (catálogo como dado: key/group/type/value/default_value/validation_rules/sensitive/description/requires_connection_test)"
  - "Model Parameter: typedValue() por tipo, criptografia condicional de sensíveis (RN-009), invalidação de cache em saved/deleted (núcleo do CA-05)"
  - "Settings::get com backend banco+cache+fallback resiliente (QueryException → config) sem alterar assinatura nem call sites"
  - "Settings::enabled(feature) para feature toggles do grupo features (RN-005)"
  - "ParameterSeeder: catálogo inicial de 10 parâmetros (seguranca/ui/features) idempotente que preserva value administrado"
  - "Password::defaults() lendo security.password.* do registry — política de senha administrável de verdade"
affects: [02-04, 02-05, 02-06, 02-07, 02-08, fase-13-integracoes]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Registry de parâmetros: catálogo seedado por metadados, value administrado intocável por re-seed"
    - "Criptografia condicional via Attribute::make lendo $this->sensitive (flag fora do fillable, ordenada antes de value)"
    - "Cache por chave (sile.parameters.{key}) com Cache::forget em saved/deleted — efeito sem deploy"
    - "Fallback resiliente: try/catch QueryException no Settings::get (boot/artisan/CI sem banco migrado)"

key-files:
  created:
    - database/migrations/2026_06_10_141916_create_parameters_table.php
    - app/Models/Parameter.php
    - database/factories/ParameterFactory.php
    - database/seeders/ParameterSeeder.php
    - tests/Feature/Parameters/ParameterRegistryTest.php
    - tests/Feature/Parameters/SettingsBackendTest.php
    - tests/Feature/Seeders/ParameterSeederTest.php
  modified:
    - app/Support/Settings.php
    - config/sile.php
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "sensitive fora do fillable e ordenado ANTES de value no definition() da factory — o mutator condicional lê $this->sensitive e o fill processa as chaves na ordem do array (Pitfall 3)"
  - "Sem HasAuditoria no Parameter: diff automático vazaria sensível em claro; histórico (RN-008) será auditoria explícita no 02-07"
  - "TTL do cache é constante técnica (sile.parameters.cache_ttl), não parâmetro do registry (evita recursão)"
  - "ParameterSeeder fora do DatabaseSeeder — entrada é responsabilidade do 02-08 (evita conflito de arquivo na wave)"

patterns-established:
  - "Settings::get(key, default): cache → banco (value ?? default do catálogo) → config/sile.php → default do call site"
  - "Settings::enabled(feature): toggle booleano do grupo features com fallback config"
  - "Seeder de catálogo: updateOrCreate(['key' => …], [só metadados]) — NUNCA value"

# Metrics
duration: 14min
completed: 2026-06-10
---

# Fase 2 Plano 02: Registry de Parâmetros e Backend do Settings Summary

**Settings::get trocado para banco+cache+fallback sem tocar nenhum call site (SettingsTest da Fase 1 intocado e verde), com registry criptografando sensíveis, catálogo de 10 parâmetros seedado e política de senha administrável**

## Performance

- **Duration:** 14 min
- **Started:** 2026-06-10T14:14:16Z
- **Completed:** 2026-06-10T14:28:23Z
- **Tasks:** 2 (TDD: RED → GREEN cada)
- **Files modified:** 10

## Accomplishments

- Promessa da Fase 1 ([01-01]) cumprida: backend do `App\Support\Settings` agora é banco + cache por chave + fallback resiliente, mantendo a assinatura `get(string $key, mixed $default = null): mixed` — evidência: `git diff --stat tests/Unit/Support/SettingsTest.php` vazio e teste verde sem edição.
- CA-05 provado no nível de domínio: alterar um `Parameter` invalida o cache da chave em `saved`/`deleted` e a leitura seguinte reflete o novo valor (teste `test_alteracao_tem_efeito_imediato_na_leitura_seguinte`).
- RN-009 provado no nível de modelo: parâmetro sensível gravado cifrado (assert contra o valor cru no banco com `DB::table`) e lido em claro pelo accessor.
- Catálogo inicial de 10 parâmetros (seguranca/ui/features) idempotente: re-seed atualiza apenas metadados e preserva o `value` administrado (Pitfall 2 coberto por teste dedicado).
- Política de senha deixou de ser fachada: `Password::defaults()` lê `security.password.*` do registry — parâmetro de senha alterado no banco muda a validação real (teste com min_length 12).
- Sem banco migrado, todo `artisan`/boot/teste Unit continua funcionando via `QueryException → fallback config` (Pitfall 1) — comprovado pelo próprio `SettingsTest` Unit (sem `RefreshDatabase`).

## Task Commits

Cada task TDD produziu commits atômicos (RED → GREEN; REFACTOR sem mudanças — pint passou limpo nos dois ciclos):

1. **Task 1: Registry (tabela + model + factory)**
   - RED: `e69a5f7` (test) — 5 testes falhando por classe `Parameter` inexistente
   - GREEN: `4bb7cf8` (feat) — migration, model com Crypt condicional e invalidação, factory
2. **Task 2: Settings backend + catálogo + política de senha**
   - RED: `c7248ba` (test) — 8 de 9 testes novos falhando (o de fallback config já passava por construção: comportamento da Fase 1 preservado)
   - GREEN: `552b022` (feat) — novo Settings, config ampliado, Password::defaults via registry, ParameterSeeder

**Plan metadata:** (commit docs deste SUMMARY + STATE.md)

## Files Created/Modified

- `database/migrations/2026_06_10_141916_create_parameters_table.php` — tabela `parameters` (key unique, group index, type, value/default_value TEXT, validation_rules json, sensitive, description, requires_connection_test)
- `app/Models/Parameter.php` — `typedValue()` com match por tipo, accessor/mutator condicional com `Crypt`, `Cache::forget` em `booted()`, `sensitive` fora do fillable, sem trait de auditoria automática
- `database/factories/ParameterFactory.php` — definition com `sensitive` antes de `value`; states `sensitive()` e `integer()`
- `database/seeders/ParameterSeeder.php` — catálogo de 10 entradas com descrições pt-BR; update só de metadados
- `app/Support/Settings.php` — backend banco+cache+fallback; novo helper `enabled(feature)`
- `config/sile.php` — adiciona `ui.cnaes.per_page`, `ui.users.per_page`, `features.procuracoes`, `parameters.cache_ttl` (preserva todas as chaves da Fase 1)
- `app/Providers/AppServiceProvider.php` — closure de `Password::defaults()` lê `security.password.*` via `Settings::get`; linha `auth.passwords.users.expire` intocada
- `tests/Feature/Parameters/ParameterRegistryTest.php` — 5 testes (casts, default, criptografia, claro, invalidação)
- `tests/Feature/Parameters/SettingsBackendTest.php` — 6 testes (banco, fallback config, fallback catálogo, efeito imediato, enabled, senha)
- `tests/Feature/Seeders/ParameterSeederTest.php` — 3 testes (catálogo completo, preserva value, idempotência)

## Decisions Made

- `sensitive` imutável por interface (fora do fillable) e definido apenas em seed/factory — mitigação estrutural do Pitfall 3.
- Histórico de alterações (RN-008/CA-07) não entra aqui: sem `HasAuditoria` no model (vazaria sensível); auditoria explícita com mascaramento entra no 02-07 junto com a UI.
- `requires_connection_test` criado como contrato do RN-010 sem implementação de teste de conexão — sem feature de fachada; implementações reais na Fase 13.
- TTL do cache como constante técnica em `config/sile.php`, não como parâmetro administrável (parametrizá-lo no próprio registry criaria recursão).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Ordem de `sensitive` no `definition()` da factory (não apenas no state)**

- **Found during:** Task 1 (escrita da factory, antes do GREEN)
- **Issue:** O plano posicionava `value` antes de `sensitive` no `definition()` e mandava ordenar `sensitive` antes de `value` no state. Como `array_merge` dos states preserva a POSIÇÃO original das chaves do `definition()`, o state não muda a ordem efetiva — `value` seria processado antes de `sensitive` no fill e o mutator gravaria o segredo em claro (exatamente o Pitfall 3 que o assert de banco pega).
- **Fix:** `sensitive => false` declarado ANTES de `value => null` no próprio `definition()`, com comentário explicando a restrição de ordem; o state `sensitive()` apenas troca o valor da flag, mantendo a posição correta.
- **Files modified:** database/factories/ParameterFactory.php
- **Verification:** `test_valor_sensivel_e_criptografado_no_banco` verde (valor cru no banco difere do texto plano e não o contém)
- **Committed in:** 4bb7cf8 (GREEN da Task 1)

---

**Total deviations:** 1 auto-fixed (1 bug evitado em construção)
**Impact on plan:** Nenhum scope creep — o próprio plano previa que "o assert de banco pega regressão de ordem"; a correção antecipou a falha.

## Issues Encountered

None.

## Authentication Gates

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `Settings::get`/`Settings::enabled` e o catálogo prontos para consumo pelos planos 02-04..02-07 (telas usam `ui.*.per_page`; toggle `features.procuracoes` pronto para o CA-06).
- 02-07 (UI de parâmetros) implementa: FormRequest validando com `validation_rules` do registro, auditoria explícita com mascaramento `[criptografado]`, mascaramento de sensíveis na resposta.
- 02-08 deve registrar o `ParameterSeeder` no `DatabaseSeeder` (decisão deste plano para evitar conflito de arquivo na wave).
- Suíte completa NÃO foi executada aqui (wave 1 paralela) — fechamento da wave é do orquestrador.

---
*Phase: 02-administracao-base*
*Completed: 2026-06-10*
