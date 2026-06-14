---
phase: 09-fluxo-expresso
plan: 04
subsystem: viabilidade
tags: [hu-073, hu-074, hu-075, hu-141, fluxo-expresso, resolver, dto, refactor, consolidacao, elegibilidade, anti-fachada, rn-001]

# Dependency graph
requires:
  - phase: 07-consulta-previa
    plan: "(consulta)"
    provides: "ConsultaViabilidadeService::consultarPorPontoConhecido/consultarPorCnae + ConsultaViabilidadeResult (vereditoLocacional, risco.encaminhamento.fluxo, versoes, toArray) — motor reusado por CNAE"
  - phase: 08-solicitacao-viabilidade
    plan: "09"
    provides: "SimulacaoSolicitacaoService::simulate (iteração CNAE + consolidar + centroid + snapshot orientativo) — origem da extração; ViabilityRequest (cnaes pivot is_primary, property_polygon_geojson, used_area_m2)"
provides:
  - "SolicitacaoViabilityResolver::resolve(ViabilityRequest): ResolvedViability — núcleo reutilizável de resolução por-CNAE + consolidação pior caso, SEM persistir nem decidir (RN-001)"
  - "ResolvedViability (DTO final readonly): por_cnae[] (com ConsultaViabilidadeResult bruto + toArray), consolidado, rules_versions, ponto, area_m2, elegivelExpresso() (HU-073), toSnapshot() (shape Fase 8)"
  - "SimulacaoSolicitacaoService refatorado para consumir o resolver (mantém snapshot orientativo + auditoria) — Fase 8 verde, sem duplicação de lógica"
affects: [09-05, 09-06]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Extração de núcleo reutilizável (resolver) de um serviço que persiste (simulação): a iteração/consolidação vira insumo puro consumido por DOIS lados (simulação orientativa + decisão autoritativa), sem lógica de decisão paralela (RN-001/DRY)"
    - "DTO readonly carrega o sub-resultado bruto (ConsultaViabilidadeResult objeto) E seu toArray: o consumidor de decisão lê fresco em memória, o snapshot persiste só o array"
    - "Refactor sob anti-regressão: nenhum teste da Fase 8 alterado; o shape do snapshot é travado por teste novo (toSnapshot) ANTES de migrar o serviço"

key-files:
  created:
    - app/Services/Solicitacao/SolicitacaoViabilityResolver.php
    - app/Services/Solicitacao/ResolvedViability.php
    - tests/Feature/Expresso/SolicitacaoViabilityResolverTest.php
  modified:
    - app/Services/Solicitacao/SimulacaoSolicitacaoService.php

key-decisions:
  - "O resolver recebe a injeção de ConsultaViabilidadeService (sem AuditService) e NÃO audita — auditoria é da camada que persiste (simulação) ou decide (09-05). O resolver é insumo puro."
  - "Cada item de por_cnae carrega DOIS campos para a consulta: `consulta` (o objeto ConsultaViabilidadeResult, para a decisão ler risco.encaminhamento.fluxo + vereditoLocacional() fresco) e `consulta_array` (toArray, para o snapshot orientativo da Fase 8). toSnapshot() serializa só o array."
  - "elegivelExpresso() é por ENCAMINHAMENTO de risco (fluxo == 'expresso' em TODOS os CNAEs), NÃO pelo veredito locacional (HU-073). Sem CNAEs → false (nada a decidir)."
  - "SEVERIDADE + consolidar + centroid + orderedCnaes MOVIDOS (não duplicados) do SimulacaoSolicitacaoService para o resolver. O serviço encolheu de 198 para 81 linhas e passou a injetar o resolver (dropou ConsultaViabilidadeService)."
  - "Propriedades do DTO em snake_case (por_cnae/consolidado/rules_versions/area_m2) espelhando o shape do snapshot e o contrato que o 09-05 vai ler."

patterns-established:
  - "Anti-fachada na consolidação: o pior caso é calculado sobre vereditos REAIS do motor LOUOS (provado no teste com Quadro 10: permitido vs proibido→nao_permitido); sem zona o veredito é pendente (propagado), nunca inventado."

# Metrics
duration: ~16 min
completed: 2026-06-14
---

# Phase 9 Plan 04: SolicitacaoViabilityResolver + ResolvedViability Summary

**Extraído de `SimulacaoSolicitacaoService::simulate` o núcleo reutilizável de resolução de viabilidade por solicitação: o `SolicitacaoViabilityResolver` itera os CNAEs (principal + complementares), reusa o motor da Fase 7 (`ConsultaViabilidadeService::consultarPorPontoConhecido`, centroide do polígono — sem geocodificar de novo; sem polígono degrada para `consultarPorCnae`), PROPAGA o veredito do motor LOUOS e o encaminhamento do motor de risco por CNAE (RN-001), consolida o PIOR CASO (`nao_permitido > pendente > permitido_com_condicoes > permitido`) e devolve o DTO `ResolvedViability` (por_cnae[] com o `ConsultaViabilidadeResult` bruto + consolidado + rules_versions + `elegivelExpresso()` + `toSnapshot()`) SEM persistir nem decidir. A simulação da Fase 8 passou a CONSUMIR o resolver e manteve sua camada orientativa intacta (persiste o snapshot via `toSnapshot()`, mesmo shape, + audita RN-002/RN-003). TDD estrito (RED→GREEN com evidência fresca): `SolicitacaoViabilityResolverTest` 10/10 (36 asserções, vereditos REAIS via Quadro 10 — permitido vs proibido→nao_permitido); anti-regressão da Fase 8 verde (Simulacao/Wizard/Smoke/Golden/ConsultaViabilidade 43/43); suíte completa SQLite 867/867 (4458 asserções). ZERO dependência nova; lógica MOVIDA, não duplicada (`consolidar`/`centroid`/`orderedCnaes`/`SEVERIDADE` não existem mais no serviço).**

## Performance

- **Duration:** ~16 min
- **Tasks:** 2 (resolver + DTO com TDD; refactor do serviço sob anti-regressão)
- **Files modified:** 1 (`SimulacaoSolicitacaoService` -117 linhas) + 3 criados (resolver, DTO, teste) — ZERO dependência nova

## Contrato para o 09-05 (a decisão reexecuta o resolver fresco)

### `SolicitacaoViabilityResolver::resolve(ViabilityRequest $request): ResolvedViability`

- Itera `orderedCnaes` (principal primeiro, depois por código). Para cada CNAE: `ponto === null ? consultarPorCnae(code, area) : consultarPorPontoConhecido(lat, lng, code, area)`.
- Construtor: `private ConsultaViabilidadeService $consulta` (sem auditoria — insumo puro).

### `ResolvedViability` (final readonly) — shape EXATO

| Campo | Tipo | Conteúdo |
|---|---|---|
| `por_cnae` | `list<array>` | Um item por CNAE (ver abaixo) |
| `consolidado` | `string` | Pior caso `ResultadoViabilidade` entre os CNAEs (default `pendente`) |
| `rules_versions` | `array` | Versões representativas do 1º result: `{territorio, louos, risco}` |
| `ponto` | `array{lat,lng}\|null` | Centroide do polígono (null sem polígono) |
| `area_m2` | `float\|null` | `used_area_m2` |

Cada item de `por_cnae`:

| Chave | Tipo | Conteúdo |
|---|---|---|
| `cnae` | `string` | Código (dígitos) |
| `cnae_formatado` | `string` | `0000-0/00` |
| `is_primary` | `bool` | CNAE principal |
| `tendencia` | `string` | Veredito locacional propagado (`vereditoLocacional()['resultado']`) |
| `tendencia_label` | `string` | Rótulo do veredito |
| `fluxo` | `string` | Encaminhamento de risco (`risco.encaminhamento['fluxo']`: `'expresso'`\|`'analise'`) |
| `consulta` | `ConsultaViabilidadeResult` | **Objeto bruto** — a decisão lê veredito + encaminhamento fresco numa passada |
| `consulta_array` | `array` | `toArray()` — usado só pelo snapshot |

Métodos:
- `elegivelExpresso(): bool` — `true` SE TODOS os itens têm `fluxo === 'expresso'` (HU-073: qualquer `'analise'` → inelegível; sem CNAEs → `false`). **Elegibilidade é por encaminhamento de risco, NÃO pelo veredito locacional.**
- `toSnapshot(): array` — `{ponto, area_m2, por_cnae:[{cnae, cnae_formatado, is_primary, tendencia, tendencia_label, consulta}]}` com `consulta` = `consulta_array` (objeto fica fora do registro). Shape IDÊNTICO ao que a Fase 8 persiste.

**Como o 09-05 consome:** reexecuta `resolve()` FRESCO (nunca confia no `simulation_snapshot` pré-protocolo, que pode estar `markSimulationStale`); usa `elegivelExpresso()` para a elegibilidade (HU-073), `consolidado` para a decisão defere/indefere (HU-074/075 RN-009) e cada `por_cnae[].consulta` (objeto) para a fundamentação por CNAE.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: SolicitacaoViabilityResolver + ResolvedViability (extração, sem persistir)** — `964616a` (feat) — RED: 10 falhas (`Target class [SolicitacaoViabilityResolver] does not exist`) → GREEN: 10/10 (36 asserções).
2. **Task 2: SimulacaoSolicitacaoService consome o resolver** — `3ba3944` (refactor) — sem teste novo (Fase 8 já cobre); anti-regressão Simulacao/Wizard/Smoke/Golden/ConsultaViabilidade 43/43 (278 asserções) + grep confirmando `consolidar` removido do serviço.

**Plan metadata:** `docs(09-04)` (este SUMMARY + STATE).

## Decisions Made

- **Resolver não audita nem persiste** (só `ConsultaViabilidadeService` injetado): a auditoria/persistência fica na simulação (orientativa) e na decisão (09-05). Insumo puro (RN-001).
- **`consulta` (objeto) + `consulta_array` (toArray) no item por_cnae**: a decisão precisa do objeto bruto para ler `risco.encaminhamento.fluxo` + `vereditoLocacional()` fresco; o snapshot persiste só o array. `toSnapshot()` faz a redução para o shape exato da Fase 8.
- **Elegibilidade por encaminhamento, não por veredito** (`elegivelExpresso()`): HU-073 — todos em `expresso` → elegível; qualquer `analise` → inelegível; sem CNAEs → false.
- **Lógica MOVIDA, não duplicada**: `SEVERIDADE`/`consolidar`/`centroid`/`orderedCnaes` agora vivem só no resolver; o `SimulacaoSolicitacaoService` injeta o resolver e dropou o `ConsultaViabilidadeService`.

## Deviations from Plan

None — plano executado exatamente como escrito (2 tasks; resolver + DTO com o shape especificado; refactor do serviço preservando o snapshot/auditoria e a suíte da Fase 8).

## Issues Encountered

- **Wave 1 em paralelo na MESMA working dir:** os planos 09-01/02/03 já commitaram código (incl. arquivos não rastreados de 09-03 como `app/Events/ResultadoEmitido.php`). **Boundary respeitado**: toquei SOMENTE os meus 4 arquivos (resolver + DTO + teste do resolver + `SimulacaoSolicitacaoService`); staging sempre individual (nunca `git add -A`). A suíte completa (867/867) inclui o trabalho já commitado dos planos paralelos sem conflito.
- **Veredito real para o teste de consolidação:** para provar o pior caso com `permitido`/`nao_permitido` (não só `pendente`), o teste seeda zona vigente + Quadro 7/10 via factory e injeta o `FakeSpatialRepository` com a feição de zona — mesmo padrão dos testes dos motores (LouosConsolidacaoTest). Sem fachada: os vereditos vêm do motor LOUOS real.

## Verification (evidência fresca)

- **RED Task 1:** `--filter=SolicitacaoViabilityResolverTest` → 10 erros (`Target class [SolicitacaoViabilityResolver] does not exist`). **GREEN:** 10/10 (36 asserções).
- **Anti-regressão Fase 8 (Task 2):** `--filter="Simulacao|Wizard|SolicitacaoSmoke|SolicitacaoGoldenCase|ConsultaViabilidadeService"` → 43/43 (278 asserções), sem alterar nenhuma expectativa.
- **Grep anti-duplicação:** `consolidar`/`centroid`/`orderedCnaes`/`SEVERIDADE` ausentes em `SimulacaoSolicitacaoService.php`; `SolicitacaoViabilityResolver` injetado.
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).
- **Suíte completa SQLite:** `php artisan test --compact --exclude-group=postgis` → **867 testes, 867 passaram, 0 falhas** (4458 asserções; inclui o trabalho commitado dos planos paralelos). _Grupo @group postgis não rodado nesta execução (exige container PostGIS); o resolver é SQLite-safe (motores com fake spatial)._

## Next Phase Readiness

- **Insumo direto do 09-05 (decisão autoritativa):** `app(SolicitacaoViabilityResolver::class)->resolve($request)` devolve elegibilidade (`elegivelExpresso()`), consolidado (defere/indefere) e o `ConsultaViabilidadeResult` por CNAE (fundamentação) — tudo FRESCO, sem confiar no snapshot pré-protocolo.
- **Sem bloqueios introduzidos:** o resolver é real e testado agora; sem a zona oficial (Quadro 10/SEDUR) o veredito consolidado é `pendente` → o 09-05 roteará a maioria para `em_analise` (liga sozinho quando a base entrar — muda a carga, não a lógica).
- **Boundary Wave 1 mantido:** 09-04 tocou só resolver + DTO + `SimulacaoSolicitacaoService` + teste do resolver; não tocou ParameterSeeder (09-01), schema/StateMachine/ViabilityRequest (09-02) nem contratos/evento (09-03).

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
