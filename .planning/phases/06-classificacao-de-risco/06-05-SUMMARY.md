---
phase: 06-classificacao-de-risco
plan: 05
subsystem: backend
tags: [motor-risco, classificacao, encaminhamento, condicionante-pergunta, gatilhos, zeis, dto-readonly, auditoria, rn-002, sqlite]

# Dependency graph
requires:
  - phase: 06-01-fundacao-regras-versionadas
    provides: "RuleVersion + scopes vigente/naData (resolução de versão por domínio e por época — RN-005)"
  - phase: 06-02-classificacao-risco-municipal
    provides: "RiskClassification (FK rule_version_id, cnae_code, enum RiscoMunicipal baixo_a/baixo_b/alto — sem 'médio')"
  - phase: 06-03-risco-sanitario
    provides: "SanitaryRiskClassification (dimensão separada) + RiskCondicionante (regra_reclassificacao jsonb: resposta_gatilho/reclassifica_para/fundamento) + enum RiscoSanitario"
  - phase: 06-04-encaminhamento-gatilhos
    provides: "DTOs RiscoInput/RiscoResult, enum Fluxo (expresso/analise), RiskTrigger::ativos(), parâmetros risco.mapa_encaminhamento + risco.dimensao_tvl (HU-014)"
  - phase: 04-georreferenciamento
    provides: "TerritoryService/TerritoryResult — padrão espelhado (resolução de versão, status por dimensão, versoes(), auditoria por dimensão decisiva)"
  - phase: 01-identidade
    provides: "AuditService.log (RN-002) — log 'risco', event 'classificacao', rulesVersion"
provides:
  - "RiscoClassificationService::classify(RiscoInput): RiscoResult — o motor de classificação de risco (decisão de roteamento auditada)"
  - "Dimensões municipal × sanitária SEPARADAS, com versão de regras por dimensão (vigente ou da época)"
  - "Reclassificação por condicionante-pergunta (mecanismo DI): resposta == resposta_gatilho + reclassifica_para não nulo → nivel_final"
  - "Encaminhamento 100% parametrizado: dimensão decisiva (risco.dimensao_tvl) + mapa (risco.mapa_encaminhamento); nível ausente/CNAE sem regra/gatilho → analise (FA-02)"
  - "Gatilhos semi-expresso aplicados sobre o contexto recebido (RiskTrigger::ativos × gatilhosContexto) com motivo auditável — sem consultar território (wiring no EP07)"
  - "Auditoria da decisão com a versão de regras da dimensão decisiva (RN-002)"
affects: [06-06-mantenedores-publicacao, 06-07-golden-cases, 06-08-ui-risco, 07-consulta-previa]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Motor de risco espelha TerritoryService: DTO in/out, métodos privados por dimensão (classifyMunicipal/classifySanitario/resolveEncaminhamento/applyGatilhos/buildFundamentacao), resolução de versão (vigente/naData), auditoria uma vez por classificação"
    - "Regra é DADO, motor só APLICA: mapa e dimensão decisiva via Settings (sem hardcode), gatilhos via tabela (RiskTrigger::ativos), níveis via enum — HU-014"
    - "Degradação segura: o que não é expresso é análise (nível ausente no mapa, CNAE não classificado na dimensão decisiva, gatilho acionado) — nunca inventa nível"
    - "cnae_code normalizado para dígitos no motor (preg_replace /\\D/) — FK lógica com risk_classifications/risk_sanitary_classifications (precedente do import)"

key-files:
  created:
    - app/Services/Risco/RiscoClassificationService.php
    - tests/Feature/Risco/RiscoClassificationServiceTest.php
  modified: []

key-decisions:
  - "rules_version da auditoria = versão da DIMENSÃO DECISIVA (risco.dimensao_tvl), espelhando TerritoryService::identify (a decisão é registrada com a regra que a determinou)"
  - "Condicionante acionada com reclassifica_para NULO não reclassifica (mantém nivel_original) mas registra acionou=true na condicionante_pergunta — preserva o indeterminado sem inventar nível (RiskCondicionante docblock: null → análise)"
  - "respostasCondicionantes aceita chave por condicionante_id OU por pergunta (lookup id-first) — contrato flexível para o EP07/UI"
  - "Motivo do encaminhamento por gatilho = motivo do primeiro gatilho acionado (todos ficam em gatilhos_acionados[]); motivo sem gatilho descreve nível+dimensão decisiva"
  - "Verificação SQLite via 'php artisan test --compact --exclude-group postgis' (precedente 06-01..04); o motor é tabular e roda em :memory:"

patterns-established:
  - "RiscoResult.sanitario.condicionantes_perguntas[] enriquecido: {condicionante_id, pergunta, resposta, acionou, reclassifica_para, fundamento} — rastreabilidade da reclassificação para UI/auditoria"
  - "Encaminhamento e fundamentação derivam exclusivamente das dimensões + parâmetros + gatilhos — nenhuma regra de roteamento no código"

# Metrics
duration: 20min
completed: 2026-06-14
---

# Phase 6 Plan 05: Motor de Classificação de Risco Summary

**O coração da Fase 6: `RiscoClassificationService::classify(RiscoInput): RiscoResult`, espelhando o `TerritoryService`. Classifica um CNAE nas dimensões municipal (Decreto 32.636/2020) e sanitária (VISA) SEPARADAS, resolvendo a versão vigente ou da época; aplica a reclassificação por condicionante-pergunta (golden 1031-7/00: produto não artesanal → Alto); resolve o encaminhamento pela dimensão decisiva via mapa parametrizado (HU-014); aplica os gatilhos semi-expresso (incl. exceção ZEIS) que derrubam para análise mesmo em baixo risco; e audita a decisão com a versão de regras aplicada (RN-002). CNAE sem regra vigente nunca é decidido automaticamente — segue para análise (FA-02), nível nenhum é inventado.**

## Performance

- **Duration:** ~20 min
- **Tasks:** TDD RED → GREEN → pint (1 feature, 11 casos de teste)
- **Files created:** 2 | **modified:** 0

## Accomplishments
- `RiscoClassificationService` (≈349 linhas) com `classify()` público e 8 métodos privados espelhando a divisão do `TerritoryService`.
- Os 11 casos do plano cobrindo HU-047 a HU-051 (dimensões separadas, expresso/análise, reclassificação, gatilho, ZEIS, mapa parametrizável sem deploy, CNAE sem regra, auditoria, reprodução por época).
- Suíte SQLite: **495/495 verde** (484 baseline + 11 novos), sem regressão; `--filter=Risco` 36/36.

## Task Commits

Ciclo TDD com commits atômicos:

1. **RED — 11 testes do motor** - `e4b4b98` (test)
2. **GREEN — RiscoClassificationService** - `6060af7` (feat)

**Plan metadata:** este SUMMARY (docs).

## Contrato do motor (insumo do EP07, da UI 06-08 e dos golden cases 06-07)

### Assinatura
```
App\Services\Risco\RiscoClassificationService (injeta AuditService)

classify(RiscoInput $input): RiscoResult
```

- **Entrada** (`RiscoInput`, readonly — 06-04): `cnaeCode` (com ou sem máscara — o motor normaliza para dígitos), `respostasCondicionantes` (mapa `condicionante_id|pergunta → bool`), `gatilhosContexto` (`list<string>` de `TipoGatilho` ativos no contexto), `data` (`?CarbonInterface` — null = vigente; preenchida = reprodução por época).
- **Saída** (`RiscoResult`, readonly — 06-04): shape `toArray()` snake_case já documentado no 06-04; este plano define o CONTEÚDO de cada dimensão.

### Resolução de versão (espelha TerritoryService::resolveLayer)
Por domínio (`RiscoMunicipal`, `RiscoSanitario`): `data === null` → `RuleVersion::vigente($domain)->first()`; senão `RuleVersion::naData($domain, $data)->first()`. Toda lookup de classificação/condicionante é escopada por `rule_version_id` da versão resolvida (RN-005 — reprodução por época, provada pelo teste 11).

### Dimensão `municipal` (shape do RiscoResult)
`{status, nivel, nivel_label, condicionantes, versao_regras}` — `RiskClassification` por (rule_version_id, cnae_code). Ausente → `status='nao_classificado'`, `nivel=null`.

### Dimensão `sanitario` (SEPARADA)
`{status, nivel_original, nivel_final, reclassificado, condicionantes_perguntas[], versao_regras}` — `SanitaryRiskClassification` por (rule_version_id, cnae_code). Para cada `RiskCondicionante` do CNAE: se `resposta == regra_reclassificacao['resposta_gatilho']` **e** `reclassifica_para` não nulo → `nivel_final = reclassifica_para`, `reclassificado = true`. Cada `condicionantes_perguntas[]` carrega `{condicionante_id, pergunta, resposta, acionou, reclassifica_para, fundamento}` (rastreabilidade). Condicionante acionada com `reclassifica_para` nulo mantém o nível e marca `acionou=true` (indeterminado preservado, sem inventar nível).

### `encaminhamento`
`{fluxo, dimensao_decisiva, motivo, gatilhos_acionados[]}`:
- `dimensao_decisiva = Settings::get('risco.dimensao_tvl','municipal')`.
- nível decisivo = `municipal.nivel` ou `sanitario.nivel_final` (pós-reclassificação) conforme a dimensão decisiva.
- `fluxo = mapa[nivel_decisivo] ?? 'analise'`, com `mapa = Settings::get('risco.mapa_encaminhamento', [...])`.
- **Degradação para `analise`** (FA-02): dimensão decisiva `nao_classificado` OU nível ausente no mapa → `motivo = 'Classificação de risco não parametrizada para o CNAE'`.
- **Gatilho** (semi-expresso): qualquer `RiskTrigger::ativos()` cujo `codigo` ∈ `gatilhosContexto` força `fluxo='analise'` e entra em `gatilhos_acionados[] = {codigo, motivo}` — derruba expresso mesmo em baixo risco.

### `fundamentacao` (`list<string>`)
Municipal classificado → `'Decreto Municipal nº 32.636/2020'` + condicionantes gerais. Sanitário classificado → `'Classificação de risco sanitário (Vigilância Sanitária)'` + o `fundamento` de cada condicionante-pergunta efetivamente acionada.

### `versoes`
`{municipal, sanitario}` — `version` da RuleVersion consultada em cada dimensão (null quando não há versão resolvida).

## Como o contexto de gatilhos chega (contrato para o EP07)
O motor **não consulta o território**. O `gatilhosContexto` (`list<string>`) é preenchido pelo CHAMADOR (EP07) a partir de fatos já apurados: `zeis_especial` vem da restrição ZEIS identificada pelo `TerritoryService` (Fase 4, 234 features), `enquadramento_ausente` quando o CNAE não tem classificação vigente, `dados_do_processo` quando porte/área/condicionantes declarados exigem análise. O motor cruza esses códigos com `RiskTrigger::ativos()` (dado parametrizado) e aplica. Assim, ligar o território ao motor é trabalho do EP07 — aqui a regra é aplicada sobre o contexto recebido, sem simular território (sem fachada).

## Auditoria (RN-002 — espelha TerritoryService::identify)
Uma `Activity` por classificação: `log_name='risco'`, `event='classificacao'`, `description="Classificação de risco do CNAE {cnaeCode}"`, `result='sucesso'`, `rules_version` = versão da **dimensão decisiva**, `properties = {cnae, dimensao_decisiva, nivel_municipal, nivel_sanitario_final, fluxo, gatilhos_acionados, versoes}`.

## Files Created
- `app/Services/Risco/RiscoClassificationService.php` - O motor: classify() + classifyMunicipal/classifySanitario/resolveEncaminhamento/applyGatilhos/buildFundamentacao/resolveVersion + helpers de dimensão não classificada.
- `tests/Feature/Risco/RiscoClassificationServiceTest.php` - 11 casos (cada CA/RN das HUs), usando factories de RuleVersion/classificação e o RiskTriggerSeeder real para os gatilhos.

## Decisions Made
- **`rules_version` = versão da dimensão decisiva** na auditoria (não as duas): a decisão de roteamento é determinada por uma dimensão (HU-014 `risco.dimensao_tvl`); ambas as versões ficam em `properties.versoes`. Espelha o registro da versão consultada do TerritoryService.
- **`reclassifica_para` nulo não reclassifica** (mantém nível, marca `acionou=true`): a fonte VISA tem condicionantes que transferem competência sem nível-alvo (06-03); reclassificar para null inventaria ausência de risco. Preserva o caso indeterminado para a análise.
- **Lookup de resposta por id OU pergunta**: o `respostasCondicionantes` do `RiscoInput` é "id/pergunta → bool" (contrato 06-04); o motor tenta id primeiro, depois pergunta.
- **Mapa e dimensão lidos via Settings a cada classificação** (sem cachear no service): a HU-014 exige efeito sem deploy; o cache é responsabilidade do `Settings`/`Parameter` (invalida na gravação) — provado pelo teste 8.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Guarda para `reclassifica_para` nulo na reclassificação sanitária**
- **Found during:** GREEN (regra da condicionante-pergunta).
- **Issue:** O texto do plano descreve "resposta == resposta_gatilho → nivel_final = reclassifica_para". Aplicado cru, uma condicionante com `reclassifica_para: null` (caso real da VISA — 06-03) zeraria `nivel_final`.
- **Fix:** Só reclassifica quando `reclassifica_para` não é nulo; o acionamento é registrado em `condicionantes_perguntas[].acionou` para não perder o sinal (indeterminado → tratamento na análise).
- **Files modified:** app/Services/Risco/RiscoClassificationService.php
- **Verification:** teste 5 (golden 1031-7/00 → alto; resposta "não" mantém baixo) verde.
- **Committed in:** `6060af7`

**2. [Verificação] `--exclude-group postgis` em vez de `migrate:fresh --env=testing`**
- Mesma natureza e racional do 06-01..04 (sem `.env.testing`; o grupo postgis exige container). O motor é tabular e é provado pelo `RefreshDatabase` em SQLite `:memory:`.

**Enriquecimentos aditivos (dentro do contrato 06-04, sem scope creep):**
- `condicionantes_perguntas[]` ganhou `{condicionante_id, resposta, acionou, reclassifica_para}` além de `{pergunta, fundamento}` — rastreabilidade para UI/auditoria, dentro do campo já previsto.
- `motivo` do encaminhamento sem gatilho cita nível + dimensão decisiva (legibilidade).
- Teste 5 inclui o ramo negativo (resposta "não" não reclassifica) e o teste 8 prova antes/depois (default → análise; após gravar o Parameter → expresso) — evidência anti-fachada mais forte.

---

**Total deviations:** 1 missing-critical (guarda de nulo, exigência anti-fachada do dado real) + 1 de verificação (padrão da fase).
**Impact on plan:** Sem scope creep. Apenas os dois arquivos do `files_modified` do 06-05. Nada dos mantenedores/golden (06-06/07) nem da UI (06-08) foi tocado.

## Issues Encountered
- Nenhum. O contexto das waves anteriores (DTOs, enums, parâmetros, tabelas e seeders) estava completo; o motor é pura aplicação da regra sobre o dado versionado.

## User Setup Required
None - sem configuração de serviço externo. Sem dependência nova.

## Next Phase Readiness
- **06-06 (mantenedores/publicação):** o motor consome `risk_classifications`, `risk_sanitary_classifications`, `risk_condicionantes` e `risk_triggers`; o CRUD auditado (HasAuditoria) + publicação por quatro olhos (`RuleVersionService.publish`) altera o dado e o motor reflete sem mudança de código.
- **06-07 (golden cases):** o `RiscoResult::toArray()` é o contrato estável para os fixtures entrada→esperado (`#[DataProvider]`); o motor classifica sobre os seeders reais (RiscoMunicipal/RiscoSanitario/RiskTrigger).
- **06-08 (UI de risco):** consome o `toArray()` (municipal/sanitário separados, encaminhamento, fundamentação, versões).
- **EP07 (consulta prévia):** liga o `TerritoryService` ao `RiscoClassificationService` preenchendo `gatilhosContexto` (ex.: `zeis_especial` a partir da restrição ZEIS) — contrato documentado acima.
- Sem blockers introduzidos por este plano. Lista definitiva de gatilhos CNAE e prevalência da dimensão no TVL seguem pendentes SEDUR (tabela e parâmetro destravam — dado, não código).

---
*Phase: 06-classificacao-de-risco*
*Completed: 2026-06-14*
