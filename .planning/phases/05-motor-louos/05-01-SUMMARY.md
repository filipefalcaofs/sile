---
phase: 05-motor-louos
plan: 01
subsystem: database
tags: [louos, rule-versions, enums, dto, parameters, sqlite, eloquent]

# Dependency graph
requires:
  - phase: 06-classificacao-risco
    provides: "rule_versions + RuleVersionService (openDraft/publish 4-olhos; scopes vigente/naData/versao), enum RuleDomain/RuleVersionStatus, padrão de DTO readonly RiscoInput/RiscoResult, HasAuditoria"
  - phase: 04-georreferenciamento
    provides: "TerritoryResult (zona/via vêm daqui; status indisponivel quando pendente SEDUR)"
provides:
  - "RuleDomain estendido com 4 domínios LOUOS sensíveis (louos_quadro7/10/11/11a)"
  - "Enums ResultadoViabilidade (veredito consolidado HU-044) e Quadro10Permissao (HU-039)"
  - "3 tabelas tipadas tabulares (FK rule_version_id): louos_quadro7_faixas, louos_quadro10_permissoes, louos_quadro11_condicoes_via"
  - "Models + factories LouosQuadro7Faixa, LouosQuadro10Permissao, LouosQuadro11CondicaoVia"
  - "DTOs readonly EnquadramentoInput e EnquadramentoResult (contrato do motor)"
  - "Grupo de parâmetros louos (vagas.exigencia_por_grupo, sandbox.amostra_padrao) — catálogo 33"
affects: [05-02-seeds, 05-03-motor, 05-04-motor, 05-05-motor, 05-06-mantenedores, 05-sandbox, 07-consulta-previa, 08-solicitacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Quadros da LOUOS como domínios do rule_versions genérico (Fase 6) — tabela tipada por domínio referencia rule_version_id"
    - "DTO readonly de motor (dimensão por Quadro {status, dados, motivo, versao_regra} + consolidado) espelhando RiscoResult/TerritoryResult"
    - "Model com $table explícito quando a pluralização do Eloquent diverge do nome canônico da tabela"

key-files:
  created:
    - app/Enums/ResultadoViabilidade.php
    - app/Enums/Quadro10Permissao.php
    - app/Models/LouosQuadro7Faixa.php
    - app/Models/LouosQuadro10Permissao.php
    - app/Models/LouosQuadro11CondicaoVia.php
    - database/factories/LouosQuadro7FaixaFactory.php
    - database/factories/LouosQuadro10PermissaoFactory.php
    - database/factories/LouosQuadro11CondicaoViaFactory.php
    - database/migrations/2026_06_14_045747_create_louos_quadro7_faixas_table.php
    - database/migrations/2026_06_14_045748_create_louos_quadro10_permissoes_table.php
    - database/migrations/2026_06_14_045748_create_louos_quadro11_condicoes_via_table.php
    - app/Services/Louos/EnquadramentoInput.php
    - app/Services/Louos/EnquadramentoResult.php
  modified:
    - app/Enums/RuleDomain.php
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "Os 4 domínios LOUOS são isSensitive=true (publicação 4-olhos) — decidem viabilidade"
  - "Uma única tabela louos_quadro11_condicoes_via serve aos domínios louos_quadro11 E louos_quadro11a (o domínio da versão distingue 11 de 11A)"
  - "Models LouosQuadro10Permissao e LouosQuadro11CondicaoVia precisam de $table explícito (pluralização padrão gera nomes errados)"
  - "EnquadramentoResult armazena versoes como mapa próprio (privado), espelhando RiscoResult — não deriva das dimensões"

patterns-established:
  - "Quadro LOUOS = domínio do rule_versions + tabela tipada com FK rule_version_id constrained/cascadeOnDelete"
  - "Status de dimensão do motor LOUOS: identificado | nao_encontrado | indisponivel (degradação honesta)"

# Metrics
duration: 16min
completed: 2026-06-14
---

# Phase 5 Plan 01: Fundação do Motor LOUOS Summary

**RuleDomain estendido com os 4 Quadros da LOUOS (sensíveis, reusando rule_versions da Fase 6), 3 tabelas tipadas tabulares com FK rule_version_id, enums ResultadoViabilidade/Quadro10Permissao e os DTOs readonly EnquadramentoInput/EnquadramentoResult que fixam o contrato do motor.**

## Performance

- **Duration:** 16 min
- **Started:** 2026-06-14T04:52:15Z
- **Completed:** 2026-06-14T05:08:22Z
- **Tasks:** 3
- **Files modified:** 21 (16 criados, 5 modificados)

## Accomplishments

- `RuleDomain` ganhou `louos_quadro7/10/11/11a` (todos `isSensitive()===true`, publicação 4-olhos) **sem tocar** os domínios de risco da Fase 6; `RuleVersion`/`RuleVersionService` reusados, não recriados.
- 3 migrations TABULARES (sem PostGIS, rodam em SQLite) com `foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete()`, mais models (casts decimal/enum/array, `ruleVersion()`) e factories ligadas a `RuleVersion::factory()`.
- Enums `ResultadoViabilidade` (veredito consolidado HU-044) e `Quadro10Permissao` (permissão na zona HU-039).
- DTOs readonly `EnquadramentoInput`/`EnquadramentoResult` espelhando `RiscoResult`/`TerritoryResult` — cada Quadro é uma dimensão com status, e a degradação honesta (`indisponivel` → consolidado `pendente`) está no contrato.
- Grupo de parâmetros `louos` (vagas HU-042 + sandbox HU-143); catálogo 31 → 33 travado nos dois testes de seeder; fallback em `config/sile.php`.

## Task Commits

1. **Task 1: RuleDomain + enums ResultadoViabilidade/Quadro10Permissao** - `bbe9874` (feat) — TDD: RED→GREEN
2. **Task 2: Migrations + models + factories das 3 tabelas tipadas** - `ffce4d4` (feat) — TDD: RED→GREEN
3. **Task 3: DTOs + parâmetros (vagas, sandbox) + contagem nos dois seeders** - `b29333a` (feat) — TDD: RED→GREEN

## Contrato para os planos 05-02..05-09

### Mapeamento de HUs confirmado (fonte: arquivos oficiais das HUs)
- **HU-015 = Manter Quadro 7**, **HU-016 = Manter Quadro 10**, **HU-017 = Manter Quadro 11**, **HU-018 = Manter Quadro 11A**.
- Motor: **HU-038 = Quadro 7**, **HU-039 = Quadro 10**, **HU-040 = Quadro 11**, **HU-041 = Quadro 11A**.

### Domínios (RuleDomain)
`louos_quadro7`, `louos_quadro10`, `louos_quadro11`, `louos_quadro11a` — todos `isSensitive()===true`. A publicação versionada usa `RuleVersionService::openDraft($dominio, $version, $source, $createdBy)` e `publish($draft, $publishedBy)` (rejeita publicador === autor em domínio sensível — 4-olhos).

### Tabelas e colunas finais
- **`louos_quadro7_faixas`** (model `LouosQuadro7Faixa`): `rule_version_id` (FK), `cnae_code` (string 7, dígitos), `grupo`, `subgrupo?`, `area_min` (decimal:2, default 0), `area_max?` (decimal:2 — **null = sem limite superior**), `observacao?`. Index (`rule_version_id`,`cnae_code`).
- **`louos_quadro10_permissoes`** (model `LouosQuadro10Permissao`, `$table` explícito): `rule_version_id` (FK), `zona`, `grupo_uso`, `subgrupo?`, `permissao` (cast `Quadro10Permissao`), `condicionante_ref?`, `base_legal?`, `observacao?`. Index (`rule_version_id`,`zona`,`grupo_uso`).
- **`louos_quadro11_condicoes_via`** (model `LouosQuadro11CondicaoVia`, `$table` explícito): `rule_version_id` (FK — o **domínio da versão** distingue Quadro 11 de 11A), `classe_via`, `grupo_uso?`, `condicoes?` (cast `array`/jsonb), `base_legal?`, `observacao?`. Index (`rule_version_id`,`classe_via`).
- **Faixas não-sobrepostas** (Quadro 7) são validadas na camada de aplicação (mantenedor/seed do 05-02/05-06), não no banco.

### DTO `EnquadramentoInput` (entrada do motor)
Construtor (ordem): `float $area`, `string $cnaePrincipal`, `array $cnaesSecundarios = []`, `?TerritoryResult $territory = null`, `array $vagasDeclaradas = []`, `?CarbonInterface $data = null`, `array $versoesOverride = []`.
- `territory` é a fonte de zona/via (Fase 4). `versoesOverride` é o sandbox HU-143 (mapa `RuleDomain->value` → versão).
- Helper: `EnquadramentoInput::paraConsulta(float $area, string $cnae, ?TerritoryResult $territory = null)`.

### DTO `EnquadramentoResult` (saída do motor)
Construtor: `array $quadro7, array $quadro10, array $quadro11, array $quadro11a, array $consolidado, private array $versoes`.
- Constantes de status de dimensão: `STATUS_IDENTIFICADO='identificado'`, `STATUS_NAO_ENCONTRADO='nao_encontrado'`, `STATUS_INDISPONIVEL='indisponivel'`.
- Shape sugerido por dimensão: `quadro7 {status, grupo, subgrupo, motivo, versao_regra}`, `quadro10 {status, permissao, condicionante_ref, motivo, versao_regra}`, `quadro11/quadro11a {status, condicoes, motivo, versao_regra}`.
- `consolidado {resultado (value de ResultadoViabilidade), fundamentacao[], condicionantes[], motivo}`.
- Métodos: `versoes(): array<string,?string>` (chaves `quadro7/quadro10/quadro11/quadro11a`), `resultado(): string`, `pendente(): bool`, `toArray(): array` (snake_case, chaves `quadro7/quadro10/quadro11/quadro11a/consolidado/versoes`).
- **Anti-fachada (RN do motor):** sem zona real (`quadro10.status === STATUS_INDISPONIVEL`) o consolidado deve ser `pendente`, nunca `permitido`/`nao_permitido`.

### Parâmetros novos (grupo `louos`, catálogo agora 33)
- `louos.vagas.exigencia_por_grupo` — json, default `{}`, `['required','json']`, `typedValue()` → `[]` (HU-042; vazio = não parametrizado, motor não bloqueia).
- `louos.sandbox.amostra_padrao` — integer, default `50`, `['required','integer','min:1','max:1000']` (HU-143).
- Fallback em `config/sile.php` seção `louos` (`Settings::get('louos.*')`).

## Files Created/Modified
- `app/Enums/RuleDomain.php` - +4 domínios LOUOS (isSensitive), labels pt-BR
- `app/Enums/ResultadoViabilidade.php` - veredito consolidado (permitido/permitido_com_condicoes/nao_permitido/pendente)
- `app/Enums/Quadro10Permissao.php` - permitido/permitido_condicionado/proibido
- `app/Models/LouosQuadro7Faixa.php` `LouosQuadro10Permissao.php` `LouosQuadro11CondicaoVia.php` - tabelas tipadas com ruleVersion()
- `database/factories/LouosQuadro7FaixaFactory.php` `LouosQuadro10PermissaoFactory.php` `LouosQuadro11CondicaoViaFactory.php`
- `database/migrations/2026_06_14_045747...` / `...045748...` (x2) - 3 tabelas tabulares (SQLite)
- `app/Services/Louos/EnquadramentoInput.php` `EnquadramentoResult.php` - contrato readonly do motor
- `database/seeders/ParameterSeeder.php` + `config/sile.php` - grupo louos (vagas + sandbox)
- `tests/Unit/Louos/RuleDomainLouosTest.php` `EnquadramentoResultTest.php`, `tests/Feature/Louos/LouosQuadrosSchemaTest.php` - cobertura
- `tests/Feature/Seeders/ParameterSeederTest.php` `DatabaseSeederTest.php` - contagem 33 + grupo louos

## Decisions Made
- **isSensitive nos 4 Quadros:** decidem viabilidade locacional → publicação 4-olhos, como as dimensões de risco.
- **Tabela única para Quadro 11 e 11A:** evita duplicar esquema idêntico; o domínio da versão (`louos_quadro11` vs `louos_quadro11a`) faz a distinção.
- **`versoes` como mapa próprio no EnquadramentoResult** (espelha RiscoResult), em vez de derivar das dimensões (como TerritoryResult faz com `versao_camada`) — o motor 05-03+ preenche explicitamente.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `$table` explícito nos models de Quadro 10 e 11**
- **Found during:** Task 2 (schema test)
- **Issue:** A pluralização padrão do Eloquent gera `louos_quadro10_permissaos` e `louos_quadro11_condicao_vias`, divergindo dos nomes canônicos das tabelas exigidos pelo plano — inserts falhavam com "no such table".
- **Fix:** `protected $table = 'louos_quadro10_permissoes'` e `protected $table = 'louos_quadro11_condicoes_via'`.
- **Files modified:** app/Models/LouosQuadro10Permissao.php, app/Models/LouosQuadro11CondicaoVia.php
- **Verification:** LouosQuadrosSchemaTest verde (4/4).
- **Committed in:** ffce4d4 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Necessário para os nomes canônicos das tabelas exigidos pelo plano. Sem scope creep.

## Issues Encountered
- **Baseline real era 534, não 519:** o plano citava 519 (número do momento em que foi escrito), mas a suíte canônica (`--exclude-group postgis`) já estava em 534 antes deste plano. Sem regressão: a suíte fechou em **547** (534 baseline + 13 testes novos). Recomendo atualizar a referência de baseline para 547 nos próximos planos da fase.
- **`migrate:fresh --env=testing` adaptado:** não existe `.env.testing` e a config SQLite vive no `phpunit.xml` (só vale durante o PHPUnit). Rodar `--env=testing` cairia no `.env` de desenvolvimento e poderia apagar o banco real. Em vez disso, provei as migrations em SQLite com `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh` (todas as 7 migrations de regra `DONE`), e o teste `LouosQuadrosSchemaTest` (RefreshDatabase) migra as 3 tabelas novas a cada run.

## User Setup Required
None - nenhuma configuração de serviço externo necessária.

## Next Phase Readiness
- **05-02 (seeds do Quadro 7):** tabelas, domínios e `RuleVersionService` prontos; basta abrir rascunho `louos_quadro7`, popular `louos_quadro7_faixas` e publicar. Faixas não-sobrepostas validadas na carga.
- **05-03/04/05 (motor):** contrato `EnquadramentoInput`/`EnquadramentoResult` fixado; o motor preenche cada dimensão e o consolidado, respeitando a degradação honesta (zona indisponível → pendente).
- **05-06 (mantenedores HU-015..018):** CRUD sobre rascunhos + `publish` 4-olhos reusado.
- **Bloqueios herdados (pendente SEDUR, registrados em STATE.md):** zona urbanística (Quadro 10 degrada para análise) e atributo viário LOUOS / "Quadro 11"↔11B (Quadros 11/11A modelados, aplicam quando o atributo existir). Nada simulado.

---
*Phase: 05-motor-louos*
*Completed: 2026-06-14*
