---
phase: 10-analise-tecnica-sedur
plan: "03"
subsystem: maquina-de-estados
tags: [hu-079, hu-083, hu-084, hu-086, hu-087, hu-089, state-machine, evento-de-dominio, after-commit, auto-descoberta, anti-regressao, analise-tecnica]

# Dependency graph
requires:
  - phase: 08-solicitacao-viabilidade
    plan: "10"
    provides: "ViabilityRequestStateMachine::transition() (timeline viability_request_transitions + auditoria RN-002) + InvalidStatusTransitionException"
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "Padrão do evento de domínio after-commit (ResultadoEmitido) + entradas protocolada/aguardando_bap no mapa"
provides:
  - "StateMachine ESTENDIDA com a análise humana: em_analise → {em_pendencia, deferida, indeferida}; em_pendencia → em_analise"
  - "Decisão FINAL preservada (deferida/indeferida sem saída = encerramento HU-089); anti-regressão travada (deferida/indeferida→em_analise e em_pendencia→decisão INVÁLIDAS)"
  - "Evento de domínio EncaminhadoParaAnalise (Dispatchable + ShouldDispatchAfterCommit, carrega ViabilityRequest) — seam dispatcher↔listener auto-descoberto"
affects: [10-07, 10-08, 10-10, 10-11]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Mapa de transições SÓ ADICIONA entradas por fase (Fase 8/9 intactas); decisão final = estado sem saída no mapa"
    - "Evento de domínio after-commit espelhando ResultadoEmitido/SolicitacaoProtocolada (Dispatchable + ShouldDispatchAfterCommit, sem SerializesModels) — seam para listener AUTO-DESCOBERTO, NUNCA Event::listen"

key-files:
  created:
    - app/Events/EncaminhadoParaAnalise.php
    - tests/Feature/Analise/EncaminhadoParaAnaliseEventTest.php
  modified:
    - app/Services/Solicitacao/ViabilityRequestStateMachine.php
    - tests/Feature/Solicitacao/ViabilityRequestStateMachineTest.php

key-decisions:
  - "em_analise/em_pendencia ADICIONADOS ao mapa sem tocar rascunho/protocolada/aguardando_bap; deferida/indeferida seguem FINAIS (sem entrada no mapa)."
  - "Evento espelha ResultadoEmitido/SolicitacaoProtocolada: Dispatchable + ShouldDispatchAfterCommit, SEM SerializesModels (consistência com os 2 eventos irmãos; o texto da Task 2 citava SerializesModels — divergência deliberada, ver Deviations)."
  - "NÃO registrar listener via Event::listen — auto-descoberta (lição Fases 8/9); o PreAnalisarProcesso (10-08) faz type-hint do evento no handle."
  - "Reuso total de transition(): cada transição nova grava timeline + auditoria RN-002 sem nova mecânica."

patterns-established:
  - "Anti-regressão de máquina de estados validada em DOIS níveis: canTransition() (guarda) + transition() lançando InvalidStatusTransitionException sem alterar estado nem gravar timeline."

# Metrics
duration: ~12min
completed: 2026-06-14
---

# Phase 10 Plan 03: Transições da Análise Humana + Evento EncaminhadoParaAnalise Summary

**A `ViabilityRequestStateMachine` GANHOU as transições da análise técnica humana (EP10) SEM tocar as Fases 8/9: `em_analise → {em_pendencia, deferida, indeferida}` (abre pendência HU-083/084 ou decide HU-086/087) e `em_pendencia → em_analise` (ciclo de pendência: só de `em_analise` se decide). A decisão segue FINAL — `deferida`/`indeferida` não têm entrada no mapa (= encerramento HU-089) — e a anti-regressão está travada em dois níveis (`canTransition()` + `transition()` lançando `InvalidStatusTransitionException` sem mudar estado): `deferida→em_analise`, `indeferida→em_analise`, `em_pendencia→deferida`, `em_pendencia→indeferida` e `em_analise→protocolada` são INVÁLIDAS. Cada transição nova reusa o `transition()` existente — grava `viability_request_transitions` (timeline) + auditoria (RN-002, `solicitacoes`/`transicao`) sem mecânica nova. Foi criado o evento de domínio `EncaminhadoParaAnalise` (`Dispatchable` + `ShouldDispatchAfterCommit`, carregando a `ViabilityRequest`), espelhando `ResultadoEmitido`/`SolicitacaoProtocolada`: é o seam que mantém o dispatcher (`FluxoExpressoService::encaminharAnalise`, wiring em 10-07) e o listener AUTO-DESCOBERTO da pré-análise (`PreAnalisarProcesso`, HU-140, 10-08) em planos PARALELOS — o listener fará type-hint do evento no `handle`, NUNCA `Event::listen`. TDD estrito (RED→GREEN com evidência fresca): `ViabilityRequestStateMachineTest` 15/15 (70 asserções, +6 casos novos sobre os 9 das Fases 8/9) e `EncaminhadoParaAnaliseEventTest` 3/3. Filtro combinado do plano 18/18 (73 asserções). ZERO dependência nova. Escopo respeitado: tocados só os 4 arquivos do plano (NÃO toquei ParameterSeeder/10-01 nem migrations/models/10-02).**

## Performance

- **Duration:** ~12 min
- **Tasks:** 2 (transições na StateMachine + anti-regressão; evento de domínio)
- **Commits:** 4 atômicos (test/feat por task) + docs
- **Files:** 4 (2 criados, 2 modificados) — ZERO dependência nova

## Contrato para os planos seguintes (nomes EXATOS)

### `ViabilityRequestStateMachine::TRANSITIONS` (mapa FINAL após 10-03)

```php
'rascunho'       => ['protocolada', 'cancelada'],                                  // Fase 8
'protocolada'    => ['cancelada', 'em_analise', 'deferida', 'indeferida', 'aguardando_bap'], // Fase 9
'aguardando_bap' => ['deferida', 'indeferida', 'em_analise'],                      // Fase 9
'em_analise'     => ['em_pendencia', 'deferida', 'indeferida'],                    // EP10 (NOVO)
'em_pendencia'   => ['em_analise'],                                                // EP10 (NOVO)
// deferida / indeferida: SEM entrada no mapa = estado FINAL (encerramento HU-089)
```

Transições INVÁLIDAS travadas (anti-regressão): `deferida→em_analise`, `indeferida→em_analise`, `em_pendencia→deferida`, `em_pendencia→indeferida`, `em_analise→protocolada` (e as antigas: `protocolada→rascunho`, `rascunho→em_analise`, `cancelada→deferida`, `aguardando_bap→protocolada`).

- **10-10** (decisão humana): transiciona `em_analise → deferida|indeferida` via `transition($request, $to, actor: $analista, reason, publicLabel)` dentro da transação do `AnaliseTecnicaDecisionService::decide()`.
- **10-11** (ciclo de pendência): `em_analise → em_pendencia` ao abrir pendência e `em_pendencia → em_analise` ao sanear/responder.

### `App\Events\EncaminhadoParaAnalise`

```php
final class EncaminhadoParaAnalise implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    public function __construct(public ViabilityRequest $request) {}
}
```

- **10-07** (dispatcher): `EncaminhadoParaAnalise::dispatch($request)` no `FluxoExpressoService::encaminharAnalise`, APÓS o commit da transição → `em_analise`.
- **10-08** (listener AUTO-DESCOBERTO): `PreAnalisarProcesso::handle(EncaminhadoParaAnalise $event)` roda o `SolicitacaoViabilityResolver` e cria `analysis_records` rev 1 (rascunho). NUNCA registrar via `Event::listen`; travar por CONTAGEM de listeners no teste (lição Fases 8/9). O `EncaminhadoParaAnaliseEventTest` já prova o contrato do payload (`$event->request`).

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-086/087 — decidir a partir de em_analise | `ViabilityRequestStateMachineTest::test_em_analise_transiciona_para_pendencia_e_decisao` | em_analise→deferida/indeferida válidas + auditadas |
| HU-083/084 — ciclo de pendência | `...::test_em_analise_para_em_pendencia_audita_transicao` + `...::test_em_pendencia_volta_para_em_analise` | em_analise↔em_pendencia válidas + trilha/auditoria |
| HU-089 — encerramento (estado final) | `...::test_decisao_final_e_ciclo_de_pendencia_travados` | deferida/indeferida sem saída |
| Anti-regressão — decisão final | `...::test_decisao_final_lanca_excecao_e_nao_altera_estado` | deferida→em_analise lança exceção, estado/timeline intactos |
| HU-079/140 — seam do encaminhamento | `EncaminhadoParaAnaliseEventTest` (3 casos) | after-commit + carrega a request + despachável |

## Decisions Made

- **em_analise/em_pendencia ADICIONADOS, decisão FINAL preservada** — `deferida`/`indeferida` continuam sem entrada no mapa; encerramento (HU-089) é a ausência de saída, não um estado novo.
- **Evento espelha os irmãos (`Dispatchable` + `ShouldDispatchAfterCommit`, sem `SerializesModels`)** — consistência com `ResultadoEmitido`/`SolicitacaoProtocolada` (os 3 eventos do SILE), conforme a diretriz repetida do plano "espelha SolicitacaoProtocolada/ResultadoEmitido". Ver Deviations #1.
- **Auto-descoberta, NUNCA `Event::listen`** — o seam fica estável aqui; o listener (10-08) só faz type-hint do evento no `handle`.
- **Reuso de `transition()`** — timeline + auditoria RN-002 sem mecânica nova; o caller controla a transação (padrão atual).

## Deviations from Plan

### 1. [Decisão de implementação] Evento SEM `SerializesModels`

- **Texto da Task 2:** "espelhar `ResultadoEmitido`/`SolicitacaoProtocolada`: `use Dispatchable, SerializesModels;`" — há contradição interna: os DOIS eventos irmãos citados usam SÓ `Dispatchable` (+ `ShouldDispatchAfterCommit`), nunca `SerializesModels`.
- **O que foi feito:** priorizei a diretriz dominante e repetida (user query + must_haves + key_links: "espelha SolicitacaoProtocolada/ResultadoEmitido") e a regra de consistência com arquivos irmãos — `EncaminhadoParaAnalise` usa exatamente `Dispatchable` + `ShouldDispatchAfterCommit`. `SerializesModels` só importa para eventos enfileirados; os 3 eventos do SILE têm listeners SÍNCRONOS auto-descobertos, então é inerte aqui e introduziria inconsistência num único dos três.
- **Impacto:** nenhum no contrato/payload nem nos testes (provam after-commit + payload). Se 10-08 vier a enfileirar o listener (`ShouldQueue`), basta adicionar `SerializesModels` ao evento — gancho trivial, registrado.

### 2. [Fora do escopo — observação, NÃO corrigido] Regressão cruzada do 10-01 na suíte completa

- **Sintoma:** na suíte completa SQLite, `Tests\Feature\Roles\ManageRolesTest::test_administrador_lista_perfis_com_permissoes_e_vinculos` falha: espera `permissions, 19`, encontra 24.
- **Causa raiz (investigada):** o commit `1ceb70c feat(10-01): adiciona 5 permissoes aditivas da analise tecnica` (plano PARALELO 10-01) adicionou as 5 permissões do EP10 (`analisar-processos`, `distribuir-processos`, `emitir-tvl`, `encaminhar-malha-fina`, `manter-setores`) ao `RolesAndPermissionsSeeder` (19 + 5 = 24), mas o `ManageRolesTest` (domínio Roles/EP02) ainda fixa `19` na asserção (linha 44).
- **Por que NÃO corrigi:** está fora do escopo de 10-03 (toco só StateMachine + evento + seus testes); `ManageRolesTest`/`RolesAndPermissionsSeeder` pertencem ao domínio do 10-01. Nenhum dos meus 4 arquivos toca permissões/roles — a falha existe a partir do `1ceb70c`, independente das minhas mudanças.
- **Encaminhamento:** o 10-01 (ou o fechamento da fase) deve atualizar a asserção de `ManageRolesTest` para 24 (e o `RolesAndPermissionsSeederTest`, se fixar a contagem). Registrado como pendência de 10-01.

## Authentication Gates

Nenhum — sem CLI/credencial externa neste plano.

## Verification (evidência fresca)

- **Task 1 RED:** `--filter=ViabilityRequestStateMachineTest` → 15 testes, 12 passados, **3 falhas pelo motivo certo** (`em_analise→em_pendencia` inválida hoje: 2 asserções `canTransition` falsas + 1 `transition()` lançando exceção).
- **Task 1 GREEN:** `--filter=ViabilityRequestStateMachineTest` → **15/15 (70 asserções)**.
- **Task 2 RED:** `--filter=EncaminhadoParaAnaliseEventTest` → 3 erros pelo motivo certo (`Class "App\Events\EncaminhadoParaAnalise" not found`).
- **Task 2 GREEN:** `--filter=EncaminhadoParaAnaliseEventTest` → **3/3 (3 asserções)**.
- **Filtro combinado do plano:** `--filter="ViabilityRequestStateMachineTest|EncaminhadoParaAnaliseEventTest"` → **18/18 (73 asserções)**.
- **`vendor/bin/pint --format agent`** (nos arquivos tocados) → passed.
- **Suíte completa SQLite** (`--exclude-group=postgis`): **951/952 passados** — a ÚNICA falha é o `ManageRolesTest` do 10-01 (Deviation #2), ortogonal a 10-03. Anti-regressão das Fases 8/9 (protocolada/rascunho/aguardando_bap/cancelada) verde.

## Next Phase Readiness

- **10-07** dispara `EncaminhadoParaAnalise::dispatch($request)` no `encaminharAnalise` (após commit → em_analise).
- **10-08** pendura `PreAnalisarProcesso` no evento por AUTO-DESCOBERTA (type-hint no `handle`); travar por CONTAGEM de listeners no teste.
- **10-10** usa `transition(em_analise → deferida|indeferida)`; **10-11** usa `em_analise ↔ em_pendencia`.
- **Pendência cruzada (10-01):** atualizar `ManageRolesTest` (19 → 24 permissões) — não bloqueia 10-03.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
