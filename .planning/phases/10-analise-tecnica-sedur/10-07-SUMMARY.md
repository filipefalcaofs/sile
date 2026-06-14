---
phase: 10-analise-tecnica-sedur
plan: 07
subsystem: distribuicao-analise
tags: [hu-079, hu-080, hu-081, hu-144, evento-after-commit, caixa-setor, distribuir, lote, assumir, sla, auditoria-sincrona, server-driven]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "FluxoExpressoService::encaminharAnalise (transição + auditoria síncrona) + padrão de evento after-commit (ResultadoEmitido)"
  - phase: 10-analise-tecnica-sedur/10-02
    provides: "colunas sector_id/assigned_user_id/assigned_at/analysis_stage/analysis_stage_started_at/analysis_due_at (FORA do fillable); sectors + sector_user; relations sector()/assignedTo() e User::sectors()"
  - phase: 10-analise-tecnica-sedur/10-03
    provides: "evento EncaminhadoParaAnalise (Dispatchable + ShouldDispatchAfterCommit) + transição em_analise no mapa da StateMachine"
  - phase: 10-analise-tecnica-sedur/10-05
    provides: "AnalysisSlaService::dueAtFor (prazo-limite por etapa, reusa BusinessDeadlineCalculator)"
  - phase: 10-analise-tecnica-sedur/10-01
    provides: "permissões analisar-processos / distribuir-processos"
provides:
  - "FluxoExpressoService::encaminharAnalise ESTENDIDO: materializa analysis_due_at/analysis_stage (etapa distribuição) e dispara EncaminhadoParaAnalise after-commit"
  - "DistribuicaoService: distribuir (single), distribuirLote (RN-007) e assumir (HU-081) — atribuição sem sair da caixa, SLA recalculado (etapa análise) e auditoria síncrona por processo (RN-006)"
  - "DistribuicaoException (semSetor / analistaForaDoSetor) — vínculo de setor obrigatório"
  - "CaixaSetorController + rotas caixa-setor.* (index/distribuir/assumir) gated por analisar-processos / distribuir-processos; 403 auditado no ponto único"
  - "DistribuirProcessoRequest (single+lote): request_ids em_analise + analista_id"
affects: [10-08, 10-14, 10-16]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Evento de domínio disparado após o commit pelo PRODUTOR (10-07) e consumido por listener AUTO-DESCOBERTO no plano paralelo (10-08) — seam file-disjunto"
    - "Atribuição na caixa via forceFill (colunas fora do fillable) + SLA recalculado por AnalysisSla(fonte única) + auditoria SÍNCRONA por processo (não depende de evento)"
    - "Lote tolerante a falha (RN-007): itera, audita por item e isola DistribuicaoException sem abortar os demais"
    - "Controller server-driven escopado por setor do usuário (espelha ResultadoExpressoController); 403 auditado no handler único (bootstrap/app.php)"

key-files:
  created:
    - app/Services/Analise/DistribuicaoService.php
    - app/Services/Analise/DistribuicaoException.php
    - app/Http/Controllers/Gestao/CaixaSetorController.php
    - app/Http/Requests/Gestao/DistribuirProcessoRequest.php
    - tests/Feature/Analise/EncaminhamentoAnaliseTest.php
    - tests/Feature/Analise/DistribuicaoServiceTest.php
    - tests/Feature/Analise/CaixaSetorTest.php
  modified:
    - app/Services/Expresso/FluxoExpressoService.php
    - routes/gestao.php

key-decisions:
  - "10-07 é o PRODUTOR do EncaminhadoParaAnalise (dispatch no encaminharAnalise, after-commit), conforme o plano + frontmatter; 10-08 (lido) só cria PreAnaliseService + listener PreAnalisarProcesso e dispara o evento REAL nos próprios testes — file-disjunto, sem tocar FluxoExpressoService. Coordenação resolvida sem sobreposição."
  - "encaminharAnalise grava analysis_stage=distribuicao + analysis_due_at (≈ +2d) e deixa sector_id/assigned_user_id NULOS — a distribuição (HU-080) e a assunção (HU-081) são ações posteriores na caixa; o roteamento da Fase 9 (toggle/semi-expresso/pendente → em_analise) NÃO mudou."
  - "Auditoria do histórico de atribuição é SÍNCRONA (RN-006) dentro da transação do DistribuicaoService — quem (ator_id + assigned_user_id), quando (assigned_at), de qual setor (sector_id) — não depende de evento (lição Fases 8/9)."
  - "distribuir/assumir exigem analista VINCULADO ao setor do processo (HU-081); sem vínculo → DistribuicaoException; no lote vira falha isolada (RN-007)."
  - "Auditoria do DistribuicaoService usa AuditService::log POSICIONAL ('analise', evento, ...) — espelha SectorController e satisfaz o critério de aceite do plano."

patterns-established:
  - "Atribuição idempotente-segura na caixa sem mudar sector_id (RN-004) nem o status (segue em_analise) — distribuir e assumir compartilham o mesmo núcleo, divergindo só no evento de auditoria e no ator."

# Metrics
duration: ~40min
completed: 2026-06-14
---

# Phase 10 Plan 07: Distribuição e gatilho da pré-análise (HU-079/080/081) — Summary

**O `encaminharAnalise` foi ESTENDIDO (Fase 9 intacta) para MATERIALIZAR o SLA da fila (`analysis_due_at`/`analysis_stage`=distribuição, via `AnalysisSlaService`) e DISPARAR `EncaminhadoParaAnalise` APÓS o commit — o gatilho da pré-análise (10-08, listener auto-descoberto). O `DistribuicaoService` entrega a caixa do setor (HU-080/081): `distribuir` (single), `distribuirLote` (RN-007, falha isolada) e `assumir` — todos atribuindo um analista VINCULADO ao setor SEM o processo sair da caixa (RN-004: `sector_id` intacto, status `em_analise`), recalculando o SLA para a etapa de análise (≈ +10d) e auditando SÍNCRONO por processo (RN-006: quem/quando/setor). O `CaixaSetorController` lista a caixa escopada aos setores do usuário (ordenada por prazo) e expõe `distribuir`/`assumir`, gated por `distribuir-processos`/`analisar-processos` (403 auditado no ponto único). TDD estrito (RED→GREEN com evidência fresca): filtro do plano 16/16; suíte completa 1020/1020. ÚNICO editor de `routes/gestao.php` na Wave 3; ZERO dependência nova.**

## Performance
- **Tasks:** 3 (commit atômico por task)
- **Files:** 9 (7 criados, 2 modificados)

## Contrato para os planos seguintes (nomes EXATOS)

### `FluxoExpressoService::encaminharAnalise` (estendido)
Dentro da transação (após a transição → `em_analise` e antes da auditoria já existente):
```php
$startedAt = now();
$request->forceFill([
    'analysis_stage' => AnalysisStage::Distribuicao,
    'analysis_stage_started_at' => $startedAt,
    'analysis_due_at' => $this->sla->dueAtFor(AnalysisStage::Distribuicao, $startedAt),
])->save();
```
APÓS o commit: `EncaminhadoParaAnalise::dispatch($request)`. Construtor ganhou `AnalysisSlaService $sla` (auto-resolvido pelo container — todos os call sites usam `app()`/injeção). `sector_id`/`assigned_user_id` ficam NULOS.

### `App\Services\Analise\DistribuicaoService`
Construtor injeta `AnalysisSlaService`, `AuditService`.

| Método | Assinatura | Efeito |
|---|---|---|
| `distribuir` | `(ViabilityRequest $request, User $analista, ?User $ator = null): void` | Atribui `assigned_user_id`/`assigned_at`, `analysis_stage=Analise`, `analysis_due_at=dueAtFor(Analise)`; `sector_id`/status intactos; audita `analise`/`distribuir`. Lança `DistribuicaoException` se sem setor / analista fora do setor. |
| `distribuirLote` | `(iterable $requests, User $analista, ?User $ator = null): array{ok:int, falhas:list<array{viability_request_id:int, motivo:string}>}` | Itera `distribuir`, audita por item, isola falhas (RN-007). |
| `assumir` | `(ViabilityRequest $request, User $analista): void` | Igual a `distribuir` com ator=analista; audita `analise`/`assumir` (HU-081). |

`DistribuicaoException` (RuntimeException): `::semSetor($request)`, `::analistaForaDoSetor($analista, $request)`.

Auditoria (log `analise`, eventos `distribuir`/`assumir`) — `properties`: `viability_request_id`, `protocol_number`, `sector_id`, `assigned_user_id`, `ator_id`.

### Rotas `caixa-setor.*` (gestao) + gates
| Método/rota | Nome | Gate |
|---|---|---|
| GET `gestao/caixa-setor` | `gestao.caixa-setor.index` | `permission:analisar-processos` |
| POST `gestao/caixa-setor/distribuir` | `gestao.caixa-setor.distribuir` | `permission:distribuir-processos` |
| POST `gestao/caixa-setor/{viabilityRequest}/assumir` | `gestao.caixa-setor.assumir` | `permission:analisar-processos` |

`CaixaSetorController@index` → componente Inertia `gestao/caixa-setor/index`, prop `processos` (paginada, em_analise dos setores do usuário, ordenada por `analysis_due_at`) com `id, protocol_number, empresa, cnpj, status(_label), analysis_stage(_label), sector, assigned_user_id, assigned_to, analysis_due_at`. `DistribuirProcessoRequest`: `request_ids` (array, ids em_analise) + `analista_id` (aceita também `request_id` single, normalizado).

## Mapa CA → teste (todos verdes)
| HU / RN | Teste |
|---|---|
| HU-079 — encaminhar materializa SLA | `EncaminhamentoAnaliseTest::test_encaminhamento_materializa_o_sla_na_etapa_de_distribuicao` |
| HU-079/140 — gatilho + auditoria síncrona | `EncaminhamentoAnaliseTest::test_encaminhamento_dispara_evento_e_mantem_auditoria_sincrona` |
| Regressão Fase 9 — decisão não dispara o evento | `EncaminhamentoAnaliseTest::test_decisao_deferida_nao_dispara_encaminhado_para_analise` |
| HU-080 RN-004 — atribui sem sair da caixa | `DistribuicaoServiceTest::test_distribuir_atribui_analista_vinculado_e_recalcula_o_sla_sem_sair_da_caixa` |
| HU-080/081 — vínculo de setor obrigatório | `DistribuicaoServiceTest::test_analista_nao_vinculado...` / `test_assumir_por_analista_fora_do_setor_e_bloqueado` |
| HU-080 RN-007 — lote (auditoria por item + falha isolada) | `DistribuicaoServiceTest::test_distribuir_em_lote...` / `test_lote_isola_falha...` |
| HU-080 RN-006 — histórico auditado com setor | `DistribuicaoServiceTest` (assert `properties.sector_id`) |
| HU-081 — assumir da caixa | `DistribuicaoServiceTest::test_assumir...` + `CaixaSetorTest::test_analista_assume...` |
| HU-080 CA-04 — segurança de acesso | `CaixaSetorTest::test_index_exige_analisar_processos_e_audita_o_403` / `test_distribuir_exige_distribuir_processos` |
| Caixa escopada + ordenada por prazo | `CaixaSetorTest::test_analista_ve_so_a_caixa_dos_seus_setores` / `test_index_lista_ordenada_por_prazo` |

## Task Commits
1. **Task 1: encaminhamento materializa SLA + dispara EncaminhadoParaAnalise** — `96e7dcd` (feat)
2. **Task 2: DistribuicaoService (distribuir/lote/assumir) + DistribuicaoException** — `fd9dd08` (feat)
3. **Task 3: CaixaSetorController + DistribuirProcessoRequest + rotas caixa-setor** — `0bb4c10` (feat)

_Cada task seguiu RED→GREEN com evidência fresca._

## Verification (evidência fresca)
- `vendor/bin/pint --dirty --format agent` → **passed** (cada task).
- `php artisan test --compact --filter="EncaminhamentoAnaliseTest|FluxoExpressoElegibilidadeTest"` → **7/7** (Task 1 + regressão da Fase 9).
- `php artisan test --compact --filter=DistribuicaoServiceTest` → **6/6**.
- `php artisan test --compact --filter=CaixaSetorTest` → **7/7**.
- `php artisan test --compact --filter="EncaminhamentoAnaliseTest|DistribuicaoServiceTest|CaixaSetorTest"` → **16/16** (60 asserções).
- `php artisan test --compact --exclude-group postgis` → **1020/1020** (5151 asserções) — suíte completa verde, Fase 9 intacta.
- Greps de aceite OK: `EncaminhadoParaAnalise::dispatch` + `analysis_due_at` em `FluxoExpressoService`; `assigned_user_id` + `AnalysisSlaService` + `->log('analise'` em `DistribuicaoService`; `caixa-setor` em `routes/gestao.php`.

## Coordenação (Wave 3) e Deviations
- **EncaminhadoParaAnalise — produtor x consumidor (resolvido sem conflito):** o plano 10-08 foi LIDO antes de codar. Ele só cria `PreAnaliseService` + listener AUTO-DESCOBERTO `PreAnalisarProcesso` e dispara o evento REAL nos próprios testes — NÃO toca `FluxoExpressoService`. Logo, o dispatch no `encaminharAnalise` é responsabilidade do 10-07 (Task 1, como manda o plano e o frontmatter `files_modified`), e o 10-08 é o consumidor. File-disjunto: nenhum arquivo compartilhado. No ambiente atual o dispatch é inerte (listener do 10-08 ainda não existe na árvore); ao integrar, a pré-análise reage por auto-descoberta.
- **ÚNICO editor de `routes/gestao.php` na Wave 3:** confirmado — só este plano alterou o arquivo (import + grupo `caixa-setor`).
- **STATE.md NÃO editado** (wave paralela — consolidação a cargo do orquestrador), conforme instrução.
- Sem outros desvios no código de produção — plano executado como escrito.

## Pendência registrada (anti-fachada — entrega-funcional)
- **Roteamento ao setor (gravar `sector_id`) está FORA do escopo das 3 tasks deste plano.** O `encaminharAnalise` deixa `sector_id` nulo (Task 1) e o `DistribuicaoService` mantém `sector_id` intacto (RN-004). Portanto, hoje NÃO há produtor de `sector_id`: um processo só entra numa caixa de setor (e fica distribuível/assumível) depois que algum passo definir o setor. Não há regra de roteamento nas HUs entregues (HU-079/080/081/138) — é decisão/planilha pendente da SEDUR ou um passo de "atribuir setor" a planejar (provável 10-16/console). **Não foi simulado**: distribuir/assumir funcionam de ponta a ponta sobre processos já em um setor (provado nos testes); o que falta é o produtor do `sector_id`. Encaminhar como pendência ao orquestrador da fase.

## Next Phase Readiness
- **10-08** (pré-análise): o `EncaminhadoParaAnalise` já é disparado no encaminhamento — o listener auto-descoberto cria a revisão 1 ao integrar.
- **10-14** (fila/consulta): lê `assigned_user_id`/`sector_id`/`analysis_due_at`/`analysis_stage` (índices de 10-02) e `AnalysisSlaService::statusFor` para o semáforo on-the-fly.
- **10-16** (tela da caixa/distribuição): consome o componente `gestao/caixa-setor/index` e as rotas `caixa-setor.distribuir`/`assumir`; deve resolver o passo de roteamento ao setor (pendência acima).
- ZERO dependência nova.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
