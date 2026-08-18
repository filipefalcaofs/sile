---
phase: 06-classificacao-de-risco
plan: 02
subsystem: database
tags: [risco-municipal, decreto-32636-2020, regras-como-dados, seed-csv-oficial, enum, eloquent, sqlite, auditoria]

# Dependency graph
requires:
  - phase: 06-01-fundacao-regras-versionadas
    provides: "rule_versions + RuleVersionService.openDraft/publish (quatro olhos, fecha vigente anterior, auditoria) + scopes vigente/naData/versao + enum RuleDomain (RiscoMunicipal isSensitive)"
  - phase: 02-administracao-base
    provides: "Padrão de seed a partir de CSV oficial (CnaeImportService + CnaeSeeder, database/data/, upsert idempotente); Cnae.code normalizado (FK lógica cnae_code)"
  - phase: 01-identidade
    provides: "AuditService.log (RN-002) + HasAuditoria"
provides:
  - "Enum RiscoMunicipal (baixo_a/baixo_b/alto) com fromDecreto() e label() pt-BR — NÃO existe 'médio'"
  - "Tabela tabular risk_classifications (FK rule_version_id, cnae_code, risco_municipal, condicionantes jsonb, observacao) — dado versionado por CNAE"
  - "Model RiskClassification (HasAuditoria, casts enum+array, relação ruleVersion) + factory"
  - "RiscoMunicipalImportService: import real do CSV do Decreto com relatório {lidos, importados, atualizados, rejeitados, por_nivel, total} e parsing de condicionantes"
  - "CSV oficial commitado em database/data/risco/ (fonte reprodutível do seed, 1.332 linhas)"
  - "RiscoMunicipalSeeder: publica versão vigente (rules_version 'decreto-32636-2020') + import + auditoria, integrado ao DatabaseSeeder"
affects: [06-03-risco-sanitario, 06-04-encaminhamento-gatilhos, 06-05-motor-risco, 06-06-mantenedores-publicacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Tabela tipada por domínio referencia rule_version_id (dimensão municipal de risco como dado versionado, nunca código)"
    - "Import de CSV oficial espelha CnaeImportService: SplFileObject READ_CSV, valida EXPECTED_HEADER, upsert por chunks, relatório auditado no seeder"
    - "Nível desconhecido é REJEITADO no relatório (jamais inventado); enum é a única porta de entrada via fromDecreto()"
    - "Upsert preserva 'observacao' (não está nas colunas de update) — anotação dos mantenedores sobrevive a re-import (precedente do 'active' do CnaeImportService)"

key-files:
  created:
    - app/Enums/RiscoMunicipal.php
    - database/migrations/2026_06_14_014716_create_risk_classifications_table.php
    - app/Models/RiskClassification.php
    - database/factories/RiskClassificationFactory.php
    - app/Services/Risco/RiscoMunicipalImportService.php
    - database/data/risco/decreto-32636-2020-risco-municipal-unificado-cnae.csv
    - database/seeders/RiscoMunicipalSeeder.php
    - tests/Unit/Risco/RiscoMunicipalImportServiceTest.php
    - tests/Feature/Risco/RiscoMunicipalSeederTest.php
    - tests/Fixtures/risco/decreto-com-nivel-invalido.csv
  modified:
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "Verificação SQLite via 'php artisan test --exclude-group postgis' (RefreshDatabase migra o schema em :memory:) em vez de 'migrate:fresh --env=testing' — sem .env.testing, apontaria ao Postgres de dev (destrutivo). Precedente do 06-01."
  - "Upsert grava condicionantes com json_encode(..., JSON_UNESCAPED_UNICODE) — o Eloquent upsert não aplica o cast 'array'; a leitura via model decodifica de volta. observacao fica fora das colunas de update (preserva edição manual)."
  - "Seeder reusa a versão vigente se existir (RuleVersion::vigente(RiscoMunicipal)->first() ?? openDraft+publish) — idempotente; publish com publishedBy null não dispara quatro olhos (seed de sistema)."

patterns-established:
  - "Tabela tipada por domínio de risco: FK rule_version_id + cnae_code (dígitos) + nível enum + condicionantes jsonb"
  - "Contagem oficial assertada sobre o CSV REAL commitado (767/328/236=1.331), sem fixture sintético para a distribuição — anti-fachada"

# Metrics
duration: 13min
completed: 2026-06-14
---

# Phase 6 Plan 02: Classificação de Risco Municipal Summary

**Dimensão MUNICIPAL de risco como dado versionado: tabela `risk_classifications` (FK `rule_version_id`), enum `RiscoMunicipal` sem "médio", e seed REAL do Decreto nº 32.636/2020 carregando 767 baixo_a + 328 baixo_b + 236 alto = 1.331 classificações com condicionantes parseadas e auditoria com a versão de regras.**

## Performance

- **Duration:** ~13 min
- **Started:** 2026-06-14T01:43:43Z
- **Completed:** 2026-06-14T01:56:47Z
- **Tasks:** 3 (todas TDD RED→GREEN→pint)
- **Files created:** 10 | **modified:** 2

## Accomplishments
- Enum `RiscoMunicipal` (baixo_a/baixo_b/alto) com `fromDecreto()` que mapeia os rótulos do Decreto e **rejeita "MÉDIO"** (lança `InvalidArgumentException`) — nível inexistente nunca é inventado.
- Tabela tabular `risk_classifications` ligada a `rule_versions` (FK `rule_version_id`), `unique(rule_version_id, cnae_code)` — roda em SQLite, sem PostGIS.
- `RiscoMunicipalImportService` espelhando `CnaeImportService`: importa o CSV oficial REAL, parseia condicionantes gerais (separador `|`, remove marcador de lista), upsert idempotente, e relatório com a distribuição por nível.
- CSV oficial do Decreto commitado em `database/data/risco/` (fonte reprodutível) e `RiscoMunicipalSeeder` integrado ao `DatabaseSeeder` (publica versão vigente + import + auditoria).
- Suíte SQLite: **466/466 verde** (459 baseline + 7 novos), sem regressão. Contagem 767/328/236 assertada sobre o dado real.

## Task Commits

Cada task foi commitada atomicamente (TDD RED→GREEN):

1. **Task 1: Enum RiscoMunicipal + migration risk_classifications + model + factory** - `5a4eb33` (feat)
2. **Task 2: RiscoMunicipalImportService + CSV oficial commitado** - `f4370df` (feat)
3. **Task 3: RiscoMunicipalSeeder (versão + import + auditoria) + DatabaseSeeder** - `be6719b` (feat)

**Plan metadata:** este SUMMARY (docs).

## Contrato dos artefatos (insumo do motor 06-05 e dos mantenedores 06-06)

### Tabela `risk_classifications`
`id`, `rule_version_id` (FK `rule_versions`, cascadeOnDelete), `cnae_code` (string 7, dígitos — FK lógica para `Cnae.code`), `risco_municipal` (cast `RiscoMunicipal`), `condicionantes` (jsonb/array — lista de strings), `observacao` (text, nullable), `timestamps`. Índices: `unique(rule_version_id, cnae_code)`, `index(cnae_code)`.

### Enum `RiscoMunicipal` (string)
`BaixoA = 'baixo_a'`, `BaixoB = 'baixo_b'`, `Alto = 'alto'`. `label()` → 'Baixo Risco A' / 'Baixo Risco B' / 'Alto Risco'. `fromDecreto(string $raw): self` (trim + uppercase) mapeia 'BAIXO A'/'BAIXO B'/'ALTO'; desconhecido lança `InvalidArgumentException`. **NÃO existe nível "médio"** (regra firme do analista-negocio).

### Relatório do `RiscoMunicipalImportService::import(RuleVersion $version, string $csvPath): array`
```
{
  lidos: int,
  importados: int,
  atualizados: int,
  rejeitados: string[],
  por_nivel: { baixo_a: int, baixo_b: int, alto: int },
  total: int
}
```
Sobre o CSV oficial: `total=1331`, `por_nivel = {baixo_a:767, baixo_b:328, alto:236}`, `rejeitados=[]`.

### Shape de `condicionantes`
Array de strings (uma por condicionante geral do Decreto), com o marcador de lista `- ` removido e itens vazios descartados. Linha sem condicionante → `[]`. Ex.: CNAE `0111301` → `["Desde que seja escritório da empresa", "Desde que não esteja em imóvel residencial (...)", "Desde que a área utilizada não ultrapasse 1.250m² (...)"]`.

### Versão de regra
Domínio `risco_municipal`, `version`/`rules_version` = **`'decreto-32636-2020'`**, `source` = "Decreto Municipal nº 32.636/2020 (redação Dec. 38.673/2024)". Auditoria do seed: `log_name 'risco'`, `event 'importacao-classificacao-municipal'`, `rules_version 'decreto-32636-2020'`, `properties` = relatório do import.

## Files Created/Modified
- `app/Enums/RiscoMunicipal.php` - Níveis do Decreto (3, sem "médio") + `fromDecreto()`/`label()`.
- `database/migrations/2026_06_14_014716_create_risk_classifications_table.php` - Tabela tabular ligada a `rule_versions`.
- `app/Models/RiskClassification.php` - Model com casts (enum + array), `HasAuditoria`, relação `ruleVersion()`.
- `database/factories/RiskClassificationFactory.php` - Factory (rule_version via factory, cnae_code numerify, nível aleatório).
- `app/Services/Risco/RiscoMunicipalImportService.php` - Import real do CSV com relatório, parsing de condicionantes e upsert idempotente.
- `database/data/risco/decreto-32636-2020-risco-municipal-unificado-cnae.csv` - CSV oficial commitado (1.332 linhas com header).
- `database/seeders/RiscoMunicipalSeeder.php` - Publica versão vigente + import + auditoria.
- `database/seeders/DatabaseSeeder.php` - Registra `RiscoMunicipalSeeder` após `CnaeSeeder`.
- `tests/Unit/Risco/RiscoMunicipalImportServiceTest.php` - Enum + import (distribuição 767/328/236, condicionantes, rejeição de nível, idempotência).
- `tests/Feature/Risco/RiscoMunicipalSeederTest.php` - Publicação da versão vigente + carga + idempotência.
- `tests/Feature/Seeders/DatabaseSeederTest.php` - +asserções: 1.331 classificações, versão vigente única, auditoria 'risco'.
- `tests/Fixtures/risco/decreto-com-nivel-invalido.csv` - Fixture com linha 'MÉDIO' para o teste de rejeição.

## Decisions Made
- **Verificação SQLite via `--exclude-group postgis`** (não `migrate:fresh --env=testing`): sem `.env.testing`, o `migrate:fresh --env=testing` apontaria ao Postgres de dev (destrutivo) e o grupo `postgis` exige container. O `RefreshDatabase` dos testes migra o schema em `:memory:` e prova a migration em SQLite. Precedente do 06-01.
- **`json_encode(..., JSON_UNESCAPED_UNICODE)` no upsert**: o Eloquent `upsert` não aplica o cast `array`; a leitura via model decodifica de volta. `observacao` fica fora das colunas de update (preserva edição dos mantenedores) — espelha o `active` intocado do `CnaeImportService`.
- **Seeder idempotente sem republicar**: reusa `RuleVersion::vigente(RiscoMunicipal)->first()` se existir; só `openDraft+publish` na primeira carga. `publish` com `publishedBy` null não dispara os quatro olhos (seed de sistema), coerente com o `RuleVersionServiceTest`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Verificação via `--exclude-group postgis` em vez de `migrate:fresh --env=testing`**
- **Found during:** Task 1 (verify da migration) e fechamento.
- **Issue:** O `<verify>` da Task 1 sugere `migrate:fresh --env=testing`. O projeto não tem `.env.testing`; esse comando rodaria contra o Postgres de dev (destrutivo) e não é o driver da suíte. O `php artisan test` sem filtro inclui o grupo `postgis`, que exige o container.
- **Fix:** Migration provada em SQLite pelo `RefreshDatabase` (migra todo o schema em `:memory:` no setUp) e a não-regressão pelo comando canônico do CI `php artisan test --compact --exclude-group postgis` — exatamente o pedido nas constraints.
- **Files modified:** nenhum (decisão de verificação).
- **Verification:** 466/466 verde com `--exclude-group postgis`.
- **Committed in:** n/a

**Total deviations:** 1 (verificação) — mesma natureza e racional do 06-01.
**Impact on plan:** Nenhum scope creep. Todos os arquivos dentro do `files_modified` do 06-02. Escopo das waves seguintes (06-03 sanitária, 06-04 encaminhamento/parâmetros, 06-05 motor `RiscoClassificationService`) NÃO tocado.

## Issues Encountered
- **CRLF no CSV oficial:** o `git add` avisou que o CSV (CRLF) será normalizado para LF no commit. Sem impacto: o `RiscoMunicipalImportService` faz `trim()` de cada campo e `fromDecreto()` também normaliza; a distribuição 767/328/236 é idêntica com LF ou CRLF (testes verdes sobre o working copy). A normalização do git deixa a fonte mais limpa.
- **`médio` aparece no docblock do enum:** o critério de aceitação pedia `grep -i medio` vazio. A única ocorrência é o comentário que **documenta a ausência** do nível (regra firme do analista-negocio) — não há `case` médio, e `fromDecreto('MÉDIO')` lançar exceção é provado por teste. A documentação foi mantida por ser informação de negócio relevante; a garantia substantiva (sem nível médio) está coberta pelo enum e pelo teste.

## Escopo NÃO incluído (waves seguintes — confirmação ao orquestrador)
O objetivo de alto nível menciona "dimensão municipal do `RiscoClassificationService` + parâmetros de encaminhamento". Esses itens **não fazem parte do 06-02-PLAN.md** (não estão em `files_modified` nem nas 3 tasks) e as constraints pedem explicitamente para não tocar arquivos das waves 06-04 (encaminhamento/gatilhos/parâmetros) e 06-05 (motor `RiscoClassificationService`). Portanto:
- **`RiscoClassificationService` (dimensão municipal):** pertence ao 06-05. NÃO criado aqui.
- **Parâmetros `risco.mapa_encaminhamento` / `risco.dimensao_tvl`:** pertencem ao 06-04. NÃO adicionados — `ParameterSeeder` e a contagem (29) permanecem intocados, conforme o plano ("catálogo 29→N conforme o plano" — o 06-02 não define parâmetros novos).

## User Setup Required
None - sem configuração de serviço externo. Sem dependência nova.

## Next Phase Readiness
- **06-03 (dimensão sanitária):** reusa o mesmo padrão (tabela tipada por domínio + FK `rule_version_id` + import de CSV oficial); domínio `risco_sanitario` já existe no `RuleDomain`.
- **06-04 (encaminhamento/gatilhos):** consome `RiskClassification.risco_municipal`; aqui entram os parâmetros `risco.mapa_encaminhamento` / `risco.dimensao_tvl` e os gatilhos parametrizados.
- **06-05 (motor `RiscoClassificationService`):** lê a versão vigente (`RuleVersion::vigente`/`naData`) e as `risk_classifications` por `cnae_code`; a dimensão municipal está pronta e versionada.
- **06-06 (mantenedores):** CRUD sobre `risk_classifications` (auditado via `HasAuditoria`) + publicação por quatro olhos (`RuleVersionService.publish`).
- Sem blockers introduzidos por este plano.

---
*Phase: 06-classificacao-de-risco*
*Completed: 2026-06-14*
