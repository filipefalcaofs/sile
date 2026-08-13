---
phase: 09-fluxo-expresso
plan: 12
subsystem: expresso
tags: [hu-073, hu-074, hu-075, hu-076, hu-077, hu-078, hu-134, fechamento, seeds-dev, zona-ficticia, golden, smoke, evidencia, comando, anti-fachada, verificacao-integral, expresso]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "FluxoExpressoService::decide (motor real, idempotente) + DecisionResult"
  - phase: 09-fluxo-expresso
    plan: "06"
    provides: "Gatilho protocolar→listener AvaliarFluxoExpresso→DecidirFluxoExpressoJob→decide + harness $fakeExpressoDecisionJob"
  - phase: 09-fluxo-expresso
    plan: "07/08/09"
    provides: "Efeitos do ResultadoEmitido: notificação HU-077 + Regin HU-104 (bloqueado) + SEFAZ HU-110 (bloqueado, só deferida)"
  - phase: 09-fluxo-expresso
    plan: "11"
    provides: "Retaguarda do resultado expresso (lista + detalhe da ViabilityDecision sob consultar-solicitacoes)"
  - phase: 08-solicitacao-viabilidade
    plan: "(seeds/golden/smoke)"
    provides: "Padrão de seeds dev (SolicitacaoDevSeeder), comando de evidência e golden/smoke"
  - phase: 04-georreferenciamento
    plan: "(geo)"
    provides: "GeoJsonLayerImporter + TerritoryService (zona via geo_features) + PostgisTestCase"
provides:
  - "ExpressoDecidirCommand (expresso:decidir {solicitacao}) — evidência real do motor (status/desfecho/TVL/fundamentação)"
  - "ZonaFicticiaDevSeeder — zona ZCN-1 fictícia SÓ dev/teste (gate de ambiente + driver-aware) que torna um deferimento NAVEGÁVEL sobre a LÓGICA REAL"
  - "ExpressoDevSeeder — exemplos navegáveis: um deferimento (zona fictícia + TVL) e um em_analise honesto (sem zona)"
  - "ExpressoGoldenCaseTest (#[DataProvider] + fixtures) + ExpressoSmokeTest (cadeia real) — regressão dos três caminhos + degradação honesta"
  - "ExpressoSeedPostgisTest — prova @group postgis do deferimento navegável sobre a zona real"
  - "Link de navegação no console para os resultados do fluxo expresso (follow-up do 09-11)"
affects: [10, 13]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Seed de demonstração sem fachada: dado fictício (a feição de zona) + LÓGICA REAL (decisão), com GATE DE AMBIENTE (só local/testing) e driver-aware (geometria/decisão só pgsql) — em produção é no-op, a degradação honesta permanece"
    - "Zona fictícia usa um CÓDIGO real do Quadro 10 (ZCN-1) para o motor deferir de verdade; a proveniência fictícia vive na versão/origem/propriedades, não no código (a GeoJsonLayerImporter fecha a camada pendente_fonte e a fictícia vira vigente)"
    - "Smoke da cadeia automática REAL: $fakeExpressoDecisionJob=false exercita protocolar→evento→listener→job→decide→efeitos num único teste; anti-fachada provado por assertNotDispatched(ResultadoEmitido) sem zona"
    - "Golden cases data-driven (#[DataProvider] + fixtures JSON) sobre o motor real: cada caso declara cenário→esperado e trava a regressão de domínio pelo nome"

key-files:
  created:
    - app/Console/Commands/ExpressoDecidirCommand.php
    - database/seeders/ZonaFicticiaDevSeeder.php
    - database/seeders/ExpressoDevSeeder.php
    - tests/Feature/Expresso/ExpressoDecidirCommandTest.php
    - tests/Feature/Expresso/ExpressoGoldenCaseTest.php
    - tests/Feature/Expresso/ExpressoSmokeTest.php
    - tests/Feature/Seeders/ExpressoSeedPostgisTest.php
    - tests/Fixtures/golden/expresso/deferida-zona-permitido.json
    - tests/Fixtures/golden/expresso/deferida-zona-condicionado.json
    - tests/Fixtures/golden/expresso/indeferida-zona-proibido.json
    - tests/Fixtures/golden/expresso/em-analise-sem-zona.json
    - tests/Fixtures/golden/expresso/em-analise-alto-risco.json
  modified:
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Seeders/DatabaseSeederTest.php
    - resources/js/layouts/gestao-layout.tsx

key-decisions:
  - "Zona fictícia carrega o código real ZCN-1 (Quadro 10 → nR1 permitido) para o motor DEFERIR de verdade; o fictício é a geometria/proveniência (dado), nunca a regra (entrega-funcional). Adicionar uma regra de Quadro 10 fictícia seria fachada — rejeitado."
  - "Gate de ambiente (local/testing) nos DOIS seeders + driver-aware (pgsql): produção é no-op (degradação honesta em_analise); SQLite pula geometria e decisão (espelha a Fase 8 08-16); a prova do deferimento sobre a zona real é @group postgis."
  - "ExpressoDevSeeder decide EXPLICITAMENTE em pgsql (fila redis é async, sem worker no db:seed) — determinístico; idempotente com o gatilho quando há worker (re-check sob lock)."
  - "Smoke usa $fakeExpressoDecisionJob=false para exercitar a cadeia automática real de ponta a ponta (a after-commit roda na suíte, como provado no 09-06); o caso sem zona usa Event::fake([ResultadoEmitido]) + assertNotDispatched (anti-fachada)."
  - "DatabaseSeederTest (SQLite) afere os exemplos como protocolados (decisão pulada sem PostGIS) + idempotência; a decisão/TVL sobre a zona real é provada no ExpressoSeedPostgisTest."

patterns-established:
  - "Demonstração navegável de um deferimento sem fachada = dado fictício gated por ambiente + lógica 100% real; produção mantém a degradação honesta até a base oficial (muda a carga, não o comportamento)."

# Metrics
duration: ~75min
completed: 2026-06-14
---

# Phase 9 Plan 12: Fechamento do Fluxo Expresso — seeds dev, evidência, golden/smoke e verificação integral

**A Fase 9 fecha com o core value DEMONSTRÁVEL sem fachada. A `ZonaFicticiaDevSeeder` carrega (SÓ dev/teste — gate de ambiente + driver-aware) uma feição de zona com o código REAL `ZCN-1` (que o Quadro 10 conhece) sobre um polígono do Centro de Salvador; a `ExpressoDevSeeder` protocola e DECIDE pelos serviços reais um exemplo de DEFERIMENTO (sobre a zona fictícia → TVL) e um de EM ANÁLISE honesto (na Pituba, sem zona). O fictício é a geometria/proveniência (dado), nunca a regra (a LÓGICA é 100% real — entrega-funcional); em produção ambos os seeders são no-op e a degradação honesta (`em_analise` sem a zona oficial) permanece. O comando `expresso:decidir {solicitacao}` dá EVIDÊNCIA REAL do motor (status/desfecho/TVL/fundamentação). Golden (#[DataProvider] + fixtures) e smoke (cadeia automática REAL, com o worker não-fakeado) travam a regressão dos três caminhos (deferida/em_analise/indeferida) e os efeitos (notificação HU-077, Regin/SEFAZ pendência auditada, anti-fachada com `assertNotDispatched`). Verificação integral FRESCA verde: pint limpo, suíte SQLite 931/931 (4747 asserções), @group postgis 22/22 (131), tsc e build verdes; evidência real em dev: `expresso:decidir 8` → DEFERIDA + TVL-2026-000001, `expresso:decidir 9` → EM ANÁLISE. ZERO dependência nova; parâmetros mantidos em 56 / permissões em 19.**

## Performance
- **Duration:** ~75 min
- **Tasks:** 2 auto (comando+seeds; golden+smoke) + 1 checkpoint humano (smoke navegável)
- **Files:** 12 criados + 3 modificados — ZERO dependência nova

## O que ficou pronto

| Entrega | Arquivo | Prova |
|---|---|---|
| Comando de evidência `expresso:decidir` | `app/Console/Commands/ExpressoDecidirCommand.php` | `ExpressoDecidirCommandTest` 4/4 + evidência dev real |
| Zona fictícia (dev/teste, gated) | `database/seeders/ZonaFicticiaDevSeeder.php` | `ExpressoSeedPostgisTest` (zona vigente ZCN-1, 1 feição) |
| Exemplos navegáveis (deferida + em_analise) | `database/seeders/ExpressoDevSeeder.php` | `DatabaseSeederTest` (SQLite) + `ExpressoSeedPostgisTest` (pgsql) |
| Golden dos três caminhos | `tests/Feature/Expresso/ExpressoGoldenCaseTest.php` + 5 fixtures | 5/5 (deferida/condicionado/indeferida/sem-zona/alto-risco) |
| Smoke da cadeia automática real | `tests/Feature/Expresso/ExpressoSmokeTest.php` | 3/3 (efeitos + anti-fachada) |
| Link de navegação no console | `resources/js/layouts/gestao-layout.tsx` | tsc + build verdes |

## Verificação integral FRESCA (evidência colada)

- **`vendor/bin/pint --test --format agent`** → `passed` (sem pendências).
- **Suíte SQLite** (`php artisan test --compact --exclude-group=postgis`) → **931 testes, 931 passaram (4747 asserções)**. Baseline 918 (09-11) + 13 novos (comando 4, seeder +1, golden 5, smoke 3).
- **Suíte @group postgis** (`POSTGIS_TESTS_REQUIRED=true php artisan test --compact --group=postgis`) → **22 testes, 22 passaram (131 asserções)**. Baseline 21 + 1 (`ExpressoSeedPostgisTest`), com `sile-pgsql` healthy.
- **`npx tsc --noEmit`** e **`npm run build`** → verdes.
- **Auditoria síncrona** (`log_name expresso`, `event decisao`): 2 (1 `deferida` + 1 `analise`) — HU-078, independe do evento.

### Evidência do comando (dev pgsql, após `php artisan migrate` + `db:seed`)

```
$ php artisan expresso:decidir 8        # solicitação sobre a zona fictícia
Decisão do fluxo expresso
Solicitação #8
Número de protocolo: VIA-2026-000007
Empresa: MAGAZINE LUIZA S/A
Atividade principal: 4712-1/00 — Comércio varejista ... minimercados ...
Resultado: DEFERIDA
Desfecho: DEFERIDA
Número TVL: TVL-2026-000001
Fundamentação legal:
 - Lei nº 9.148/2016 (LOUOS) — Quadro 7
 - Quadro 10 da Lei nº 9.148/2016
 - Lei nº 9.148/2016 (LOUOS) — Quadro 10
 - Decreto Municipal nº 32.636/2020
 - Classificação de risco sanitário (Vigilância Sanitária)

$ php artisan expresso:decidir 9        # solicitação fora da zona (Pituba)
Resultado: EM ANÁLISE
Nenhuma decisão vinculante emitida — segue para análise técnica.
```

## Roteiro do SMOKE NAVEGÁVEL (checkpoint humano — `composer dev`)

O ambiente dev já está preparado: `php artisan migrate` aplicou as tabelas da Fase 9 (`viability_decisions`, `tvl_sequences`, colunas BAP) e `php artisan db:seed` populou a zona fictícia + os exemplos. Com um worker de fila ativo (`composer dev` roda `queue:listen`), os efeitos do `ResultadoEmitido` (notificação/Regin/SEFAZ) também processam.

1. **Deferimento navegável (dev)**: logar no console (`admin@sile.dev`), abrir **Resultados do fluxo expresso** (novo item no menu, gate `consultar-solicitacoes`) → a solicitação **VIA-2026-000007** aparece **DEFERIDA** com **TVL-2026-000001**, decisão por CNAE, fundamentação legal e versões de regra; a transmissão **Regin** e **SEFAZ** aparece como **PENDENTE** (canal bloqueado — Fase 13), nunca "enviado". Conferir a notificação por e-mail (sem anexo de TVL) em `Sistema > E-mails`. Mobile (375px) + dark mode.
2. **Em análise honesto (sem zona — CRÍTICO)**: a solicitação **VIA-2026-000008** (Pituba) está **em análise** (NÃO deferida/indeferida), SEM `ViabilityDecision` e SEM e-mail de resultado — a degradação honesta visível (não aparece na lista de resultados, que só mostra decididas).
3. **(Opcional) BAP dormente**: `php artisan expresso:indeferir-sem-bap` → no-op (zero), provando a dormência (nada entra em `aguardando_bap` até o Regin/Fase 13).

## Mapa CA → teste (fechamento integral, provado)

| HU / RN | Teste / evidência |
|---|---|
| HU-073/074/075 — três caminhos reais (deferida/em_analise/indeferida) | `ExpressoSmokeTest` + `ExpressoGoldenCaseTest` |
| HU-076 RN-007 — TVL no deferimento | `ExpressoSmokeTest` (deferida) + `ExpressoSeedPostgisTest` + `expresso:decidir 8` (TVL-2026-000001) |
| HU-077 — notificação ao deferir (sem anexo) | `ExpressoSmokeTest` (`Notification::assertSentTo`) + roteiro smoke |
| HU-078 — auditoria síncrona consultável | `log_name expresso`/`decisao` = 2 (>0) + UI de resultado |
| HU-104/110 — pendência Regin/SEFAZ honesta (SEFAZ ignora indeferida) | `ExpressoSmokeTest` (`integracoes` `bloqueado`/`ignorado`) |
| HU-134 — dormente (no-op) | `expresso:indeferir-sem-bap` (no-op) — 09-10 |
| Anti-fachada — sem zona → em_analise sem evento | `ExpressoSmokeTest` + `ExpressoGoldenCaseTest` (`assertNotDispatched`) |

## Bloqueios / pendências que PERMANECEM (sem fachada — registrados, não simulados)

- **Transmissão Regin (HU-104) e SEFAZ (HU-110) → Fase 13**: contratos prontos e bloqueados (`Unavailable*`); os listeners auditam a pendência (`integracoes` `bloqueado`). A Fase 13 liga trocando o binding.
- **HU-134 ativa (indeferir por prazo BAP) → Fase 13**: rotina dormente pronta (no-op); depende do Regin alimentar `bap_due_at`.
- **TVL PDF (HU-132) → Fase 10**: o número TVL é interno agora; o PDF sai da mesma fonte (`ViabilityDecision`).
- **Zona oficial (Quadro 10/SEDUR — SIGIS/CA 2000)**: sem ela a maioria cai honestamente em `em_analise`. A zona fictícia é SÓ dev/teste; produção liga sozinha quando a base oficial entrar (muda a carga, não a lógica).
- **HU-137 (feriados) para o prazo BAP em dias úteis**: seam `BusinessDeadlineCalculator` pronto; horas-calendário até lá.
- **Canais plenos de notificação (WhatsApp/in-app) → EP11**.
- **Política do "médio risco" e numeração oficial do TVL → SEDUR** (parametrizados, substituíveis sem deploy).

## Deviations from Plan

- **[Ajuste mínimo documentado]** Link de navegação no console para `resultados-expresso` adicionado no `gestao-layout` (follow-up de UX do 09-11): o plano não previa o arquivo, mas o escopo do fechamento o pede; mudança puramente visual, validada por tsc/build.
- **[Decisão de teste — não no plano]** `ExpressoDecidirCommandTest` (4 casos) criado: TDD do comando (o plano só pedia `--help` na aceitação), espelhando o padrão de testes de comando da fase.
- **[Driver-awareness]** O plano pedia a decisão/zona no `DatabaseSeederTest`; como SQLite não tem PostGIS, a prova do deferimento+TVL sobre a zona real ficou no `ExpressoSeedPostgisTest` (@group postgis) e o `DatabaseSeederTest` (SQLite) afere os exemplos como protocolados — espelha a decisão da Fase 8 (08-16) de não simular geometria em SQLite.

## Issues Encountered

- **Scope `vigente` da camada não filtra por status** (só `valid_to` nulo): a `GeoLayerSeeder` cria a zona como `pendente_fonte` com `valid_to` nulo. A `ZonaFicticiaDevSeeder` usa a `GeoJsonLayerImporter` (que via `openVersion` FECHA a camada anterior) para que a fictícia vire a única vigente — senão `vigente(Zona)->first()` poderia devolver a pendente e o motor cairia em `em_analise`.
- **Fila dev é redis (async)**: por isso a `ExpressoDevSeeder` decide EXPLICITAMENTE em pgsql (sem depender de um worker durante o `db:seed`), idempotente com o gatilho quando há worker.

## Next Phase Readiness

- **Fase 10** (Análise Técnica): recebe os `em_analise`; o TVL PDF (HU-132) sai da `ViabilityDecision` já gravada; a ficha pré-analisada reusa o `SolicitacaoViabilityResolver`.
- **Fase 13** (Integrações): liga Regin/SEFAZ/BAP trocando 3 bindings — os listeners e a auditoria de pendência já existem.
- **Critérios do ROADMAP**: 1, 2, 5 e 6 validados com evidência fresca; 3 (Regin/SEFAZ) e 4 (BAP) com a DECISÃO/auditoria reais e a transmissão/ativação BLOQUEADAS → Fase 13 (registrado, não simulado).

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
