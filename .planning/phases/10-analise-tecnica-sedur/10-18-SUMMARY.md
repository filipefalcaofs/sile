---
phase: 10-analise-tecnica-sedur
plan: 18
subsystem: testing
tags: [seeds-dev, analise-tecnica, analise-decidir, golden, smoke, postgis, precedentes, malha-fina, pendencia, tvl, anti-fachada, fechamento-fase]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur
    plan: "04"
    provides: "Sector/StandardText models + SectorSeeder/StandardText alvo dos seeds"
  - phase: 10-analise-tecnica-sedur
    plan: "08"
    provides: "PreAnaliseService + listener PreAnalisarProcesso (ficha rev 1)"
  - phase: 10-analise-tecnica-sedur
    plan: "09"
    provides: "AnalysisRecordService (autosave/finalizar/divergências)"
  - phase: 10-analise-tecnica-sedur
    plan: "10"
    provides: "AnaliseTecnicaDecisionService (decide → ViabilityDecision flow analise_tecnica + TVL)"
  - phase: 10-analise-tecnica-sedur
    plan: "06"
    provides: "PrecedentService + PostgisPrecedentRepository (HU-142)"
  - phase: 09-fluxo-expresso
    plan: "12"
    provides: "ExpressoDevSeeder + ZonaFicticiaDevSeeder + comando expresso:decidir (padrões espelhados)"
provides:
  - "SectorSeeder (setor de triagem + analista/gestor dev vinculados) + StandardTextSeeder (textos-padrão por categoria)"
  - "AnaliseDevSeeder (processos em cada estágio via serviços reais — driver-aware pgsql)"
  - "Comando analise:decidir {solicitacao} {--finalizar} (evidência real da decisão humana)"
  - "AnaliseSmokeTest + AnaliseGoldenCaseTest + fixtures + AnalisePrecedentesPostgisTest + AnaliseSeedPostgisTest + AnaliseDecidirCommandTest"
  - "Verificação integral fresca do fechamento da Fase 10 (1161/1161 incl. postgis)"
affects: [11-pendencias-comunicacao, 13-integracoes, 15-relatorios]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Seeder driver-aware (igual ExpressoDevSeeder): catálogos sempre; estágios da análise só em pgsql (decide() reexecuta os motores territoriais), no-op honesto em SQLite"
    - "Golden da decisão humana (#[DataProvider] + fixtures JSON) sobre AnaliseTecnicaDecisionService::decide — espelha os golden das Fases 5-9"
    - "Smoke do fluxo humano dirige a cadeia real (decide→listener→ficha→distribuir/assumir→finalizar com divergência→decidir+TVL) com FakeSpatialRepository em SQLite"
    - "Comando de evidência analise:decidir contraparte do expresso:decidir (status/desfecho/TVL/parecer; degradação honesta exit 0)"

key-files:
  created:
    - database/seeders/SectorSeeder.php
    - database/seeders/StandardTextSeeder.php
    - database/seeders/AnaliseDevSeeder.php
    - app/Console/Commands/AnaliseDecidirCommand.php
    - tests/Feature/Analise/AnaliseDecidirCommandTest.php
    - tests/Feature/Analise/AnaliseSmokeTest.php
    - tests/Feature/Analise/AnaliseGoldenCaseTest.php
    - tests/Feature/Analise/AnalisePrecedentesPostgisTest.php
    - tests/Feature/Seeders/AnaliseSeedPostgisTest.php
    - tests/Fixtures/golden/analise/*.json
  modified:
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "AnaliseDevSeeder driver-aware (espelha ExpressoDevSeeder): os estágios só são montados em pgsql porque a transição protocolada→em_analise reexecuta os motores territoriais (PostGIS). Em SQLite é no-op honesto; a cadeia real é provada em AnaliseSeedPostgisTest. DatabaseSeederTest (SQLite) cobre os catálogos (setor/textos/usuários) + 67/24 + idempotência"
  - "ROTEAMENTO AO SETOR é pendência SEDUR (não inventada): o FluxoExpressoService deixa sector_id nulo ao encaminhar. No DEV o seed coloca os processos na caixa de TRIAGEM (forceFill sector_id) para destravar a navegação; em PRODUÇÃO o gestor atribui o setor/analista manualmente via distribuir/caixa até a regra oficial entrar"
  - "Usuários de gestão dev (analista@sile.dev / gestor@sile.dev, ambos password) criados pelo SectorSeeder SÓ em dev/teste, com termo LGPD aceito — tornam caixa/ficha/decisão navegáveis"
  - "Setor padrão e textos-padrão criados SEMPRE (catálogo inicial, idempotente) — o setor inicial evita distribuição bloqueada em produção; a SEDUR mantém pela UI"

patterns-established:
  - "Fechamento de fase com seed dev de LÓGICA REAL + comando de evidência + golden/smoke + verificação integral fresca — padrão consolidado das Fases 8/9/10"

# Metrics
duration: ~75min
completed: 2026-06-15
---

# Phase 10 Plan 18: Fechamento da Análise Técnica SEDUR — seeds dev, analise:decidir, golden/smoke e verificação integral — Summary

**O fechamento da Fase 10: seeds de desenvolvimento que montam processos em cada estágio da análise técnica pela LÓGICA REAL (em análise/distribuído, deferido pelo analista com TVL, em pendência, malha fina sobre deferido), o comando de evidência `analise:decidir` (contraparte humana do `expresso:decidir`), os golden cases (#[DataProvider]) da decisão humana, o smoke do fluxo humano ponta a ponta (encaminhar→ficha pré-analisada→distribuir/assumir→finalizar com divergência→deferir+TVL; pendência ida-e-volta; malha fina sobre deferido; degradação FA-01) e os precedentes @group postgis (ST_Intersects real via PrecedentService). Verificação integral FRESCA: pint limpo, suíte 1161/1161 (5982 asserções) incluindo @group postgis com POSTGIS_TESTS_REQUIRED=true, tsc e build verdes; evidência real do comando: `analise:decidir 7 --finalizar` → DEFERIDA + TVL-2026-000003. Parâmetros 67 / permissões 24 confirmados. ZERO dependência nova.**

## Performance
- **Duração:** ~75 min
- **Tasks:** 2 de implementação (4 commits atômicos) + 1 checkpoint humano (smoke navegável)
- **Files:** 10 criados + 2 modificados — ZERO dependência nova

## O que foi entregue

### Task 1 — Seeds dev + comando analise:decidir
- **`SectorSeeder`** (catálogo + dev): cria o setor "Análise Locacional" (sempre, idempotente) e, SÓ em dev/teste, os usuários `analista@sile.dev` e `gestor@sile.dev` (papéis analista/gestor, termo LGPD aceito) vinculados ao setor — destravando caixa/distribuição/ficha navegáveis.
- **`StandardTextSeeder`** (HU-085): biblioteca de textos-padrão do parecer por categoria (deferimento/indeferimento/condicionante/pendência), em pt-BR, versão 1, idempotente (substituível pela SEDUR sem deploy).
- **`AnaliseDevSeeder`** (driver-aware, dev/teste + pgsql): monta 3 processos de exemplo pela cadeia REAL — (1) **em análise** distribuído ao analista com a ficha preenchida (pronta para `analise:decidir --finalizar`), (2) **deferido pelo analista** (ficha finalizada → `AnaliseTecnicaDecisionService::decide` → ViabilityDecision flow `analise_tecnica` + TVL) com **malha fina** encaminhada sobre o deferido, e (3) **em pendência** (PendenciaService::abrir). Dados fictícios, lógica real.
- **`analise:decidir {solicitacao} {--finalizar}`**: conclui o processo a partir da ficha pelo `AnaliseTecnicaDecisionService` e imprime status/desfecho/TVL/parecer; `--finalizar` finaliza a ficha em rascunho antes. Degradação honesta: rascunho sem `--finalizar` e processo fora de análise saem exit 0; só inexistente sai exit 1.
- **`DatabaseSeederTest`** (SQLite): setor + analista/gestor vinculados + 4 textos-padrão + idempotência + **67 parâmetros / 24 permissões**. **`AnaliseSeedPostgisTest`** (@group postgis): os 3 estágios reais + idempotência.

### Task 2 — Golden, smoke e precedentes postgis
- **`AnaliseSmokeTest`** (SQLite + FakeSpatialRepository): dirige o fluxo HUMANO de ponta a ponta pelos serviços reais — encaminhar (semi-expresso, risco alto) → ficha rev 1 pré-analisada pelo listener → distribuir/assumir → finalizar com **divergência analista×motor** gravada → **DEFERIR** (flow `analise_tecnica` + TVL; auditoria síncrona; **Regin/SEFAZ `bloqueado`** auditado → Fase 13); **INDEFERIR** (sem TVL, SEFAZ `ignorado`); **pendência** ida-e-volta (em_analise↔em_pendencia, requerente responde pelo portal); **malha fina** sobre um deferido (flag + referral, status inalterado); **degradação FA-01** (sem zona → ficha manual, o humano decide e defere com fundamentação própria).
- **`AnaliseGoldenCaseTest`** (#[DataProvider] + 3 fixtures JSON): todas deferidas → deferida+TVL; uma indeferida → indeferida (sem TVL); **deferir sem zona pelo humano** (engine_available=false) → deferida com a fundamentação da ficha.
- **`AnalisePrecedentesPostgisTest`** (@group postgis): o painel de precedentes via `PrecedentService::forRecord` sobre o `PostgisPrecedentRepository` REAL — decisões do imóvel por **ST_Intersects** (ordenadas por data, sem CPF/LGPD) + estatística do CNAE na zona; degradação honesta sem zona.

## Verificação integral (evidência FRESCA)
- `vendor/bin/pint --test --format agent` → **passed** (sem pendências).
- `POSTGIS_TESTS_REQUIRED=true php artisan test --compact` → **1161/1161 (5982 asserções)** — SQLite + @group postgis com o container PostGIS de pé (obrigatório, sem skip). Baseline da fase era 1142; +19 testes novos deste plano.
- `npx tsc --noEmit` → **verde** (exit 0). `npm run build` → **verde** (vite build OK).
- **Evidência do comando** (banco `sile_testing` semeado, sem tocar o dev):
  ```
  $ php artisan analise:decidir 7 --finalizar
  Decisão técnica da análise
  Solicitação #7
  Número de protocolo: VIA-2026-000006
  Empresa: MAGAZINE LUIZA S/A
  Atividade principal: 4712-1/00 — Comércio varejista ... minimercados ...
  Resultado: DEFERIDA
  Desfecho: DEFERIDA
  Analista responsável: Analista SILE (#3)
  Número TVL: TVL-2026-000003
  Parecer do analista:
   Parecer técnico favorável: atividade compatível com o local, com fundamentação na LOUOS (Lei nº 9.148/2016).
  ```
- **Estado semeado** (sile_testing): auditoria `analise` = 13 (>0, ações síncronas); decisões `analise_tecnica` = 2; em malha fina = 1; em pendência = 1; setores = 1; textos-padrão = 4.
- A pendência **Regin/SEFAZ `bloqueado`** (anti-fachada → Fase 13) é provada pelo `AnaliseSmokeTest` (fila sync na suíte); no seed CLI os listeners do `ResultadoEmitido` são despachados para a fila assíncrona do dev (comportamento de produção — after-commit).

## Mapa CA → evidência (fechamento)
| HU / RN | Evidência |
|---|---|
| HU-079/080/081/140 — encaminhar→pré-análise→distribuir→assumir | AnaliseSmokeTest + AnaliseSeedPostgisTest |
| HU-135/140 — ficha pré-analisada + divergência | AnaliseSmokeTest (divergência gravada) + AnaliseGoldenCaseTest |
| HU-085/086/087/088/089 — decidir/encerrar | AnaliseGoldenCaseTest + AnaliseDecidirCommandTest + `analise:decidir` real |
| HU-132 — TVL (deferimento) | AnaliseSmokeTest + evidência `analise:decidir` (TVL-2026-000003) |
| HU-083/084 — pendência ida-e-volta | AnaliseSmokeTest (em_analise↔em_pendencia) |
| HU-136 — malha fina sobre deferido | AnaliseSmokeTest + AnaliseSeedPostgisTest |
| HU-142 — precedentes reais | AnalisePrecedentesPostgisTest (ST_Intersects via PrecedentService) |
| Anti-fachada — Regin/SEFAZ pendência honesta | AnaliseSmokeTest (auditoria 'bloqueado'/'ignorado') |

## Checkpoint humano — SMOKE NAVEGÁVEL (pendente de aprovação visual)
A verificação automatizada cobre a lógica e o contrato; a renderização visual no navegador NÃO foi exercida por mim (instrução do usuário: registrar o roteiro, não abrir o browser). Roteiro para aprovação humana (`composer dev`, logado como `analista@sile.dev`/`gestor@sile.dev` ou `admin@sile.dev`, todos `password`), após `php artisan migrate:fresh --seed`:
1. **FILA** (`/gestao/processos/fila`): processos ordenados por prazo com semáforo de SLA; abas Meus/Setor.
2. **FICHA** (`/gestao/processos/{id}/ficha`): abrir o processo em análise → ficha pré-analisada (sugestão por CNAE + gatilhos); preencher/divergir com autosave; precedentes (ou "sem precedentes") + mini-mapa; diff de revisão; **finalizar**.
3. **DECIDIR**: deferir → status deferida + ViabilityDecision (flow analise_tecnica); **emitir TVL** → baixar o PDF por URL assinada (confirmar que NÃO vai ao cidadão); conferir na trilha Regin/SEFAZ `bloqueado` (Fase 13).
4. **PENDÊNCIA**: abrir pendência → em_pendencia + e-mail; logar como o cidadão dono no portal → responder → volta a em_analise.
5. **MALHA FINA**: encaminhar um deferido à malha fina (motivo) → flag ligada, status inalterado; lote pela consulta.
6. **CONSULTA** (`/gestao/processos`): filtros (analista/categoria/status) + busca global Cmd+K + exportar CSV; mobile (375px) + dark mode.

## Deviations from Plan
1. **[Rule 2 — Missing Critical] `AnaliseSeedPostgisTest` adicionado.** O plano lista os estágios da análise na asserção do `DatabaseSeederTest`, mas a cadeia até `em_analise` reexecuta os motores territoriais (PostGIS) — só roda em pgsql (igual ao `ExpressoDevSeeder`, cujo `decide()` é guardado por `if pgsql`). Para provar os estágios reais sem fachada, criei o `AnaliseSeedPostgisTest` (@group postgis, espelha o `ExpressoSeedPostgisTest`); o `DatabaseSeederTest` (SQLite) cobre os catálogos + 67/24 + idempotência e a ausência honesta dos estágios em SQLite.
2. **[Rule 2 — Missing Critical] `AnaliseDecidirCommandTest` adicionado.** O comando tem lógica (resolução do analista, `--finalizar`, degradação honesta) — coberto por feature test em SQLite (ficha finalizável via factory, sem motor), espelhando o `ExpressoDecidirCommandTest`.
3. **Usuários de gestão dev (`analista@sile.dev`/`gestor@sile.dev`) criados no `SectorSeeder`.** O plano diz "reusa os usuários dev", mas só existia `admin@sile.dev`. Criar um analista e um gestor dedicados (gated em dev/teste) torna o fluxo caixa→distribuir→assumir→decidir genuinamente navegável com papéis distintos.

**Total deviations:** 3 (2 testes críticos para cobertura honesta + 1 dado de dev necessário). Sem scope creep — todos dentro do escopo do fechamento.

## Bloqueios / pendências que PERMANECEM (sem fachada)
- **Roteamento automático ao setor (produção)** — pendência SEDUR: o encaminhamento deixa `sector_id` nulo; até a regra oficial, o gestor atribui a caixa/analista manualmente (no dev o seed usa a caixa de triagem). NÃO há regra de roteamento inventada.
- **Transmissão Regin (HU-104) / SEFAZ (HU-110)** na conclusão → **Fase 13**: a DECISÃO, o TVL e a AUDITORIA são reais; a transmissão fica `bloqueado` auditado (nunca "enviado"). Reusa os contratos `Unavailable*`/`ResultadoEmitido` da Fase 9.
- **HU-083/084 pleno** (convite Simplifica/Regin + comunicação multicanal) → **EP11**: o ciclo de pendência interno (portal + e-mail simples) é real agora.
- **Exportação plena XLSX/PDF da consulta (HU-131)** → **Fase 15**: CSV simples agora.
- **Zona oficial Quadro 10/SEDUR** pendente: sem ela o veredito locacional degrada para pendente e o humano decide o caso (é exatamente o que a análise técnica existe para resolver).
- **HU-137 (dias úteis/feriados)**: seam `BusinessDeadlineCalculator` pronto; horas/dias-corridos até lá.
- **Assinatura digital gov.br/ICP do TVL** (HU-132 RN-005) → SEDUR: gancho pronto, default imagem do diretor.
- **Quadros/planilhas/ficha SAPS oficiais, gatilhos, valor TLL monetário (DAM bloqueado) e recurso administrativo** → SEDUR/Fase 13 (a ficha exibe "pendente", nunca inventa o valor).

## Authentication Gates
Nenhum — sem CLI/credencial externa neste plano.

## Task Commits
1. **Task 1 — seeds dev (setores/textos-padrão/processos em estágios)** — `efa9e7a` (feat)
2. **Task 1 — comando analise:decidir** — `ecf571f` (feat)
3. **Task 2 — golden, smoke e precedentes postgis** — `c7c840a` (test)

## Next Phase Readiness
- **Fase 10 COMPLETA** (18/18 planos): a análise técnica humana opera de ponta a ponta com LÓGICA REAL e auditável — encaminhamento, fila/SLA, caixa/distribuição, ficha pré-analisada, divergências, precedentes, decisão humana (ViabilityDecision flow analise_tecnica), TVL PDF interno, pendência e malha fina. Verificação integral fresca verde.
- **Pendência de aprovação VISUAL** (smoke navegável humano — roteiro acima) antes de considerar a fase "pronta" do ponto de vista de UX.
- **Próxima acionável: Fase 11 (Pendências e Comunicação)** — depende da Fase 10; HU-083/084 pleno e multicanal.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-15*
