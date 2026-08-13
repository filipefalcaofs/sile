---
phase: 06-classificacao-de-risco
plan: 09
subsystem: testing
tags: [comando-artisan, evidencia-ponta-a-ponta, verificacao-integral, motor-risco, seed-oficial, anti-fachada, postgis, fechamento-de-fase]

# Dependency graph
requires:
  - phase: 06-05-motor-risco
    provides: "RiscoClassificationService::classify(RiscoInput): RiscoResult — o motor REAL que o comando exercita"
  - phase: 06-04-encaminhamento-gatilhos
    provides: "DTOs RiscoInput/RiscoResult + enum Fluxo + RiskTrigger (gatilhos parametrizados, incl. zeis_especial)"
  - phase: 06-02-classificacao-risco-municipal
    provides: "RiscoMunicipalSeeder + CSV oficial Decreto 32.636/2020 (767/328/236=1.331) + enum RiscoMunicipal"
  - phase: 06-03-risco-sanitario
    provides: "RiscoSanitarioSeeder + RiskCondicionante (regra de reclassificação) + enum RiscoSanitario"
  - phase: 06-06-mantenedores-risco-condicionantes
    provides: "Consulta vigente auditada + publicação versionada 4-olhos + CRUD condicionantes (HU-019/052/053)"
  - phase: 06-07-golden-cases
    provides: "RiscoGoldenCaseTest (#[DataProvider]) + RiscoSeedDistributionTest (regressão de domínio)"
  - phase: 06-08-ui-risco
    provides: "Telas do console (consulta/publicação/condicionantes) — critério 5 navegável"
provides:
  - "Comando risco:classificar {cnae} (auto-descoberto) — evidência real de ponta a ponta do motor sobre o seed oficial, com relatório pt-BR fundamentado e exit codes"
  - "Verificação integral fresca da Fase 6: suíte SQLite 519/519 + grupo postgis 15/15 (executado) + pint + typecheck + build + migrate:fresh --seed no Postgres dev"
  - "Mapeamento dos 6 critérios de pronto do ROADMAP (Fase 6) a testes que passam (evidência colada)"
affects: [07-consulta-previa]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Comando de evidência espelha os comandos auditados (cnae:importar/redesim:importar): assinatura artisan, relatório pt-BR seccionado, exit codes — invalido=1, classificado=0, sem-regra=0 (análise, nunca erro)"
    - "Comando só APLICA o motor: normaliza o CNAE para dígitos, monta RiscoInput (respostas/gatilhos via opções) e chama RiscoClassificationService::classify — zero regra de roteamento no comando"
    - "Degradação honesta no relatório: CNAE sem regra imprime 'Não classificado (segue para análise)' e denominação '(subclasse não cadastrada)' — nunca inventa nível nem denominação"

key-files:
  created:
    - app/Console/Commands/RiscoClassificarCommand.php
    - tests/Feature/Risco/RiscoClassificarCommandTest.php
  modified: []

key-decisions:
  - "Validação de formato = exatamente 7 dígitos após remover máscara (subclasse CNAE DDDD-D/SS): 'abc' (0 dígitos) sai com erro (exit 1); 9999999 (7 dígitos, ausente do seed) é formato VÁLIDO e segue para análise (exit 0) — distingue erro de entrada de decisão real de negócio"
  - "Conclusão impressa em $this->line() (texto plano, capturado de forma determinística por expectsOutputToContain) em vez de depender de info/warn — robustez do teste de saída"
  - "Denominação resolvida por Cnae::where('code', ...)->value('description') com fallback '(subclasse não cadastrada)' — o motor não depende do Cnae; o comando só enriquece a exibição sem fabricar dado"
  - "Verificação integral roda os DOIS caminhos exigidos: SQLite (--exclude-group postgis) E o grupo postgis real (após geo:preparar-banco-de-testes) — o grupo postgis EXECUTA (15 testes, 2,7s), não é pulado"

patterns-established:
  - "Comando de evidência por fatia de motor: {motor}:classificar/{motor}:simular que exercita o serviço real sobre o seed oficial e imprime o resultado fundamentado — replicável pelo motor LOUOS (Fase 5)"

# Metrics
duration: ~11min
completed: 2026-06-14
---

# Phase 6 Plan 09: Fechamento e Verificação Integral da Classificação de Risco Summary

**O fechamento da Fase 6: o comando `risco:classificar {cnae}` (auto-descoberto, espelhando cnae:importar/redesim:importar) exercita o motor REAL (`RiscoClassificationService`) sobre o seed OFICIAL e imprime o resultado fundamentado pt-BR — risco municipal (Decreto 32.636/2020) e sanitário (VISA) separados, encaminhamento com dimensão decisiva/motivo/gatilhos, fundamentação legal e versões de regras —, com exit codes que distinguem formato inválido (1) de CNAE sem regra (0, segue para análise, nunca nível inventado). Acompanha a verificação integral FRESCA da fase: suíte SQLite 519/519, grupo postgis 15/15 (executado), pint/typecheck/build verdes, `migrate:fresh --seed` no Postgres dev com a distribuição oficial 767/328/236=1.331 confirmada, e os 6 critérios de pronto do ROADMAP mapeados a testes que passam.**

## Performance

- **Duration:** ~11 min
- **Started:** 2026-06-14T03:37:00Z
- **Completed:** 2026-06-14T03:48:00Z
- **Tasks:** 2 (comando TDD + verificação integral) + checkpoint humano
- **Files created:** 2 | **modified:** 0

## Accomplishments

- `RiscoClassificarCommand` (`risco:classificar {cnae} {--resposta=*} {--gatilho=*}`): evidência real de ponta a ponta — o motor processa dado oficial e o comando imprime a decisão fundamentada.
- `RiscoClassificarCommandTest` (4 casos, TDD RED→GREEN): baixo_a→expresso, gatilho ZEIS→análise, CNAE sem regra→análise (exit 0), formato inválido→erro (exit 1).
- Verificação integral fresca da Fase 6 (outputs colados abaixo), incluindo a prova de que o grupo postgis EXECUTA (não é pulado).
- Os 6 critérios de pronto do ROADMAP mapeados a testes verdes (tabela abaixo).

## Task Commits

1. **Task 1 (RED): teste do comando risco:classificar** - `cb44d33` (test)
2. **Task 1 (GREEN): comando risco:classificar** - `78f8b2d` (feat)

**Plan metadata:** este SUMMARY (docs).

## Resultado da verificação integral (evidência fresca — output lido por inteiro)

| Comando | Resultado |
|---|---|
| `php artisan test --compact --exclude-group postgis` | **519/519 passed** (2.568 asserções), 0 falhas, 0 erros — 18,4s |
| `php artisan geo:preparar-banco-de-testes` | `Banco sile_testing já existe.` + `Extensão postgis garantida em sile_testing.` |
| `php artisan test --compact --group postgis` | **15/15 passed** (85 asserções) — 2,7s (**EXECUTOU, não pulou**) |
| `php artisan test --compact --filter=Risco` | **60/60 passed** (325 asserções) |
| `vendor/bin/pint --dirty --format agent` | `{"tool":"pint","result":"passed"}` |
| `npm run typecheck` (tsc --noEmit) | passed (exit 0) |
| `npm run build` (vite) | `✓ built in 611ms` — chunks `risco-CkWl88TE.js` e `condicionantes-BQZHvizX.js` emitidos |
| `php artisan migrate:fresh --seed` (Postgres dev) | todas as migrations + 10 seeders **DONE**, sem erros |

Output bruto das suítes (reporter `--compact`):

```
--exclude-group postgis:  {"tool":"phpunit","result":"passed","tests":519,"passed":519,"assertions":2568,"duration_ms":18383}
--group postgis:          {"tool":"phpunit","result":"passed","tests":15,"passed":15,"assertions":85,"duration_ms":2698}
--filter=Risco:           {"tool":"phpunit","result":"passed","tests":60,"passed":60,"assertions":325,"duration_ms":2188}
```

> A linha `"tests":15 ... "duration_ms":2698` do grupo `postgis` é a prova de que o grupo **rodou contra o PostGIS real** (container `sile-pgsql` na 5433) — não houve skip. Total efetivo da fase: 519 (SQLite) + 15 (postgis) = **534 testes verdes** (515 do baseline 06-08 + 4 novos do comando; os 15 do postgis são da Fase 4).

### Distribuição oficial após `migrate:fresh --seed` (Postgres dev — consulta read-only)

```
versao_municipal=decreto-32636-2020
versao_sanitaria=visa-unificada-2026-04-30
municipal_total=1331   baixo_a=767   baixo_b=328   alto=236
sanitario_total=261
condicionantes_total=67
gatilhos_total=3   gatilhos_ativos=3
```

A distribuição municipal **767/328/236 = 1.331** bate exatamente com o Decreto 32.636/2020; dimensão sanitária (261) e condicionantes-pergunta (67) carregadas da planilha VISA; 3 gatilhos parametrizados, todos ativos.

## Evidência real do motor (ponta a ponta sobre o seed oficial)

### (1) `php artisan risco:classificar 0111-3/01` — Baixo Risco A → EXPRESSO

```
Classificação de risco — CNAE 0111-3/01
Denominação: Cultivo de arroz

Risco municipal (Decreto 32.636/2020):
  Nível: Baixo Risco A
  Condicionantes gerais: Desde que seja escritório da empresa; Desde que não esteja em imóvel residencial (casa, apartamento), galpão, terreno, lote, box, quiosque, container,; Desde que a área utilizada não ultrapasse 1.250m² (mil duzentos e cinquenta metros quadrados).
  Versão de regras: decreto-32636-2020

Risco sanitário (Vigilância Sanitária):
  Não classificado na versão vigente.
  Versão de regras: visa-unificada-2026-04-30

Encaminhamento:
  Fluxo: Fluxo expresso
  Dimensão decisiva: municipal
  Motivo: Nível baixo_a (municipal) elegível ao fluxo expresso
  Gatilhos acionados: nenhum
  Conclusão: elegível ao fluxo expresso.

Fundamentação legal:
  - Decreto Municipal nº 32.636/2020
  - Desde que seja escritório da empresa
  - Desde que não esteja em imóvel residencial (casa, apartamento), galpão, terreno, lote, box, quiosque, container,
  - Desde que a área utilizada não ultrapasse 1.250m² (mil duzentos e cinquenta metros quadrados).
```

### (2) `php artisan risco:classificar 1011-2/01` — Alto Risco → ANÁLISE

```
Classificação de risco — CNAE 1011-2/01
Denominação: Frigorífico - abate de bovinos

Risco municipal (Decreto 32.636/2020):
  Nível: Alto Risco
  Condicionantes gerais: ATIVIDADE INDUSTRIAL CONFORME LEI N° 9.148/2016 (LOUOS)
  Versão de regras: decreto-32636-2020

Risco sanitário (Vigilância Sanitária):
  Não classificado na versão vigente.
  Versão de regras: visa-unificada-2026-04-30

Encaminhamento:
  Fluxo: Análise técnica
  Dimensão decisiva: municipal
  Motivo: Nível alto (municipal) encaminhado para análise técnica
  Gatilhos acionados: nenhum
  Conclusão: segue para análise técnica.

Fundamentação legal:
  - Decreto Municipal nº 32.636/2020
  - ATIVIDADE INDUSTRIAL CONFORME LEI N° 9.148/2016 (LOUOS)
```

### (3) `php artisan risco:classificar 9999-9/99` — Sem regra vigente → ANÁLISE (exit 0, anti-fachada)

```
Classificação de risco — CNAE 9999-9/99
Denominação: (subclasse não cadastrada)

Risco municipal (Decreto 32.636/2020):
  Não classificado na versão vigente (segue para análise).
  Versão de regras: decreto-32636-2020

Risco sanitário (Vigilância Sanitária):
  Não classificado na versão vigente.
  Versão de regras: visa-unificada-2026-04-30

Encaminhamento:
  Fluxo: Análise técnica
  Dimensão decisiva: municipal
  Motivo: Classificação de risco não parametrizada para o CNAE
  Gatilhos acionados: nenhum
  Conclusão: segue para análise técnica.

Fundamentação legal:
  - Sem fundamentação automática (CNAE não classificado) — a análise técnica define o enquadramento.
```

### (Bônus — gatilho parametrizado) `php artisan risco:classificar 0111-3/01 --gatilho=zeis_especial` — Baixo Risco A, mas → ANÁLISE

O mesmo CNAE de Baixo Risco A do caso (1) é **derrubado do expresso para análise** pelo gatilho ZEIS, com o motivo auditável vindo da tabela parametrizada de gatilhos (prova de que o gatilho é dado, não código, e que nada é simulado):

```
Encaminhamento:
  Fluxo: Análise técnica
  Dimensão decisiva: municipal
  Motivo: Imóvel em Zona Especial de Interesse Social (ZEIS) exige análise técnica específica conforme a LOUOS (Lei nº 9.148/2016) — o fluxo expresso não se aplica.
  Gatilhos acionados:
    - zeis_especial: Imóvel em Zona Especial de Interesse Social (ZEIS) exige análise técnica específica conforme a LOUOS (Lei nº 9.148/2016) — o fluxo expresso não se aplica.
  Conclusão: segue para análise técnica.
```

## Os 6 critérios de pronto do ROADMAP (Fase 6) → evidência (todos cobertos)

| # | Critério (ROADMAP) | Evidência (teste/arquivo que comprova) | Status |
|---|---|---|---|
| 1 | Mantém condicionantes e classificação de risco como **dados versionados**, com seed oficial do Decreto (767/328/236) | `RiscoSeedDistributionTest::test_distribuicao_oficial_do_decreto` (767/328/236=1.331) + `test_cobertura_e_unica_por_cnae_na_versao_vigente`; `RiscoMunicipalSeederTest::test_seeder_publica_versao_vigente_e_carrega_o_decreto`; mantenedores em `RiscoConsultaTest`/`RiscoCondicionanteMaintenanceTest`. Confirmado no Postgres dev (767/328/236). | ✅ |
| 2 | Classifica qualquer CNAE mantendo **municipal × sanitário separados** | `RiscoClassificationServiceTest::test_classifica_municipal_e_sanitario_como_dimensoes_separadas`; `RiscoSanitarioSeederTest::test_dimensoes_municipal_e_sanitaria_sao_separadas`; evidência do comando (painéis separados, versões distintas por dimensão). | ✅ |
| 3 | Condicionante-pergunta **reclassifica** o risco conforme a resposta (mecanismo "DI") | `RiscoClassificationServiceTest::test_condicionante_pergunta_reclassifica_sanitario` (baixo→alto) + golden `sanitario-reclassifica-1031` em `RiscoGoldenCaseTest`. | ✅ |
| 4 | Baixo/médio→**expresso**; alto e **gatilhos**→análise, incl. exceção por localização (ZEIS) | `RiscoClassificationServiceTest::test_baixo_risco_municipal_encaminha_para_expresso`, `test_baixo_b_tambem_encaminha_para_expresso_por_default`, `test_alto_risco_encaminha_para_analise`, `test_gatilho_ativo_derruba_baixo_risco_para_analise`, `test_excecao_zeis_especial_encaminha_para_analise`, `test_mapa_de_encaminhamento_parametrizavel_sem_deploy`; golden `municipal-alto-analise`/`gatilho-zeis-analise`; evidência real do comando (casos 1, 2 e ZEIS). | ✅ |
| 5 | Tabela de risco vigente **consultável e atualizável com auditoria** | `RiscoConsultaTest::test_analista_consulta_tabela_de_risco_vigente`, `test_consulta_e_auditada`, `test_atualizar_publica_nova_versao_preservando_a_anterior`, `test_publicacao_pelo_mesmo_autor_e_bloqueada_por_quatro_olhos`, `test_sem_permissao_consultar_risco_recebe_403_auditado`; UI 06-08 (`->component('gestao/risco/index')` validado em disco). | ✅ |
| 6 | **Golden cases** (incl. reclassificação) na suíte de regressão de domínio | `RiscoGoldenCaseTest::test_golden_case` (#[DataProvider], 5 fixtures sobre o seed real) + `RiscoSeedDistributionTest` (distribuição como âncora). | ✅ |

## Entregue × pendências SEDUR (parametrizadas — não bloqueiam, não simuladas)

### Entregue (operando de ponta a ponta sobre dado oficial real)

- Fundação de regras versionadas (`rule_versions`, publicação por 4-olhos, scopes vigente/naData) — herdável pela Fase 5.
- Classificação **municipal** (Decreto 32.636/2020): 1.331 CNAEs (767/328/236), versionada e auditada.
- Dimensão **sanitária** (VISA) separada: 261 classificações + 67 condicionantes-pergunta com regra de reclassificação.
- **Motor** `RiscoClassificationService`: 2 dimensões separadas + reclassificação por condicionante + encaminhamento parametrizável + gatilhos/ZEIS + auditoria RN-002.
- **Mantenedores + UI** do console (consulta vigente, publicação versionada 4-olhos, CRUD de condicionantes) com permissões `consultar-risco`/`manter-risco`.
- **Gatilhos** parametrizados (3: `enquadramento_ausente`, `zeis_especial`, `dados_do_processo`), administráveis por `ativo`.
- **Golden cases** (5 fixtures) + regressão da distribuição + **comando** de evidência real.

### Pendências SEDUR — já PARAMETRIZADAS (destravam por dado/parâmetro, sem deploy)

- **Mapa de encaminhamento "médio" ↔ baixo_b:** o Decreto municipal tem só baixo_a/baixo_b/alto — "médio" é diretriz operacional (e nível da VISA), não classificação do Decreto. O roteamento é o parâmetro `risco.mapa_encaminhamento` (HU-014; default `{baixo_a: expresso, baixo_b: expresso, alto: analise}`); provado mutável sem deploy por `test_mapa_de_encaminhamento_parametrizavel_sem_deploy`. Quando a SEDUR confirmar a correspondência operacional do "médio", muda-se o parâmetro.
- **Lista completa de gatilhos CNAE (semi-expresso):** 3 conhecidos seedados; a tabela `risk_triggers` é administrável — a SEDUR adiciona os demais como dado, sem código.
- **Dimensão decisiva no TVL (`risco.dimensao_tvl`):** default `municipal`; é parâmetro até a SEDUR confirmar qual dimensão prevalece no TVL.

Nenhuma dessas pendências bloqueia a fase: o motor decide com os defaults derivados da lei/decreto e degrada com segurança (CNAE sem regra/gatilho → análise), nunca simulando nível.

## Checkpoint humano (Task 3 — gate blocking)

Reservado ao orquestrador/usuário. Roteiro de verificação manual entregue e pronto:

1. `composer run dev` (já em execução no ambiente).
2. Terminal: `php artisan risco:classificar 0111-3/01` (→ Baixo Risco A, EXPRESSO, Decreto 32.636/2020) e `php artisan risco:classificar 0111-3/01 --gatilho=zeis_especial` (→ ANÁLISE). Outputs reais colados acima.
3. Console (`/gestao/login`, admin@sile.dev/password) → "Classificação de risco": tabela vigente com resumo 767/328/236 + publicação 4-olhos; "Condicionantes": CRUD.
4. `php artisan test --compact` (sem regressão) — coberto pelas suítes acima.
5. Pendências SEDUR (médio↔baixo_b, gatilhos, dimensao_tvl) parametrizadas, nada simulado.

Status do código: **verde e pronto** (534 testes, pint/typecheck/build, seed dev). A aprovação visual humana fecha a fase.

## Decisions Made

- **Formato inválido = exatamente 7 dígitos:** distingue erro de entrada (exit 1) de CNAE válido-mas-sem-regra (exit 0, análise) — coerente com a degradação segura do motor (FA-02), sem confundir o operador.
- **Conclusão em texto plano (`$this->line`)** para captura determinística da saída no teste, mantendo info/header só como cosmético.
- **Denominação com fallback honesto** (`(subclasse não cadastrada)`): o comando enriquece a exibição sem fabricar dado; o motor independe do `Cnae`.

## Deviations from Plan

### Enriquecimentos aditivos (dentro do escopo do plano)

- **4º caso de teste e 4ª evidência (CNAE sem regra → análise, exit 0; gatilho ZEIS):** o plano nomeia 3 testes; foram adicionados `test_cnae_sem_regra_segue_para_analise_sem_erro` e a evidência ZEIS para reforçar a prova anti-fachada (decisão real ≠ erro; gatilho parametrizado derruba o expresso). Apenas os 2 arquivos do `files_modified` do 06-09 (comando + teste); nenhum código de produção do motor/seed/UI tocado.

### Nota de verificação

- A verificação rodou os **dois** caminhos exigidos pelo fechamento: SQLite (`--exclude-group postgis`, 519/519) **e** o grupo `postgis` real (`geo:preparar-banco-de-testes` + `--group postgis`, 15/15 executados). O `migrate:fresh --seed` foi no **Postgres dev** (não em `.env.testing`), confirmando o seed completo de ponta a ponta com a distribuição oficial.

---

**Total deviations:** 0 correções (nenhum bug/bloqueio); 1 enriquecimento aditivo (4º caso/evidência).
**Impact on plan:** Sem scope creep. O comando é pura aplicação do motor; a verificação não revelou regressão alguma — fase íntegra.

## Issues Encountered

- Nenhum. O motor (06-05), o seed oficial (06-02/03), os gatilhos (06-04), os mantenedores (06-06), os golden cases (06-07) e a UI (06-08) estavam completos; o comando só os exercita e a verificação confirmou a integridade.

## User Setup Required

None — sem configuração de serviço externo. Sem dependência nova. Banco PostGIS de dev/testes já provisionado (`sile-pgsql` na 5433; `sile_testing` via `geo:preparar-banco-de-testes`).

## Next Phase Readiness

- **Fase 7 (Consulta Prévia):** consome território (Fase 4) + motor LOUOS (Fase 5) + motor de risco (Fase 6). O contrato do `RiscoResult::toArray()` e o wiring de `gatilhosContexto` (ex.: `zeis_especial` a partir da restrição ZEIS do `TerritoryService`) estão documentados (06-05) e provados pelo comando — ligar território→motor é trabalho do EP07.
- **Fase 5 (Motor LOUOS):** herda a infra `rule_versions`, o padrão de golden case (#[DataProvider]) e o padrão de comando de evidência (`{motor}:classificar/simular`).
- **Pendências SEDUR** inalteradas e todas parametrizadas (médio↔baixo_b via `risco.mapa_encaminhamento`, gatilhos via `risk_triggers`, prevalência via `risco.dimensao_tvl`) — destravam por dado/parâmetro, sem deploy.
- Atualização de `STATE.md`/`ROADMAP.md` (status da Fase 6 → COMPLETE) é responsabilidade do orquestrador de fase.

---
*Phase: 06-classificacao-de-risco*
*Completed: 2026-06-14*
