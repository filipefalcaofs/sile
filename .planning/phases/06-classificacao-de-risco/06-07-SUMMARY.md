---
phase: 06-classificacao-de-risco
plan: 07
subsystem: testing
tags: [golden-cases, regressao-de-dominio, dataprovider, phpunit, fixtures-json, motor-risco, seed-oficial, sqlite, anti-fachada]

# Dependency graph
requires:
  - phase: 06-02-classificacao-risco-municipal
    provides: "RiscoMunicipalSeeder + CSV oficial do Decreto 32.636/2020 (distribuição 767/328/236=1.331) + enum RiscoMunicipal"
  - phase: 06-03-risco-sanitario
    provides: "RiscoSanitarioSeeder + RiskCondicionante (golden 1031-7/00 reclassifica baixo→alto) + RiscoSanitarioSeeder"
  - phase: 06-04-encaminhamento-gatilhos
    provides: "RiscoInput/RiscoResult (DTOs readonly) + RiskTriggerSeeder (zeis_especial) + mapa/dimensão parametrizados (defaults)"
  - phase: 06-05-motor-risco
    provides: "RiscoClassificationService::classify(RiscoInput): RiscoResult — o motor REAL exercido pelos golden cases"
provides:
  - "Suíte de golden cases entrada→esperado (#[DataProvider]) que roda o motor REAL sobre o seed OFICIAL — proteção de regressão de domínio (critério 6 do ROADMAP)"
  - "5 fixtures JSON declarativos: municipal baixo_a→expresso, alto→análise, reclassificação sanitária 1031-7/00, gatilho ZEIS→análise, CNAE sem regra→análise"
  - "RiscoSeedDistributionTest: distribuição oficial 767/328/236=1.331 e unicidade por CNAE travadas como regressão"
  - "Padrão de golden case reutilizável pelo motor LOUOS (Fase 5)"
affects: [05-motor-louos, 06-08-ui-risco, 07-consulta-previa]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Golden case = fixture JSON {nome, descricao, input, esperado} executado por #[DataProvider]; o nome do caso vira a chave do data set (saída legível) e a mensagem da divergência"
    - "Harness compara apenas as chaves presentes em `esperado` (asserção parcial declarativa) — cada fixture escolhe o que ancora, via match() chave→caminho no RiscoResult"
    - "Resolução estável da condicionante: o harness carrega as condicionantes do CNAE na versão sanitária vigente e injeta a própria resposta_gatilho do seed (sem depender de id auto-increment)"
    - "Caminho dos fixtures resolvido por dirname(__DIR__, 2) e não por base_path() — o data provider roda antes do boot da app (sem container)"
    - "Regressão de domínio sobre o SEED REAL (não fixture sintético do dado): RiscoMunicipalSeeder/RiscoSanitarioSeeder/RiskTriggerSeeder no setUp"

key-files:
  created:
    - tests/Fixtures/golden/risco/municipal-baixo-a-expresso.json
    - tests/Fixtures/golden/risco/municipal-alto-analise.json
    - tests/Fixtures/golden/risco/sanitario-reclassifica-1031.json
    - tests/Fixtures/golden/risco/gatilho-zeis-analise.json
    - tests/Fixtures/golden/risco/cnae-sem-regra-analise.json
    - tests/Feature/Risco/RiscoGoldenCaseTest.php
    - tests/Feature/Risco/RiscoSeedDistributionTest.php
  modified: []

key-decisions:
  - "Asserção parcial por chave de `esperado` (não o RiscoResult inteiro): cada golden case ancora só o que importa, reduzindo acoplamento a campos não relevantes (ex.: a sanitária de um caso municipal)"
  - "1031-7/00 ancora fluxo=expresso mesmo com reclassificação sanitária para alto: a dimensão decisiva default é municipal (baixo_a) — cristaliza a SEPARAÇÃO de dimensões (RN-009), comportamento real do motor"
  - "Mapa/dimensão NÃO são parametrizados no setUp (ParameterSeeder ausente): os golden cases batem contra os DEFAULTS do motor (baixo_a/baixo_b→expresso, alto→análise) — o caso parametrizável já é coberto pelo RiscoClassificationServiceTest"
  - "Verificação SQLite via 'php artisan test --compact --exclude-group postgis' (precedente 06-01..05); o motor e o seed são tabulares e rodam em :memory:"

patterns-established:
  - "Fixture golden: {nome, descricao, input:{cnae_code, respostas_condicionantes, [responder_condicionantes_gatilho], gatilhos_contexto, data}, esperado:{<subset de chaves>}}"
  - "Chaves de `esperado` suportadas: municipal_status, municipal_nivel, sanitario_status, sanitario_nivel_original, sanitario_nivel_final, sanitario_reclassificado, fluxo, dimensao_decisiva, gatilhos_acionados (lista de códigos, comparada ordenada)"

# Metrics
duration: 6min
completed: 2026-06-14
---

# Phase 6 Plan 07: Golden Cases de Classificação de Risco Summary

**A suíte de golden cases entrada→esperado (critério 6 do ROADMAP) que protege a Fase 6 contra regressão de domínio: 5 fixtures JSON declarativos executados pelo motor REAL (`RiscoClassificationService`, 06-05) sobre o SEED OFICIAL (Decreto 32.636/2020 + planilha VISA + gatilhos), via `#[DataProvider]` do PHPUnit 12. Cobre as situações decisivas — baixo_a→expresso, alto→análise, reclassificação por condicionante-pergunta (1031-7/00 baixo→alto), gatilho ZEIS→análise e CNAE sem regra→análise — e trava a distribuição oficial 767/328/236=1.331 como regressão. Tabular: roda em SQLite. Padrão reutilizável pelo motor LOUOS (Fase 5).**

## Performance

- **Duration:** ~6 min
- **Started:** 2026-06-14T02:59:02Z
- **Completed:** 2026-06-14T03:04:52Z
- **Tasks:** 3 (fixtures + harness #[DataProvider] + distribuição)
- **Files created:** 7 | **modified:** 0

## Task Commits

1. **Task 1: 5 fixtures JSON golden** - `347ca69` (test)
2. **Task 2: RiscoGoldenCaseTest com #[DataProvider]** - `e6a7a2f` (test)
3. **Task 3: RiscoSeedDistributionTest (767/328/236)** - `2cb9a16` (test)

**Plan metadata:** este SUMMARY (docs).

## Resultado da verificação (evidência fresca)

- `--filter=RiscoGoldenCaseTest`: **5/5** (27 asserções) — um caso por fixture, contra o seed real.
- `--filter=RiscoSeedDistributionTest`: **2/2** (8 asserções) — distribuição + unicidade.
- `--filter=Risco --exclude-group postgis`: **44/44**.
- Suíte completa `--exclude-group postgis`: **503/503** (2.453 asserções), zero regressão (496 pré-existentes + 7 novos).
- `vendor/bin/pint --dirty --format agent`: passed.
- **Prova anti-fachada do harness:** ao adulterar um `esperado` (fluxo expresso→analise), o teste falhou com `Golden case '...': divergência em 'fluxo'. Esperado "analise", obtido "expresso"` — o harness assere de verdade; fixture revertido em seguida.

## Shape final do fixture JSON

```json
{
  "nome": "Baixo Risco A municipal segue para o fluxo expresso",
  "descricao": "Contexto legal/funcional do caso (documentação).",
  "input": {
    "cnae_code": "0111301",
    "respostas_condicionantes": {},
    "responder_condicionantes_gatilho": false,
    "gatilhos_contexto": [],
    "data": null
  },
  "esperado": {
    "municipal_status": "classificado",
    "municipal_nivel": "baixo_a",
    "sanitario_reclassificado": false,
    "fluxo": "expresso",
    "dimensao_decisiva": "municipal",
    "gatilhos_acionados": []
  }
}
```

- **`input`** vira `RiscoInput`: `cnae_code` (o motor normaliza máscara), `respostas_condicionantes` (mapa literal id/pergunta→bool), `gatilhos_contexto` (lista de `TipoGatilho`), `data` (null=vigente; ISO=reprodução por época).
- **`esperado`** é um SUBCONJUNTO de chaves; o harness asserta só as presentes (`match()` chave→caminho no `RiscoResult`). Chave desconhecida lança erro explícito (typo não passa silencioso). `gatilhos_acionados` compara a lista de `codigo` ordenada.

## Como o harness resolve a chave da condicionante (1031-7/00)

O `condicionante_id` é auto-increment do seed — instável para fixar no JSON. Quando o caso traz `"responder_condicionantes_gatilho": true`, o harness:

1. normaliza o `cnae_code` para dígitos e resolve a versão sanitária **vigente** (`RuleVersion::vigente(RiscoSanitario)`);
2. carrega as `RiskCondicionante` daquele CNAE nessa versão;
3. injeta, para cada uma, a **própria `resposta_gatilho` do seed** como resposta (`respostas[$id] = $regra['resposta_gatilho']`).

Assim a reclassificação dispara de forma determinística contra o dado real, sem hardcode de id nem do texto da pergunta — se a regra do seed mudar, o caso continua coerente com a fonte (e o `nivel_final` esperado é que vira a âncora de regressão).

## Lista de golden cases (entrada → resultado real esperado)

| Fixture | CNAE (real) | Entrada | Resultado real esperado |
|---|---|---|---|
| `municipal-baixo-a-expresso` | 0111-3/01 Cultivo de arroz (`0111301`) | sem respostas/gatilhos | municipal classificado/`baixo_a`; sanitário não reclassificado; **fluxo expresso**; decisiva municipal; sem gatilhos |
| `municipal-alto-analise` | 1011-2/01 Frigorífico – abate de bovinos (`1011201`) | sem respostas/gatilhos | municipal classificado/`alto`; **fluxo análise**; decisiva municipal; sem gatilhos |
| `sanitario-reclassifica-1031` | 1031-7/00 Conservas de frutas (`1031700`) | `responder_condicionantes_gatilho` | municipal `baixo_a`; sanitário `baixo`→**`alto` reclassificado**; decisiva municipal ⇒ **fluxo expresso** (dimensões separadas) |
| `gatilho-zeis-analise` | 0111-3/02 Cultivo de milho (`0111302`) | `gatilhos_contexto:["zeis_especial"]` | municipal `baixo_a`; **fluxo análise** (gatilho derruba o expresso); `gatilhos_acionados:["zeis_especial"]` |
| `cnae-sem-regra-analise` | inexistente (`9999999`) | sem respostas/gatilhos | municipal **`nao_classificado`**/nível null; **fluxo análise** (FA-02); sem gatilhos |

## Decisões de domínio cristalizadas

- **Separação de dimensões (RN-009) é observável:** o caso 1031-7/00 reclassifica o sanitário para `alto`, mas o encaminhamento segue `expresso` porque a dimensão decisiva default é a municipal (`baixo_a`). É o comportamento real do motor — golden case impede que uma futura mudança acople indevidamente sanitário→fluxo sem decisão de parâmetro.
- **Degradação segura sem inventar nível:** CNAE 9999999 fica `nao_classificado` e vai para análise; nenhum nível é fabricado.
- **Gatilho semi-expresso prevalece:** ZEIS derruba `baixo_a` (que iria ao expresso) para análise, com o motivo auditável do `RiskTriggerSeeder`.
- **Distribuição oficial é âncora:** 767 `baixo_a` + 328 `baixo_b` + 236 `alto` = 1.331, com cobertura única por CNAE na versão vigente — mudança no CSV do Decreto quebra o teste e força reconferência.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Caminho do fixture por `dirname(__DIR__, 2)` em vez de `base_path()`**
- **Found during:** Task 2 (escrita do data provider).
- **Issue:** O exemplo do plano usa `glob(base_path('tests/Fixtures/golden/risco/*.json'))`. O `#[DataProvider]` é estático e roda ANTES do boot da app (TestCase::setUp/createApplication), então `base_path()` não tem container e quebraria a coleta dos testes.
- **Fix:** Resolver o caminho por `dirname(__DIR__, 2).'/Fixtures/golden/risco/*.json'` — sem dependência de container, mantendo `glob(` (critério de aceite).
- **Files modified:** tests/Feature/Risco/RiscoGoldenCaseTest.php
- **Verification:** 5/5 golden verdes.
- **Committed in:** `e6a7a2f`

**2. [Verificação] `--exclude-group postgis` em vez de `migrate:fresh --env=testing`**
- Mesma natureza e racional do 06-01..05 (sem `.env.testing`; o grupo postgis exige container). Golden cases e distribuição são tabulares e provados pelo `RefreshDatabase` em SQLite `:memory:`.

**Enriquecimentos aditivos (dentro do escopo do plano):**
- Campo `descricao` em cada fixture (documentação do contexto legal/funcional — não afeta o motor).
- Asserção PARCIAL por chave de `esperado` (cada caso escolhe suas âncoras) em vez de comparar o `RiscoResult` inteiro — menos acoplamento, mensagem de divergência por chave.
- Caso 1031-7/00 ancora também `fluxo=expresso` (além do reclassificado) para cristalizar a separação de dimensões.

---

**Total deviations:** 1 blocking (caminho do fixture) + 1 de verificação (padrão da fase).
**Impact on plan:** Sem scope creep. Apenas os 7 arquivos do `files_modified` do 06-07 (5 fixtures + 2 testes); nenhum código de produção (motor/seeder/controller/rotas do 06-06) tocado. Nenhum golden case revelou bug no motor — todos os esperados refletem o comportamento real do 06-05 sobre o seed oficial.

## Issues Encountered
- **`git checkout` de revert não aplicou na primeira tentativa** (provável colisão de lock com a sessão paralela do 06-06): o fixture adulterado para a prova anti-fachada foi revertido via edição direta e reconfirmado (working tree limpo + golden 5/5).

## User Setup Required
None — sem configuração de serviço externo. Sem dependência nova.

## Next Phase Readiness
- **Fase 5 (Motor LOUOS):** herda o padrão de golden case (#[DataProvider] + fixtures JSON entrada→esperado sobre o seed real) — basta um harness análogo apontando para o `LouosEngine` e seus quadros versionados.
- **06-08 (UI de risco):** o `RiscoResult::toArray()` permanece o contrato estável; os golden cases ancoram o que a UI exibe.
- **EP07 (consulta prévia):** ao ligar `TerritoryService`→motor (preenchendo `gatilhos_contexto`), o caso ZEIS já documenta o efeito esperado ponta a ponta.
- Sem blockers introduzidos. Pendências SEDUR inalteradas (lista definitiva de gatilhos e prevalência da dimensão no TVL seguem como dado parametrizado).

---
*Phase: 06-classificacao-de-risco*
*Completed: 2026-06-14*
