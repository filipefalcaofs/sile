---
phase: 10-analise-tecnica-sedur
plan: "08"
subsystem: analise
tags: [hu-140, pre-analise, listener-auto-descoberto, evento-de-dominio, reuso-resolver, degradacao-honesta, fa-01, idempotencia, auditoria, rn-001, anti-fachada]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "04"
    provides: "SolicitacaoViabilityResolver::resolve(ViabilityRequest): ResolvedViability (por_cnae com tendencia/fluxo/consulta + consolidado + rules_versions) e ResolvedViability::toSnapshot (shape integral, zona aninhada em por_cnae[].consulta.territorio.zona)"
  - phase: 10-analise-tecnica-sedur
    plan: "02"
    provides: "analysis_records (engine_snapshot/engine_rules_versions/engine_available/per_cnae jsonb; unique viability_request_id+revision) + AnalysisRecordStatus + ViabilityRequest::analysisRecords()"
  - phase: 10-analise-tecnica-sedur
    plan: "03"
    provides: "Evento de dominio EncaminhadoParaAnalise (Dispatchable + ShouldDispatchAfterCommit, carrega a ViabilityRequest) — seam dispatcher<->listener auto-descoberto"
  - phase: 10-analise-tecnica-sedur
    plan: "06"
    provides: "CHAVE EXATA da zona no engine_snapshot (por_cnae[i].consulta.territorio.zona = {status, nome, ...}) lida pelo PrecedentService"
  - phase: 10-analise-tecnica-sedur
    plan: "07"
    provides: "FluxoExpressoService::encaminharAnalise ja dispara EncaminhadoParaAnalise::dispatch apos a transicao em_analise (commit 96e7dcd — materializa SLA + gatilho da pre-analise)"
provides:
  - "App\\Services\\Analise\\PreAnaliseService::preAnalisar(ViabilityRequest): ?AnalysisRecord — cria a revisao 1 (rascunho) pre-preenchida reusando o resolver FRESCO; idempotente; degradacao FA-01"
  - "Mapeamento tendencia->status sugerido por CNAE (permitido(_com_condicoes)->deferida; nao_permitido->indeferida; pendente->analise) — SUGESTAO, nao decisao (RN-001)"
  - "App\\Listeners\\PreAnalisarProcesso — listener AUTO-DESCOBERTO de EncaminhadoParaAnalise (type-hint no handle, sem Event::listen), travado por CONTAGEM (=1)"
  - "Shape da revisao 1: engine_snapshot integral (toSnapshot) + engine_rules_versions + per_cnae (status sugerido) — insumo de 10-09 (ficha/recalcular) e 10-17 (ficha SAPS)"
affects: [10-09, 10-17]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Pre-analise REUSA o resolver real (mesmo motor da Fase 9) — sem logica de decisao paralela (RN-001); o status sugerido espelha a semantica do FluxoExpressoService (permitido->deferida etc.)"
    - "Listener AUTO-DESCOBERTO por type-hint do evento no handle (espelha AvaliarFluxoExpresso), NUNCA Event::listen — travado por CONTAGEM (Event::getListeners == 1), licao Fases 8/9"
    - "Degradacao honesta FA-01 em DOIS gatilhos: excecao do motor (catch Throwable) E veredito consolidado pendente (sem zona) -> engine_available=false + ficha vazia, auditado 'degradado', nunca sugestao inventada"
    - "Idempotencia por revisao (revisao 1 ja existe -> no-op) + guarda de corrida via catch QueryException da unique(viability_request_id+revision), devolvendo a existente"

key-files:
  created:
    - app/Services/Analise/PreAnaliseService.php
    - app/Listeners/PreAnalisarProcesso.php
    - tests/Feature/Analise/PreAnaliseServiceTest.php
    - tests/Feature/Analise/PreAnalisarProcessoListenerTest.php
  modified: []

key-decisions:
  - "engine_snapshot = resolved->toSnapshot() INTEGRAL (zona ANINHADA em por_cnae[i].consulta.territorio.zona, nunca top-level) — o PrecedentService (10-06) le exatamente esse caminho; engine_rules_versions = resolved->rules_versions"
  - "FA-01 cobre EXCECAO do motor E consolidado pendente (zona urbanistica pendente SEDUR): ambos -> revisao 1 engine_available=false, ficha vazia, modo manual, auditado 'degradado'. Sem zona o motor nao sugere desfecho (anti-fachada)"
  - "per_cnae com status_sugerido por CNAE pode ser 'analise' (tendencia pendente) num registro de SUCESSO (ex.: consolidado nao_permitido com um CNAE pendente) — engine_available=true; consolidado pendente e que cai em FA-01"
  - "conditions/parking gravados como [] (vazios honestos): o dado completo do motor vive no engine_snapshot; a extracao detalhada e de 10-09/10-17 (NAO ultrapassar o escopo)"
  - "Listener SINCRONO (espelha AvaliarFluxoExpresso, que tambem nao e ShouldQueue) — a pre-analise e idempotente, reprocessar e seguro; sem job dedicado neste plano"
  - "O dispatch em encaminharAnalise (escopo atribuido a este plano) JA estava commitado pela wave paralela (96e7dcd, rotulo 10-07) — confirmado correto (apos a transicao, after-commit); NAO dupliquei (coordenacao file-disjunta)"

patterns-established:
  - "Pre-preenchimento de artefato versionado reusando um resolver puro (insumo) sem recomputo posterior (RN-004): a rev 1 nasce do motor; reabrir nao reexecuta; recalcular e nova revisao (10-09)"

# Metrics
duration: ~8min
completed: 2026-06-14
---

# Phase 10 Plan 08: Pré-análise pelo motor (HU-140) — Summary

**A maior alavanca de produtividade do projeto (HU-140): ao encaminhar um processo à análise humana, o listener AUTO-DESCOBERTO `PreAnalisarProcesso` (reage a `EncaminhadoParaAnalise` por type-hint no `handle`, NUNCA `Event::listen` — travado por CONTAGEM = 1, lição das Fases 8/9) dispara o `PreAnaliseService`, que REEXECUTA o `SolicitacaoViabilityResolver` FRESCO (o MESMO motor real da Fase 9 — sem lógica de decisão paralela, RN-001) e cria a `analysis_records` revisão 1 (rascunho) pré-preenchida: `engine_snapshot` INTEGRAL (`toSnapshot()`, com a zona ANINHADA em `por_cnae[i].consulta.territorio.zona`), `engine_rules_versions` e `per_cnae` com o status sugerido por CNAE (tendência → deferida/indeferida/análise — SUGESTÃO, nunca decisão). É idempotente (RN-004: reprocessar não cria 2ª revisão; recalcular é explícito em 10-09) e degrada honesto (FA-01/CA-03): exceção do motor OU veredito consolidado pendente (zona urbanística pendente SEDUR) → revisão 1 em modo manual (`engine_available=false`, ficha vazia), auditada como `degradado`, sem sugestão inventada. Toda execução é auditada (`analise`/`pre-analise`, RN-005). O gatilho real — `EncaminhadoParaAnalise::dispatch` no `FluxoExpressoService::encaminharAnalise` após a transição `em_analise` — já estava commitado pela wave paralela (96e7dcd), provado ponta a ponta. TDD estrito (RED→GREEN com evidência fresca): `PreAnaliseServiceTest` 5/5 + `PreAnalisarProcessoListenerTest` 5/5 (filtro do plano 10/10, 44 asserções). Suíte completa SQLite 1020/1020 (5151) + grupo postgis 25/25 (144) — zero regressão. ZERO dependência nova.**

## Performance

- **Duration:** ~8 min (início 2026-06-14T22:46:22Z)
- **Tasks:** 2 (PreAnaliseService com TDD; listener auto-descoberto + prova ponta a ponta)
- **Files:** 4 criados (serviço + listener + 2 testes), 0 modificados — ZERO dependência nova

## Contrato (assinaturas — insumo de 10-09 e 10-17)

### `App\Services\Analise\PreAnaliseService`

```php
public function __construct(
    private readonly SolicitacaoViabilityResolver $resolver,
    private readonly AuditService $audit,
) {}

// Cria (ou retorna) a revisao 1 pre-preenchida. Idempotente por revisao.
public function preAnalisar(ViabilityRequest $request): ?AnalysisRecord;
```

Fluxo de `preAnalisar`:
1. Se a **revisão 1 já existe** → no-op (retorna a existente). RN-004.
2. Tenta `$resolved = $resolver->resolve($request)`:
   - **exceção** (Throwable) → FA-01 (modo manual);
   - **`$resolved->consolidado === 'pendente'`** (sem zona) → FA-01 (modo manual);
   - senão → **revisão 1 pré-preenchida** (sucesso).
3. Guarda de corrida: `catch (QueryException)` da `unique(viability_request_id, revision)` devolve a existente.

### Mapeamento tendência → status sugerido (RN-001 — sugestão, não decisão)

| Tendência (`ResultadoViabilidade`) | `status_sugerido` |
|---|---|
| `permitido` | `deferida` (`DecisionOutcome::Deferida`) |
| `permitido_com_condicoes` | `deferida` |
| `nao_permitido` | `indeferida` (`DecisionOutcome::Indeferida`) |
| `pendente` (ou desconhecido) | `analise` |

Mesma semântica do `FluxoExpressoService` (permitido(_com_condicoes)→deferir, nao_permitido→indeferir, pendente→análise). `analise` pode aparecer **por CNAE** num registro de SUCESSO (ex.: consolidado `nao_permitido` com um CNAE pendente); o que cai em FA-01 é o **consolidado** pendente.

### `App\Listeners\PreAnalisarProcesso` (AUTO-DESCOBERTO)

```php
public function __construct(private readonly PreAnaliseService $preAnalise) {}
public function handle(EncaminhadoParaAnalise $event): void
// → $this->preAnalise->preAnalisar($event->request);
```

- Registrado **só** por auto-descoberta (type-hint do evento no `handle`). NUNCA `Event::listen` (duplicaria — lição Fases 8/9). Travado por **CONTAGEM**: `Event::getListeners(EncaminhadoParaAnalise::class)` == 1.
- Síncrono (espelha `AvaliarFluxoExpresso`); a pré-análise é idempotente, então reprocessar é seguro.

## Shape da revisão 1 (`analysis_records`)

### Sucesso (motor disponível, consolidado ≠ pendente)
```jsonc
{
  "revision": 1,
  "status": "rascunho",
  "analyst_user_id": null,
  "engine_available": true,
  "engine_snapshot": {                  // = ResolvedViability::toSnapshot() INTEGRAL
    "ponto": { "lat": -12.97, "lng": -38.51 },
    "area_m2": 120.0,
    "por_cnae": [
      {
        "cnae": "8888881", "cnae_formatado": "8888-8/81", "is_primary": true,
        "tendencia": "permitido", "tendencia_label": "Permitido",
        "consulta": {                   // ConsultaViabilidadeResult::toArray()
          "territorio": { "zona": { "status": "identificado", "nome": "ZR-1", "...": "..." } },
          "enquadramento": { "...": "..." }, "risco": { "...": "..." }, "...": "..."
        }
      }
    ]
  },
  "engine_rules_versions": { "territorio": {...}, "louos": {...}, "risco": {...} },
  "per_cnae": [
    {
      "cnae": "8888881", "cnae_formatado": "8888-8/81", "is_primary": true,
      "tendencia": "permitido", "tendencia_label": "Permitido",
      "status_sugerido": "deferida",    // mapeado da tendencia (RN-001)
      "fluxo": "expresso",
      "fundamentacao": ["Quadro 10 da Lei nº 9.148/2016", "..."]
    }
  ],
  "conditions": [], "parking": [],      // vazios honestos; dado completo no engine_snapshot
  "parecer": null, "finalized_at": null
}
```
> A zona NÃO é top-level: fica em `engine_snapshot.por_cnae[i].consulta.territorio.zona` (chave que o `PrecedentService`/10-06 lê). `conditions`/`parking` ficam `[]` neste plano — a extração detalhada (condicionantes pré-marcadas, vagas req×exigido×vistoria) é de 10-09/10-17 a partir do `engine_snapshot`.

### FA-01 (motor indisponível OU consolidado pendente — modo manual)
```jsonc
{
  "revision": 1, "status": "rascunho", "engine_available": false,
  "engine_snapshot": null, "engine_rules_versions": null, "per_cnae": null,
  "conditions": [], "parking": [], "parecer": null
}
```

### Auditoria (RN-005)
- Sucesso: `log_name=analise`, `event=pre-analise`, `result=sucesso`, `rules_version` representativa (ordem Quadro 10→7→11→11A→risco→território), properties `{viability_request_id, protocol_number, revision, engine_available, consolidado, sugestao_por_cnae}`.
- FA-01: `result=degradado`, `rules_version=null`, properties `{..., engine_available:false, motivo}`.

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-140 CA-01 — ficha pré-preenchida | `PreAnaliseServiceTest::test_cria_revisao_1_pre_preenchida_pelo_motor_real` | rev 1 com engine_snapshot (zona aninhada), rules_versions, per_cnae status_sugerido=deferida |
| RN-001 — sugestão, não decisão | `...::test_status_sugerido_indeferida_quando_zona_proibe` | não permitido → status_sugerido=indeferida |
| HU-140 RN-004 — não reexecuta (idempotência) | `...::test_idempotente_nao_cria_duas_revisoes_1` | 2x → 1 revisão (mesmo id) |
| HU-140 CA-03/FA-01 — degradação (motor) | `...::test_fa01_motor_indisponivel_degrada_em_modo_manual` | exceção → engine_available=false, audit degradado |
| FA-01 — degradação (sem zona) | `...::test_fa01_veredito_pendente_sem_zona_degrada_sem_sugestao_inventada` | consolidado pendente → modo manual, sem sugestão |
| Lição Fases 8/9 — listener travado | `PreAnalisarProcessoListenerTest::test_exatamente_um_listener_auto_descoberto_reage_ao_evento` | `Event::getListeners` == 1 |
| HU-140 — gatilho ponta a ponta | `...::test_encaminhar_para_analise_cria_a_revisao_1_ponta_a_ponta` | `decide()` → em_analise → evento → rev 1 |
| Idempotência via listener | `...::test_reprocessar_o_encaminhamento_nao_duplica_a_revisao` | 2 dispatches → 1 revisão |

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: PreAnaliseService (reuso resolver + mapeamento + FA-01 + auditoria)** — `372e294` (feat) — RED: 5 erros (`Target class [PreAnaliseService] does not exist`) → GREEN: 5/5 (37 asserções).
2. **Task 2: listener auto-descoberto PreAnalisarProcesso** — `97c15ac` (feat) — RED: 0 listeners / 0 revisões (4 falhas) + classe ausente (1 erro) → GREEN: 5/5 (7 asserções). O dispatch em `encaminharAnalise` já estava commitado (96e7dcd, wave paralela) — não dupliquei.

## Decisions Made

- **engine_snapshot integral (`toSnapshot()`)** com a zona ANINHADA — o `PrecedentService` (10-06) lê `por_cnae[i].consulta.territorio.zona`; nunca top-level. `engine_rules_versions = resolved->rules_versions`.
- **FA-01 em dois gatilhos** (exceção do motor E consolidado pendente sem zona) → `engine_available=false`, ficha vazia, modo manual, auditado `degradado`. Sem zona o motor não sugere desfecho (anti-fachada; HU-140 FA-01 "regra ausente").
- **`status_sugerido` espelha a decisão** (RN-001) reusando `DecisionOutcome` (deferida/indeferida) + `analise` para pendente — sugestão, nunca decisão paralela.
- **`conditions`/`parking` = `[]`** (vazios honestos): o dado bruto do motor vive no `engine_snapshot`; a extração detalhada é de 10-09/10-17 — não ultrapassar o escopo.
- **Listener síncrono** (espelha `AvaliarFluxoExpresso`, que também não é `ShouldQueue`); idempotência torna reprocessar seguro. Sem job dedicado neste plano.

## Deviations from Plan

### 1. [Coordenação file-disjunta] O dispatch em `encaminharAnalise` já estava commitado
- O user query atribuiu a este plano adicionar `EncaminhadoParaAnalise::dispatch($request)` no `FluxoExpressoService::encaminharAnalise`. Ao iniciar, o arquivo **já continha o dispatch** (linha 189, após a transição `em_analise`, after-commit), commitado pela wave paralela em `96e7dcd` (rótulo 10-07, que também materializou o SLA HU-144 no mesmo método).
- **O que fiz:** confirmei que o dispatch está correto (após o commit da transição, evento `ShouldDispatchAfterCommit`) e **NÃO o dupliquei nem reescrevi o arquivo** (respeitando a fronteira file-disjunta). A prova ponta a ponta (`test_encaminhar_para_analise_cria_a_revisao_1_ponta_a_ponta`) valida o caminho real em código commitado.
- **Impacto:** nenhum — `files_modified` deste plano ficou só com os 4 arquivos próprios (serviço + listener + 2 testes); o gatilho funciona em produção.

## Authentication Gates

Nenhum — sem CLI/credencial externa neste plano.

## Issues Encountered (cross-plan)

- **Waves paralelas na mesma working dir:** 10-07 (distribuição — `routes/gestao.php`, `CaixaSetorController`, `DistribuirProcessoRequest`, `CaixaSetorTest`) está em disco não commitado. NÃO foram tocados (instrução do user). Staging individual dos meus 4 arquivos (nunca `git add -A`). `STATE.md` NÃO alterado (consolidação a cargo do orquestrador — evita clobber entre executores concorrentes).
- **Impacto transversal do dispatch verificado:** como `encaminharAnalise` agora aciona a pré-análise em TODO `decide()→em_analise`, rodei a suíte completa — verde. A rede de segurança (FA-01 captura toda exceção, `log_name` distinto, sem `viability_decisions`/notificação) garante que o efeito colateral não quebra os testes do fluxo expresso.

## Verification (evidência fresca)

- **RED Task 1:** `--filter=PreAnaliseServiceTest` → 5 erros (`Target class [PreAnaliseService] does not exist`). **GREEN:** 5/5 (37 asserções).
- **RED Task 2:** `--filter=PreAnalisarProcessoListenerTest` → 4 falhas (0 listeners / 0 revisões) + 1 erro (classe ausente). **GREEN:** 5/5 (7 asserções).
- **Filtro do plano:** `--filter="PreAnaliseServiceTest|PreAnalisarProcessoListenerTest"` → **10/10 (44 asserções)**.
- **`vendor/bin/pint` (arquivos do plano)** → passed.
- **Suíte completa SQLite:** `--exclude-group=postgis` → **1020/1020 (5151 asserções)** — zero regressão (inclui o trabalho commitado das waves paralelas).
- **Grupo postgis:** `--group=postgis` → **25/25 (144 asserções)** com `sile-pgsql` healthy — caminho `decide()` sob PostgreSQL real intacto.
- **Auto-descoberta:** `php artisan event:list` lista `App\Events\EncaminhadoParaAnalise` com seu listener; `Event::getListeners` == 1 (teste). Sem `Event::listen` para o evento no código (grep).
- **Greps de aceite:** `resolver->resolve` + `engine_available` em `PreAnaliseService.php`; `handle(EncaminhadoParaAnalise` em `PreAnalisarProcesso.php` (reflection no teste).

## Next Phase Readiness

- **10-09** (ficha/divergências): abre a ficha a partir da revisão 1 (`currentAnalysisRecord`); o analista confirma/diverge do `per_cnae.status_sugerido`; **recalcular** = ação explícita que cria nova revisão (a pré-análise NÃO reexecuta ao reabrir — RN-004). A extração detalhada de condicionantes/vagas do `engine_snapshot` para `conditions`/`parking` acontece aqui.
- **10-17** (ficha SAPS): exibe a sugestão por CNAE + destaque de pendência (FA-01: `engine_available=false` → modo manual com aviso).
- **Bloqueio honesto mantido:** sem a zona oficial (Quadro 10/SEDUR) o consolidado é `pendente` → a rev 1 nasce em modo manual (`engine_available=false`); liga sozinha (sugestão pré-preenchida) quando a base de zoneamento entrar — muda a carga, não a lógica.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
