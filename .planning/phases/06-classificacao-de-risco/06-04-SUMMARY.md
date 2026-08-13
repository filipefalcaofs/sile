---
phase: 06-classificacao-de-risco
plan: 04
subsystem: backend
tags: [encaminhamento, parametrizacao, hu-014, gatilhos, semi-expresso, dto-readonly, enum, eloquent, sqlite, auditoria]

# Dependency graph
requires:
  - phase: 06-02-classificacao-risco-municipal
    provides: "Enum RiscoMunicipal (baixo_a/baixo_b/alto, sem 'médio') + risk_classifications versionadas — base do nível decisivo do encaminhamento"
  - phase: 06-03-risco-sanitario
    provides: "Enum RiscoSanitario + risk_condicionantes (condicionante-pergunta) — dimensão sanitária separada que o RiscoResult espelha"
  - phase: 02-administracao-base
    provides: "Catálogo de parâmetros HU-014 (ParameterSeeder upsert idempotente, value preservado) + Settings::get (banco→cache→config) + Parameter::typedValue (json→array)"
  - phase: 04-georreferenciamento
    provides: "Padrão de DTO readonly (TerritoryResult: status por dimensão, versoes(), toArray snake_case) espelhado nos RiscoInput/RiscoResult"
  - phase: 01-identidade
    provides: "AuditService.log (RN-002) + HasAuditoria — auditoria do CRUD de gatilhos pelos mantenedores (06-06)"
provides:
  - "Enum Fluxo (expresso/analise) — destino do encaminhamento; NÃO existe 'semi_expresso' como fluxo"
  - "Enum TipoGatilho (enquadramento_ausente/zeis_especial/dados_do_processo) — categoria semi-expresso"
  - "Tabela tabular risk_triggers (codigo, titulo, motivo auditável, ativo, categoria) — gatilhos como DADO parametrizado, ativáveis por interface"
  - "Model RiskTrigger (HasAuditoria, casts codigo→TipoGatilho + ativo→bool, scope ativos()) + factory"
  - "RiskTriggerSeeder (upsert por codigo, aditivo em ativo — preserva liga/desliga administrado), integrado ao DatabaseSeeder"
  - "Parâmetros risco.mapa_encaminhamento (json, default baixo_a/baixo_b=expresso, alto=analise) e risco.dimensao_tvl (default municipal) no catálogo (29→31), com fallback sile.risco.* em config/sile.php"
  - "DTOs readonly RiscoInput/RiscoResult — contrato snake_case (toArray), versoes() por dimensão e encaminhadoParaAnalise() — insumo do motor 06-05 e do EP07+"
affects: [06-05-motor-risco, 06-06-mantenedores-publicacao, 06-08-ui-risco]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Encaminhamento como PARÂMETRO (HU-014): o mapa risk_level→fluxo é jsonb administrável (typedValue decodifica para array; fallback de config também é array — o consumidor sempre recebe array), troca sem deploy"
    - "Gatilhos CNAE (semi-expresso) como TABELA parametrizada (risk_triggers): ativáveis por interface, com motivo auditável — dado, nunca hardcode"
    - "Seeder aditivo em ativo: ativo NUNCA entra no array de update do upsert (default da coluna na criação) — re-seed preserva o liga/desliga administrado, precedente do value do ParameterSeeder"
    - "DTOs readonly de risco espelham TerritoryResult: dimensões separadas (municipal/sanitário), status por dimensão, toArray snake_case, versoes()"

key-files:
  created:
    - app/Enums/Fluxo.php
    - app/Enums/TipoGatilho.php
    - database/migrations/2026_06_14_014719_create_risk_triggers_table.php
    - app/Models/RiskTrigger.php
    - database/factories/RiskTriggerFactory.php
    - database/seeders/RiskTriggerSeeder.php
    - app/Services/Risco/RiscoInput.php
    - app/Services/Risco/RiscoResult.php
    - tests/Feature/Risco/RiskTriggerSeederTest.php
    - tests/Unit/Risco/RiscoDtoTest.php
  modified:
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "Mapa de encaminhamento é parâmetro json (risco.mapa_encaminhamento), não 3 toggles: troca atômica do roteamento sem deploy; nível ausente no mapa é decisão de DEGRADAÇÃO do consumidor (06-05) → 'analise', nunca inventar nível. O Decreto não tem 'médio'."
  - "Fallback de config/sile.php é o ARRAY já decodificado; typedValue() do parâmetro json também devolve array — o consumidor (motor 06-05) sempre recebe array, nunca string (provado por teste e por Settings::get no tinker)."
  - "ativo fora do array de update do RiskTriggerSeeder (upsert aditivo): o default true da coluna cobre a criação; re-seed não sobrescreve o liga/desliga administrado — precedente do value do ParameterSeeder."
  - "Migration risk_triggers com timestamp explícito 014719 (após as 014716/7/8 da fase): sem FK a rule_versions (gatilho é parametrização própria), mas mantém a ordem cronológica do conjunto."
  - "RiscoResult expõe $versoes como propriedade E versoes() como método (getter, paralelo a TerritoryResult::versoes()): cada dimensão também carrega versao_regras no toArray (self-contido), e o top-level versoes é o resumo consolidado por dimensão."
  - "Verificação SQLite via 'php artisan test --compact --exclude-group postgis' (precedente 06-01/02/03): o grupo postgis exige container; a migration tabular é provada pelo RefreshDatabase em :memory:."

patterns-established:
  - "Roteamento (fluxo) e gatilhos nascem como parâmetro/dado; o motor (06-05) só APLICA — separação regra-vs-código da HU-014"
  - "Enum de fluxo com 2 destinos (expresso/analise); semi-expresso é categoria de gatilho que derruba para analise, nunca um fluxo"

# Metrics
duration: 15min
completed: 2026-06-14
---

# Phase 6 Plan 04: Encaminhamento Parametrizável e Gatilhos Summary

**O encaminhamento da viabilidade locacional vira PARÂMETRO (HU-014): o mapa `risk_level→fluxo` (`risco.mapa_encaminhamento`, default baixo_a/baixo_b=expresso, alto=analise) e a dimensão decisiva (`risco.dimensao_tvl`, default municipal) trocam sem deploy; os gatilhos CNAE (semi-expresso) viram a tabela parametrizada `risk_triggers` (3 conhecidos, ativáveis, com motivo auditável); e o contrato do motor (06-05) nasce nos DTOs readonly `RiscoInput`/`RiscoResult`, espelhando `TerritoryResult`. Toda a regra de roteamento é dado — o motor só aplica.**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-06-14T02:25:00Z
- **Completed:** 2026-06-14T02:40:00Z
- **Tasks:** 3 (todas TDD RED→GREEN→pint)
- **Files created:** 10 | **modified:** 5

## Accomplishments
- Enums `Fluxo` (expresso/analise — sem 'semi_expresso' como fluxo) e `TipoGatilho` (os 3 gatilhos conhecidos) com `label()` pt-BR.
- Tabela tabular `risk_triggers` + model `RiskTrigger` (HasAuditoria, scope `ativos()`) + factory + `RiskTriggerSeeder` (upsert aditivo em `ativo`), integrado ao `DatabaseSeeder`.
- Parâmetros `risco.mapa_encaminhamento` (json) e `risco.dimensao_tvl` (string) no catálogo (29→31), com fallback `sile.risco.*` em `config/sile.php` e contagem atualizada nos DOIS testes.
- DTOs readonly `RiscoInput`/`RiscoResult` com contrato snake_case (`toArray`), `versoes()` por dimensão e `encaminhadoParaAnalise()`.
- Suíte SQLite: **484/484 verde** (475 baseline + 9 novos), sem regressão. `--filter=Risco` 25/25.

## Task Commits

Cada task foi commitada atomicamente (TDD RED→GREEN):

1. **Task 1: Enums Fluxo/TipoGatilho + tabela/model/factory/seeder risk_triggers** - `16126a4` (feat)
2. **Task 2: Parâmetros risco.mapa_encaminhamento + risco.dimensao_tvl (catálogo + config + 2 testes)** - `88ddde2` (feat)
3. **Task 3: DTOs RiscoInput/RiscoResult readonly** - `9e69656` (feat)

**Plan metadata:** este SUMMARY (docs).

## Contrato dos artefatos (insumo do motor 06-05, da UI 06-08 e dos mantenedores 06-06)

### Parâmetros novos (catálogo HU-014, grupo `risco`)
| key | group | type | default_value | validation_rules |
|---|---|---|---|---|
| `risco.mapa_encaminhamento` | risco | json | `{"baixo_a":"expresso","baixo_b":"expresso","alto":"analise"}` | `['required','json']` |
| `risco.dimensao_tvl` | risco | string | `municipal` | `['required','in:municipal,sanitario']` |

- `typedValue()` do `mapa_encaminhamento` devolve o **array** `['baixo_a'=>'expresso','baixo_b'=>'expresso','alto'=>'analise']`; o fallback `config('sile.risco.mapa_encaminhamento')` é o **mesmo array** — o consumidor sempre recebe array (provado por teste e por `Settings::get` no tinker).
- **Nível ausente no mapa ⇒ o consumidor (06-05) degrada para `analise`** (degradação segura). NUNCA inventar nível; o Decreto não tem 'médio'.
- Grupos ordenados passam a `['features','geo','integracoes','retencao','risco','seguranca','ui']`.

### Tabela `risk_triggers` (gatilhos parametrizados — categoria semi-expresso)
`id`, `codigo` (cast `TipoGatilho`, unique), `titulo` (string), `motivo` (text — motivo auditado quando acionado), `ativo` (bool, default true), `categoria` (string, default `semi_expresso`), `timestamps`. Índice: `unique(codigo)`. Scope `RiskTrigger::ativos()` filtra `ativo=true`.

Gatilhos seedados (todos `ativo=true`, `categoria='semi_expresso'`):
| codigo | titulo | quando aciona |
|---|---|---|
| `enquadramento_ausente` | Enquadramento locacional ausente | CNAE sem classificação de risco vigente (FA-02) |
| `zeis_especial` | Localização em ZEIS | imóvel em Zona Especial de Interesse Social (restrição da Fase 4) |
| `dados_do_processo` | Dados do processo exigem análise | porte/área/condicionantes declarados demandam análise |

Cada gatilho acionado derruba o encaminhamento para `analise` (semi-expresso) com o `motivo` registrado na decisão. O seeder é **aditivo em `ativo`** (re-seed preserva o liga/desliga administrado).

### Enum `Fluxo` (string)
`Expresso='expresso'`, `Analise='analise'`. `label()` → 'Fluxo expresso' / 'Análise técnica'. **NÃO existe 'semi_expresso' como fluxo** — semi-expresso é a categoria do gatilho que derruba para `analise`; o destino final é sempre expresso|analise.

### DTO `App\Services\Risco\RiscoInput` (final readonly)
Construtor promovido: `string $cnaeCode`, `array $respostasCondicionantes = []` (mapa condicionante_id/pergunta → bool), `array $gatilhosContexto = []` (valores de `TipoGatilho` ativos no contexto — ex.: `zeis_especial` vindo do território), `?CarbonInterface $data = null` (reprodução por época; null = vigente). Estático `RiscoInput::paraCnae(string $cnae): self` (atalho sem respostas/gatilhos).

### DTO `App\Services\Risco\RiscoResult` (final readonly) — contrato do EP07+
Construtor promovido (5 arrays de shape estável) + constantes `STATUS_CLASSIFICADO='classificado'` / `STATUS_NAO_CLASSIFICADO='nao_classificado'`:
- `municipal`: `{status, nivel, nivel_label, condicionantes, versao_regras}`
- `sanitario`: `{status, nivel_original, nivel_final, reclassificado(bool), condicionantes_perguntas[], versao_regras}`
- `encaminhamento`: `{fluxo, dimensao_decisiva, motivo, gatilhos_acionados[]}`
- `fundamentacao`: `list<string>` (referências legais)
- `versoes`: `{municipal, sanitario}` (versão de regra por dimensão)

Métodos: `versoes(): array` (por dimensão), `toArray(): array` (snake_case, espelha `TerritoryResult::toArray` — chaves `municipal/sanitario/encaminhamento/fundamentacao/versoes`), `encaminhadoParaAnalise(): bool` (`fluxo === 'analise'`).

`status` por dimensão ∈ {`classificado`, `nao_classificado`}. `nao_classificado` quando o CNAE não está na tabela vigente (FA-02 → segue para análise; nunca inventa nível).

## Files Created/Modified
- `app/Enums/Fluxo.php` - Destino do encaminhamento (expresso/analise) + label().
- `app/Enums/TipoGatilho.php` - Os 3 gatilhos semi-expresso + label().
- `database/migrations/2026_06_14_014719_create_risk_triggers_table.php` - Tabela tabular parametrizada de gatilhos.
- `app/Models/RiskTrigger.php` - Model (HasAuditoria, casts codigo→enum/ativo→bool, scope ativos()).
- `database/factories/RiskTriggerFactory.php` - Factory + state inativo().
- `database/seeders/RiskTriggerSeeder.php` - Semeia os 3 gatilhos (upsert aditivo em ativo).
- `app/Services/Risco/RiscoInput.php` - DTO readonly de entrada + paraCnae().
- `app/Services/Risco/RiscoResult.php` - DTO readonly de resultado (dimensões separadas, toArray, versoes, encaminhadoParaAnalise).
- `database/seeders/ParameterSeeder.php` - +2 parâmetros risco.* (grupo risco).
- `config/sile.php` - +bloco fallback sile.risco.* (mapa como array, dimensao_tvl).
- `database/seeders/DatabaseSeeder.php` - Registra RiskTriggerSeeder após RiscoSanitarioSeeder.
- `tests/Feature/Risco/RiskTriggerSeederTest.php` - 3 gatilhos, idempotência, scope ativos, preservação de ativo administrado (4 testes).
- `tests/Unit/Risco/RiscoDtoTest.php` - paraCnae, toArray snake_case, versoes por dimensão, encaminhadoParaAnalise (4 testes).
- `tests/Feature/Seeders/ParameterSeederTest.php` - 29→31, grupo risco ordenado, +teste dos parâmetros de encaminhamento.
- `tests/Feature/Seeders/DatabaseSeederTest.php` - 29→31 nas duas asserções (seed completo + idempotente).

## Decisions Made
- **Mapa de encaminhamento como parâmetro json** (não 3 toggles): troca atômica do roteamento sem deploy; o nível ausente no mapa é tratado pelo consumidor (06-05) como degradação para `analise` — nunca inventa nível.
- **Consumidor sempre recebe array**: `typedValue()` do json e o fallback de config são ambos arrays decodificados — sem ramo string no motor.
- **Seeder aditivo em `ativo`**: o default `true` da coluna cobre a criação; `ativo` fora do update do upsert preserva o liga/desliga administrado (precedente do `value` do ParameterSeeder).
- **`versoes` propriedade + `versoes()` método** no RiscoResult: paralelo a TerritoryResult; cada dimensão também carrega `versao_regras` (self-contido) e o top-level é o resumo consolidado.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Asserção do `codigo` no teste comparava string com enum**
- **Found during:** Task 1 (primeira run GREEN).
- **Issue:** `RiskTrigger::query()->pluck('codigo')` aplica o cast `TipoGatilho` (Eloquent hidrata o enum), devolvendo instâncias de enum — não strings. A asserção `assertEqualsCanonicalizing(['enquadramento_ausente', ...], pluck)` falhava (enum × string).
- **Fix:** Mapear o resultado para `->value` antes de comparar — prova de quebra o cast funciona. Comportamento sob teste (3 gatilhos com os códigos certos) inalterado.
- **Files modified:** tests/Feature/Risco/RiskTriggerSeederTest.php
- **Verification:** test_seeder_cria_os_tres_gatilhos_conhecidos verde (4/4).
- **Committed in:** `16126a4`

**2. [Verificação] `--exclude-group postgis` em vez de `migrate:fresh --env=testing`**
- Mesma natureza e racional do 06-01/02/03 (sem `.env.testing`; o grupo postgis exige container). A migration tabular `risk_triggers` é provada pelo `RefreshDatabase` em SQLite `:memory:`.

---

**Total deviations:** 1 ajuste de teste (cast de enum no pluck) + 1 de verificação (mesmo padrão da fase).
**Impact on plan:** Sem scope creep. Todos os arquivos dentro do `files_modified` do 06-04. Nada do motor completo `RiscoClassificationService` (06-05) nem dos mantenedores/golden (06-06/07) foi tocado.

## Issues Encountered
- `pluck('codigo')` aplica o cast de enum no Laravel 13 (devolve `TipoGatilho`, não string) — ajustado no teste (deviation 1); é o comportamento correto e desejado do cast.

## User Setup Required
None - sem configuração de serviço externo. Sem dependência nova.

## Next Phase Readiness
- **06-05 (motor `RiscoClassificationService`):** consome `RiscoInput`/`RiscoResult` (contrato pronto), lê `Settings::get('risco.mapa_encaminhamento')` (array) e `risco.dimensao_tvl` para decidir o fluxo, e `RiskTrigger::ativos()` para os gatilhos semi-expresso. Nível ausente no mapa / CNAE não classificado / gatilho acionado ⇒ `analise` com motivo. Auditoria via AuditService (log 'risco', event 'classificacao').
- **06-06 (mantenedores):** CRUD de `risk_triggers` (auditado via HasAuditoria, liga/desliga `ativo`) + edição dos parâmetros `risco.*` pela tela de parâmetros (HU-014).
- **06-08 (UI de risco):** consome o `RiscoResult::toArray` (snake_case) — dimensões municipal/sanitário separadas, encaminhamento e fundamentação.
- Sem blockers introduzidos por este plano. Lista definitiva de gatilhos CNAE e prevalência da dimensão no TVL seguem pendentes SEDUR (a tabela e o parâmetro destravam — dado, não código).

---
*Phase: 06-classificacao-de-risco*
*Completed: 2026-06-14*
