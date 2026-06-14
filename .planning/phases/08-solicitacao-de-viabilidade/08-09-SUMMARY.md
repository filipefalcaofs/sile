---
phase: 08-solicitacao-de-viabilidade
plan: 09
subsystem: simulacao-viabilidade
tags: [solicitacao-viabilidade, hu-141, simulacao, motor-fase-7, propaga-veredito, snapshot, toggle, rn-001, rn-002, rn-003, rn-005, auditoria, anti-fachada]

# Dependency graph
requires:
  - phase: 07-consulta-previa-viabilidade
    plan: "05"
    provides: "ConsultaViabilidadeService (orquestra Geocoder→TerritoryService→LouosEnquadramentoService→RiscoClassificationService e PROPAGA o veredito do motor LOUOS) + DTOs ConsultaViabilidadeInput/Result"
  - phase: 08-solicitacao-de-viabilidade
    plan: "01"
    provides: "ViabilityRequest (simulation_snapshot/_rules_versions/_resultado/simulated_at fillable + markSimulationStale) + cnaes() withPivot is_primary"
  - phase: 08-solicitacao-de-viabilidade
    plan: "06"
    provides: "centroide do polígono (ponto da solicitação) + property_polygon_geojson como fonte"
  - phase: 08-solicitacao-de-viabilidade
    plan: "05"
    provides: "ViabilityRequestPolicy::update (dono + rascunho) — autorização da simulação"
  - phase: 08-solicitacao-de-viabilidade
    plan: "02"
    provides: "toggle features.simulacao_solicitacao (catálogo + fallback config/sile.php)"
provides:
  - "ConsultaViabilidadeService::consultarPorPontoConhecido(lat,lng,cnae,?area) — entrada PÚBLICA por ponto+CNAE (refactor mínimo, sem geocodificar de novo, sem regressão dos 3 públicos)"
  - "App\\Services\\Solicitacao\\SimulacaoSolicitacaoService::simulate(ViabilityRequest): array — itera CNAEs, propaga o veredito, consolida e persiste o snapshot"
  - "Snapshot persistido: simulation_snapshot (ponto/area/por_cnae com a consulta completa por CNAE) + simulation_rules_versions + simulation_resultado (consolidado) + simulated_at"
  - "Rota POST portal/solicitacoes/{solicitacao}/simular (name portal.solicitacoes.simular) — orientativa, não bloqueia o protocolo"
affects: [08-10-protocolo, 08-13-ui-wizard]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Reuso do motor da Fase 7 SEM recomputar: a simulação PROPAGA o veredito por CNAE (consultarPorPontoConhecido/consultarPorCnae); nenhuma lógica de decisão paralela (HU-141 RN-001)"
    - "Entrada por ponto conhecido: refactor mínimo expõe um método público que reaproveita a pipeline privada consultarPorPonto (sem geocodificar de novo); novo TIPO_PONTO no Input, sem tocar as 3 entradas existentes"
    - "Consolidação por pior caso (severidade nao_permitido>pendente>permitido_com_condicoes>permitido): a atividade mais restritiva governa a tendência da solicitação"
    - "Snapshot orientativo persistido no aggregate (RN-003) — o protocolo (08-10) LÊ o snapshot, não reprocessa"

key-files:
  created:
    - app/Services/Solicitacao/SimulacaoSolicitacaoService.php
    - app/Http/Controllers/Portal/SolicitacaoSimulacaoController.php
    - tests/Feature/Solicitacao/SimulacaoSolicitacaoTest.php
  modified:
    - app/Services/Viabilidade/ConsultaViabilidadeService.php
    - app/Services/Viabilidade/ConsultaViabilidadeInput.php
    - routes/portal.php

key-decisions:
  - "Entrada por ponto exposta como NOVO método público consultarPorPontoConhecido(lat,lng,cnae,?area) que delega à pipeline privada consultarPorPonto — mantém os 3 públicos (endereço/CNAE/inscrição) intactos (ConsultaViabilidadeServiceTest 9/9 sem regressão). Alternativa de tornar consultarPorPonto público foi descartada (assinatura interna com Input/GeocodeResult é API ruim)."
  - "ConsultaViabilidadeInput ganhou TIPO_PONTO='ponto' + named constructor paraPonto — honesto (a auditoria registra tipo 'ponto', a consulta veio do ponto da solicitação, não de endereço/CNAE/inscrição). Sem switch sobre tipo no motor — adição segura."
  - "Simulação iterando os CNAEs com o principal primeiro (orderByPivot is_primary desc + code); cada CNAE roda o motor real e o snapshot guarda o ConsultaViabilidadeResult::toArray() completo (insumo do 08-13 reusar ResultadoViabilidade)."
  - "Sem polígono → degrada honesto para consultarPorCnae (risco + Quadro 7, território null) — NUNCA inventa ponto. Centroide recomputado em PHP no service (portável SQLite/Postgres), reusando o mesmo cálculo do 08-06 (não extraí helper compartilhado para não tocar o controller do 08-06)."
  - "simulation_rules_versions = versões do PRIMEIRO CNAE (representativas — território/louos/risco são idênticas entre CNAEs no mesmo ponto e vigência). simulation_resultado consolida o pior caso; sem CNAEs degrada para 'pendente'."
  - "Controller NÃO bloqueia (RN-002): Gate update (dono+rascunho) → toggle off degrada comunicado (sem simular/persistir) → senão simula e devolve flash 'simulacao'. Não muda status nem mexe em applicant_proceeded_despite (a ciência 'prosseguir mesmo assim' é do 08-10)."

patterns-established:
  - "Serviço de simulação como CONSUMIDOR do motor: injeta ConsultaViabilidadeService + AuditService, persiste em DB::transaction (snapshot + auditoria atômicos) e devolve array para o wizard"

# Metrics
duration: ~5 min (commits 9dab9dc→e949390; ~12 min com leitura de contexto)
completed: 2026-06-14
---

# Phase 8 Plan 09: Simulação pré-protocolo (HU-141) Summary

**A simulação de viabilidade pré-protocolo (HU-141) reusa o motor REAL da Fase 7 sem nenhuma lógica paralela: o `SimulacaoSolicitacaoService` itera os CNAEs da solicitação (principal + complementares) e chama o `ConsultaViabilidadeService` PELO PONTO da própria solicitação (centroide do polígono — sem geocodificar de novo), PROPAGANDO o veredito do motor LOUOS por CNAE (RN-001). O refactor mínimo foi expor uma entrada PÚBLICA por ponto+CNAE (`consultarPorPontoConhecido`) que reaproveita a pipeline privada `consultarPorPonto`, sem alterar as três entradas públicas existentes (Fase 7 verde, sem regressão). O serviço consolida a tendência (pior caso entre os CNAEs), captura as versões das regras e PERSISTE o snapshot por CNAE + versões + resultado + `simulated_at` no processo (RN-003 — o protocolo 08-10 lê o snapshot, não reprocessa). É ORIENTATIVA: o `SolicitacaoSimulacaoController` autoriza (dono+rascunho), degrada de forma comunicada com o toggle `features.simulacao_solicitacao` off (RN-005) e NÃO bloqueia o protocolo (RN-002). Sem zona oficial, o veredito por CNAE é "pendente" (propagado) — nunca permitido/não permitido inventado; sem polígono, degrada para a via CNAE. Auditoria `solicitacoes/simulacao` (RN-002). ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca): SimulacaoSolicitacaoTest 9/9; ConsultaViabilidadeServiceTest 9/9 sem regressão; suíte completa 770/770.**

## Performance

- **Duration:** ~5 min de execução (1º→último commit), ~12 min com leitura de contexto
- **Tasks:** 2 (expor entrada por ponto no motor da Fase 7; SimulacaoSolicitacaoService + controller + rota + toggle)
- **Files:** 3 criados + 3 modificados — ZERO dependência nova

## Accomplishments

- **Refactor mínimo e seguro da Fase 7**: `ConsultaViabilidadeService::consultarPorPontoConhecido(float $lat, float $lng, string $cnae, ?float $area = null): ConsultaViabilidadeResult` — entrada pública por ponto+CNAE que delega à pipeline privada `consultarPorPonto` (sem geocodificar de novo). `ConsultaViabilidadeInput::paraPonto` com `TIPO_PONTO='ponto'`. Os 3 públicos (endereço/CNAE/inscrição) ficaram intactos — `ConsultaViabilidadeServiceTest` 9/9.
- **`SimulacaoSolicitacaoService::simulate(ViabilityRequest): array`**: itera os CNAEs (principal primeiro), chama o motor por ponto (ou por CNAE sem polígono), PROPAGA o veredito, consolida o pior caso e persiste o snapshot + auditoria em `DB::transaction`.
- **`SolicitacaoSimulacaoController@store`**: `Gate::authorize('update')` (dono+rascunho), toggle `features.simulacao_solicitacao` degrada comunicado quando off, senão simula e devolve flash `simulacao`. NÃO bloqueia, NÃO muda status.
- **Rota** `POST portal/solicitacoes/{solicitacao}/simular` (name `portal.solicitacoes.simular`) anexada após as literais (e após as rotas de documentos do 08-08, executado em paralelo).
- **Anti-fachada**: sem zona → pendente propagado (nunca inventado); sem polígono → via CNAE (território null no snapshot); mesma versão de regras do fluxo oficial (RN-001).

## Contrato para os próximos planos

### Entrada pública por ponto (motor da Fase 7)

```
ConsultaViabilidadeService::consultarPorPontoConhecido(float $lat, float $lng, string $cnae, ?float $area = null): ConsultaViabilidadeResult
```

Roda a pipeline completa (território → motores) a partir do ponto, propagando o veredito; `geocode` fica null (não geocodifica).

### Snapshot persistido em `viability_requests` (insumo do 08-10 e 08-13)

- **`simulation_snapshot`** (jsonb):
  - `ponto`: `{lat, lng}` ou `null` (sem polígono).
  - `area_m2`: float ou `null`.
  - `por_cnae[]`: `{cnae (dígitos), cnae_formatado, is_primary (bool), tendencia (value de ResultadoViabilidade), tendencia_label, consulta (ConsultaViabilidadeResult::toArray() completo — geocode/territorio/enquadramento/risco/veredito_locacional/avisos/versoes)}`.
- **`simulation_rules_versions`** (jsonb): `{territorio, louos, risco}` (representativas — idênticas entre CNAEs no mesmo ponto/vigência; RN-002).
- **`simulation_resultado`** (string): tendência consolidada (pior caso); `pendente` quando sem CNAEs/sem zona.
- **`simulated_at`** (datetime): marca a execução (RN-003).

### Retorno de `simulate()` (flash `simulacao` p/ o wizard 08-13)

`{resultado, resultado_label, por_cnae, rules_versions, simulated_at}`.

### Comportamento do toggle / autorização

- `features.simulacao_solicitacao` (default true): off → `back()->with('status', ...)` comunicado, sem simular nem persistir, sem erro (RN-005).
- `ViabilityRequestPolicy::update` (dono + rascunho): terceiro → 403; protocolada/cancelada → 403.
- Auditoria `AuditService::log('solicitacoes','simulacao', result 'sucesso', props {resultado, por_cnae[{cnae,tendencia}], versoes}, subject=solicitação)`.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: entrada pública por ponto+CNAE no motor da Fase 7** — `9dab9dc` (feat) — RED: "Call to undefined method consultarPorPontoConhecido" → GREEN: filtro `ConsultaViabilidadeServiceTest|SimulacaoSolicitacaoTest` 10/10 (sem regressão da Fase 7).
2. **Task 2: SimulacaoSolicitacaoService + controller + rota + toggle** — `e949390` (feat) — RED: 8 falhas (rota indefinida) → GREEN: `SimulacaoSolicitacaoTest` 9/9 (46 asserções).

## Decisions Made

- **Entrada por ponto = método público novo** (não tornar `consultarPorPonto` público): mantém a API limpa (lat,lng,cnae,?area) e preserva a assinatura interna; zero regressão dos 3 públicos.
- **TIPO_PONTO honesto**: a auditoria/entrada registram tipo `ponto` — a consulta veio do ponto da solicitação, não de endereço/CNAE/inscrição.
- **Consolidação por pior caso**: a atividade mais restritiva governa a tendência (uma que tende a indeferimento puxa o conjunto); honesto e conservador para orientar o requerente.
- **Snapshot guarda o resultado completo por CNAE** (`toArray()`): o 08-13 reusa o componente `ResultadoViabilidade` por CNAE sem reprocessar; o 08-10 lê o snapshot (RN-003).
- **Centroide recomputado no service** (PHP portável), reusando o mesmo cálculo do 08-06 — não extraí helper compartilhado para não tocar o controller do 08-06 (escopo mínimo).

## Deviations from Plan

- **Teste extra além dos 6 do plano** (cobertura de ramos reais que o plano exige no service): `test_motor_expoe_consulta_por_ponto_conhecido_propagando_veredito` (Task 1, exercita a entrada pública) e `test_sem_poligono_simula_por_cnae_sem_territorio` (degradação honesta sem polígono — ramo previsto no plano "se não houver polígono, simula por CNAE"). Total 9 testes.
- **Coordenação de rotas com o 08-08 (paralelo)**: o 08-08 já havia commitado (`df61479`) suas rotas de documentos em `routes/portal.php`; reli o arquivo imediatamente antes e ANEXEI só o import + a rota `simular` (git diff vs HEAD = apenas meu trecho, zero clobber); staging individual. Não toquei nos arquivos de documentos do 08-08.
- **Pint automático** removeu os imports `ViabilityRequestStatus`/`Parameter` do teste entre edições (estavam sem uso no instante intermediário); re-adicionados antes do GREEN.

## Issues Encountered

- **Nenhum bloqueio.** A simulação roda 100% com os motores reais da Fase 7 (seeds reais Quadro 7/risco) + território fake (FakeSpatialRepository, padrão da Fase 7) — sem PostGIS, sem dependência nova. As pendências SEDUR herdadas (zona oficial) degradam honesto (veredito pendente propagado), sem fachada.

## Verification (evidência fresca)

- **RED Task 1:** `--filter=SimulacaoSolicitacaoTest` → "Call to undefined method ConsultaViabilidadeService::consultarPorPontoConhecido()". **GREEN:** `--filter="ConsultaViabilidadeServiceTest|SimulacaoSolicitacaoTest"` → 10/10 (53 asserções).
- **RED Task 2:** 8 falhas ("Route [portal.solicitacoes.simular] not defined"). **GREEN:** `--filter=SimulacaoSolicitacaoTest` → 9/9 (46 asserções).
- **Filtros do plano (anti-regressão Fase 7):** `--filter="ConsultaViabilidadeServiceTest|SimulacaoSolicitacaoTest"` → **18/18** (92 asserções).
- **Suíte completa:** `php artisan test --compact` → **770 testes, 770 passaram, 0 falhas** (3896 asserções; inclui @group postgis inline com container healthy e os testes do 08-08 paralelo).
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).

## Next Phase Readiness

- **08-10 (protocolar)**: lê `simulation_snapshot`/`simulation_resultado`/`simulation_rules_versions`/`simulated_at` (RN-003, não reprocessa) e registra `applicant_proceeded_despite` (a ciência "prosseguir mesmo assim" é do protocolo, não da simulação).
- **08-13 (UI do wizard)**: a etapa de simulação consome o flash `simulacao` (e o snapshot persistido) e reusa o componente `ResultadoViabilidade` por CNAE; o toggle off já devolve status comunicado.
- **Bloqueios herdados (não introduzidos aqui)**: veredito locacional permitido/não permitido depende da zona oficial (Quadro 10/SEDUR) — a simulação propaga "pendente" honesto até a base chegar; quando chegar, muda a carga, não a lógica.

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
