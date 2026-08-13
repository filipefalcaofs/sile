---
phase: 10-analise-tecnica-sedur
plan: "09"
subsystem: analise
tags: [hu-135, hu-140, hu-142, hu-085, ficha-versionada, autosave, imutabilidade, rn-003, divergencias, diff, precedentes, server-driven, auditoria, anti-fachada]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur
    plan: "02"
    provides: "analysis_records (revision/status/per_cnae/conditions/parking/parecer/finalized_at; unique viability_request_id+revision) + analysis_divergences + AnalysisRecordStatus + ViabilityRequest::analysisRecords()/currentAnalysisRecord()"
  - phase: 10-analise-tecnica-sedur
    plan: "06"
    provides: "PrecedentService::forRecord(AnalysisRecord): {imovel, cnae_zona} + PrecedentRepository (binding INCONDICIONAL Postgis) + Tests\\Support\\Analise\\FakePrecedentRepository (injetado via instance() em SQLite)"
  - phase: 10-analise-tecnica-sedur
    plan: "08"
    provides: "revisão 1 pré-analisada (engine_snapshot/engine_rules_versions/per_cnae com status_sugerido); recalcular é ação explícita (nova revisão), a pré-análise NÃO reexecuta ao reabrir (RN-004)"
  - phase: 10-analise-tecnica-sedur
    plan: "01"
    provides: "permissão analisar-processos + parâmetros analise.* / config sile.analise.autosave.debounce_ms"
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "padrão server-driven (ResultadoExpressoController) + ViabilityDecisionResource (->resolve() como prop Inertia) + AuditService (RN-002)"
provides:
  - "App\\Services\\Analise\\AnalysisRecordService: current/autosave/finalizar/novaRevisao — abre a revisão vigente, salva o rascunho, finaliza imutável (RN-003) e materializa divergências (HU-140), cria nova revisão (append-only)"
  - "App\\Services\\Analise\\AnalysisRecordDiff::between(AnalysisRecord, AnalysisRecord): array — diff puro entre revisões (só o que mudou; RN-007)"
  - "App\\Services\\Analise\\AnalysisRecordImutavelException — bloqueio de edição/finalização de revisão finalizada (422 no caller)"
  - "App\\Http\\Controllers\\Gestao\\AnalysisRecordController: show/autosave/finalizar/novaRevisao/diff (gated analisar-processos, auditado)"
  - "App\\Http\\Controllers\\Gestao\\PrecedenteController::show — endpoint de precedentes da ficha (HU-142) consumindo PrecedentService"
  - "App\\Http\\Requests\\Gestao\\AnalysisRecordRequest + App\\Http\\Resources\\AnalysisRecordResource — payload da ficha (sugerido×escolhido)"
  - "6 rotas em routes/gestao.php sob processos/{viabilityRequest} (ÚNICO route-owner da Wave 4)"
affects: [10-10, 10-13, 10-17]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Autosave PARCIAL (array_key_exists por campo) preservando o status_sugerido do motor — a sugestão é o insumo da divergência na finalização (sem ela a comparação HU-140 seria impossível)"
    - "Imutabilidade da revisão via guard de domínio (AnalysisRecordImutavelException) traduzido em 422 no controller (abort), não validação — o bloqueio é regra de negócio, não de formulário"
    - "Diff puro e testável (AnalysisRecordDiff sem banco/container) injetado por method-injection no controller — serve o painel sem recomputar"
    - "per_cnae append-merge por CNAE (casa por código, preserva campos do motor) — o autosave nunca perde o engine_snapshot/status_sugerido"
    - "Endpoint de precedentes reusa o FakePrecedentRepository (10-06) injetado via instance() em SQLite — o SQL espacial real fica no @group postgis do 10-06, nunca retorna precedentes vazios silenciosamente"

key-files:
  created:
    - app/Services/Analise/AnalysisRecordService.php
    - app/Services/Analise/AnalysisRecordDiff.php
    - app/Services/Analise/AnalysisRecordImutavelException.php
    - app/Http/Controllers/Gestao/AnalysisRecordController.php
    - app/Http/Controllers/Gestao/PrecedenteController.php
    - app/Http/Requests/Gestao/AnalysisRecordRequest.php
    - app/Http/Resources/AnalysisRecordResource.php
    - tests/Feature/Analise/AnalysisRecordAutosaveTest.php
    - tests/Feature/Analise/AnalysisRecordFinalizeTest.php
    - tests/Feature/Analise/AnalysisRecordRevisionDiffTest.php
    - tests/Feature/Analise/PrecedenteEndpointTest.php
  modified:
    - routes/gestao.php

key-decisions:
  - "Imutabilidade (RN-003) como exceção de domínio + abort(422): autosave/finalizar numa revisão finalizada lança AnalysisRecordImutavelException; o controller a traduz em 422 (não edita silenciosamente)"
  - "Divergências (HU-140) gravadas SÓ quando o final difere do sugerido E ambos existem: concordâncias e itens sem sugestão do motor (FA-01) não geram linha — anti-fachada (field='status' por CNAE)"
  - "novaRevisao COPIA a última revisão em rascunho (revision+1) para reedição; a finalizada permanece intacta (append-only). 'Recalcular' (reexecutar o motor) é variação explícita documentada — repõe engine_snapshot numa nova revisão reusando o PreAnaliseService; NÃO implementada neste plano (mantém escopo)"
  - "show/autosave/finalizar/novaRevisao/diff/precedentes gated por analisar-processos no ÚNICO grupo de rotas processos/{viabilityRequest} da Wave 4; show via Inertia (página em 10-17), as ações/painéis via JSON (consumo XHR pelo painel)"
  - "AnalysisRecordResource expõe editavel = !isFinalizada e per_cnae sugerido×escolhido — payload de leitura honesto (->resolve() como prop Inertia, espelha ViabilityDecisionResource)"

patterns-established:
  - "Ação de domínio que viola invariante (revisão imutável) → exceção de serviço traduzida em 422 pelo controller (abort), reaproveitável pela decisão 10-10"

# Metrics
duration: ~14min
completed: 2026-06-14
---

# Phase 10 Plan 09: Ficha de análise — autosave, finalização imutável, divergências, diff e precedentes (HU-135/140/142) — Summary

**Backend completo da ficha de análise (a superfície da análise humana): `AnalysisRecordService` (abrir a revisão vigente, autosave do rascunho RN-008, finalizar tornando a revisão IMUTÁVEL RN-003 e materializando as divergências analista×motor HU-140, nova revisão append-only), `AnalysisRecordDiff::between` (diff puro entre revisões — só o que mudou, RN-007), o `AnalysisRecordController` (show/autosave/finalizar/nova-revisao/diff) e o `PrecedenteController` (HU-142, consumindo o `PrecedentService` do 10-06), com `AnalysisRecordRequest`/`AnalysisRecordResource` e 6 rotas sob `processos/{viabilityRequest}` (ÚNICO route-owner da Wave 4). Tudo gated por `analisar-processos` (403 auditado no ponto único) e auditado (RN-002); a biblioteca de textos-padrão ativos (HU-085) entra no payload do parecer. TDD estrito (RED→GREEN com evidência fresca): `AnalysisRecordAutosaveTest` 4/4 + `AnalysisRecordFinalizeTest` 5/5 + `AnalysisRecordRevisionDiffTest` 2/2 + `PrecedenteEndpointTest` 3/3 (filtro do plano 14/14, 59 asserções). Suíte completa SQLite 1034/1034 (5210) — zero regressão. ZERO dependência nova. A decisão (deferir/indeferir) NÃO está aqui — é 10-10, a partir da ficha finalizada.**

## Performance
- **Duration:** ~14 min (início 2026-06-14T23:03:46Z)
- **Tasks:** 3 (1 commit atômico por task)
- **Files:** 11 criados + 1 modificado (routes/gestao.php) — ZERO dependência nova

## Rotas (routes/gestao.php — ÚNICO route-owner da Wave 4)

Grupo sob `permission:analisar-processos`, prefixo `processos/{viabilityRequest}`, name `gestao.processos.*`:

| Método | Caminho | Ação | Name | Resposta |
|---|---|---|---|---|
| GET | `processos/{viabilityRequest}/ficha` | `AnalysisRecordController@show` | `processos.ficha.show` | Inertia `gestao/ficha-analise/show` (página em 10-17) |
| PATCH | `processos/{viabilityRequest}/ficha` | `@autosave` | `processos.ficha.autosave` | JSON `{ficha, status}` (422 se finalizada) |
| POST | `processos/{viabilityRequest}/ficha/finalizar` | `@finalizar` | `processos.ficha.finalizar` | JSON `{ficha, status}` (422 se já finalizada) |
| POST | `processos/{viabilityRequest}/ficha/nova-revisao` | `@novaRevisao` | `processos.ficha.nova-revisao` | JSON `{ficha, status}` |
| GET | `processos/{viabilityRequest}/ficha/diff?de={rev}&para={rev}` | `@diff` | `processos.ficha.diff` | JSON `{de, para, diff}` (404 se revisão inexistente) |
| GET | `processos/{viabilityRequest}/precedentes` | `PrecedenteController@show` | `processos.precedentes` | JSON `{imovel, cnae_zona}` |

## Contrato (assinaturas — insumo de 10-10/10-13/10-17)

### `App\Services\Analise\AnalysisRecordService`
```php
public function __construct(private readonly AuditService $audit) {}

// Revisão vigente (maior revision); cria rev 1 vazia (engine_available=false) defensivamente.
public function current(ViabilityRequest $request): AnalysisRecord;

// Autosave PARCIAL do rascunho (RN-008): só os campos presentes; finalizada → AnalysisRecordImutavelException.
// $data: { per_cnae?: [{cnae, status_escolhido, justificativa?, condicionantes?}], conditions?: [], parking?: [], parecer?: ?string }
public function autosave(AnalysisRecord $record, array $data): AnalysisRecord;

// Finaliza (RN-003): status Finalizada + finalized_at=now() + grava analysis_divergences (HU-140); transação + auditoria síncrona. Já finalizada → exceção.
public function finalizar(AnalysisRecord $record, ?User $ator = null): AnalysisRecord;

// Próxima revisão (revision+1, rascunho) copiando a última (edição/recálculo); a finalizada fica intacta.
public function novaRevisao(ViabilityRequest $request, ?User $ator = null): AnalysisRecord;
```

- **Imutabilidade (RN-003):** `autosave`/`finalizar` numa revisão `finalizada` lançam `AnalysisRecordImutavelException` → o controller faz `abort(422)`.
- **Divergências (HU-140 RN-002):** em `finalizar`, para cada item de `per_cnae` com `status_sugerido` e `status_escolhido` ambos presentes e DIFERENTES, grava `AnalysisDivergence` `{analysis_record_id, cnae, field:'status', suggested_value, final_value, justification}` (justification = `per_cnae[i].justificativa`). Concordância ou ausência de sugestão (FA-01) → nenhuma linha.
- **Autosave merge:** `per_cnae` é fundido por `cnae` (preserva `status_sugerido` e campos do motor; aplica `status_escolhido`/`justificativa`/`condicionantes`). `conditions`/`parking`/`parecer` são substituídos quando presentes.

### `App\Services\Analise\AnalysisRecordDiff`
```php
public function between(AnalysisRecord $a, AnalysisRecord $b): array; // só o que mudou
```
Compara campos de topo (`parecer`, `conditions`, `parking`) e o `per_cnae` por CNAE (`status_sugerido`, `status_escolhido`, `condicionantes`, `justificativa`). Puro (sem banco/container).

### Shape do diff (`{campo: {de, para}}`, campos iguais ausentes)
```jsonc
{
  "parecer":     { "de": "Parecer da revisão 1.", "para": "Parecer revisado." },
  "conditions":  { "de": ["..."], "para": ["..."] },   // só se mudou
  "parking":     { "de": {...}, "para": {...} },         // só se mudou
  "per_cnae": {
    "4712100": {
      "status_escolhido": { "de": "deferida", "para": "indeferida" }
      // só os sub-campos que mudaram; CNAE sem mudança não aparece
    }
  }
}
```

### Shape do `AnalysisRecordResource` (`->resolve()` como prop Inertia)
```jsonc
{
  "id": 1, "viability_request_id": 10, "revision": 1,
  "status": "rascunho", "status_label": "Rascunho",
  "editavel": true,                 // false na finalizada (RN-003)
  "engine_available": true,         // false = modo manual (FA-01)
  "per_cnae": [ { "cnae": "4712100", "status_sugerido": "deferida", "status_escolhido": "indeferida", "justificativa": "...", "condicionantes": [...] } ],
  "conditions": [], "parking": {...}, "parecer": "...|null",
  "analyst": "Nome do Analista|null",
  "finalized_at": "ISO-8601|null", "updated_at": "ISO-8601"
}
```

### Payload do `show` (Inertia `gestao/ficha-analise/show` — página em 10-17)
`{ ficha: AnalysisRecordResource, processo: {id, protocol_number, status, status_label}, textosPadrao: [{id, category, content, version}] (só active — HU-085 RN-004), autosaveDebounceMs: int (config sile.analise.autosave.debounce_ms) }`

### Payload de precedentes (`PrecedenteController@show`) — = `PrecedentService::forRecord` (10-06)
`{ imovel: [{viability_request_id, protocol_number, outcome, decided_at, service_type, analyst}] (SEM CPF — LGPD RN-004), cnae_zona: {disponivel, cnae?, zona?, janela_meses?, deferidos?, indeferidos?, total?} | {disponivel:false, motivo} }`

## Onde as divergências são gravadas
`AnalysisRecordService::finalizar()` → `registrarDivergencias()` (dentro da transação, ANTES de marcar `finalized_at`): itera `record.per_cnae`, e para cada CNAE com `status_sugerido !== status_escolhido` cria uma `analysis_divergences`. Insumo direto do relatório HU-145.

## Mapa CA → teste (provado)
| HU / RN | Teste | Evidência |
|---|---|---|
| HU-135 RN-008 — autosave do rascunho | `AnalysisRecordAutosaveTest::test_autosave_atualiza_status_escolhido_e_parecer_no_rascunho` | PATCH grava status_escolhido/parecer; status_sugerido preservado |
| HU-135 RN-009 / HU-085 — textos-padrão no parecer | `...::test_show_abre_a_revisao_atual_com_textos_padrao_ativos` | show traz só os textos ativos |
| CA-04 gating | `...::test_show_exige_analisar_processos_e_audita_o_403` + `PrecedenteEndpointTest::test_precedentes_exige_analisar_processos_e_audita_o_403` | 403 auditado (seguranca/acesso-negado) |
| HU-135 RN-003 — revisão imutável após finalizar | `AnalysisRecordFinalizeTest::test_finalizar_torna_a_revisao_imutavel...` + `...test_autosave_em_revisao_finalizada_e_recusado_com_422` | status finalizada + finalized_at; autosave → 422 |
| HU-140 RN-002 — divergência sugerido×final | `...::test_finalizar_grava_divergencia...` + `...test_finalizar_nao_gera_divergencia_quando_concorda...` | analysis_divergences criado só na divergência |
| nova revisão (append-only) | `...::test_nova_revisao_cria_revision_2_rascunho_copiando_a_finalizada` | rev 2 rascunho copia a finalizada; finalizada intacta |
| auditoria (RN-002) | `...::test_finalizar_e_auditado` | analise/ficha-finalizar |
| HU-135 RN-007 — diff entre revisões | `AnalysisRecordRevisionDiffTest::test_between_retorna_apenas_os_campos_que_mudaram` + `...test_endpoint_diff...` | só mudanças (status do CNAE + parecer); iguais ausentes |
| HU-142 — painel de precedentes | `PrecedenteEndpointTest::test_precedentes_retorna_imovel_sem_cpf_e_cnae_zona_auditado` + `...test_precedentes_degrada_honesto_sem_zona` | imóvel sem CPF + cnae_zona; degradação sem zona; auditado |

## Task Commits
1. **Task 1: ficha abre revisão vigente + autosave do rascunho (HU-135)** — `d330e86` (feat) — RED: 4×404 → GREEN: `AnalysisRecordAutosaveTest` 4/4.
2. **Task 2: finalizar imutável + divergências + nova revisão (HU-135/140)** — `2631ab1` (feat) — RED: 5×404 → GREEN: `AnalysisRecordFinalizeTest` 5/5.
3. **Task 3: diff entre revisões + endpoint de precedentes (HU-135/142)** — `d23c050` (feat) — RED: 4×404 + 1 erro (método ausente) → GREEN: `AnalysisRecordRevisionDiffTest` 2/2 + `PrecedenteEndpointTest` 3/3.

_TDD estrito: RED confirmado pelo motivo certo antes de cada GREEN; commit único por task (teste+implementação coesos), espelhando 10-06/10-08._

## Decisions Made
- **Imutabilidade como exceção de domínio + abort(422)** (não validação) — o bloqueio da revisão finalizada é regra de negócio (RN-003); reaproveitável pela decisão 10-10.
- **Divergências só na divergência real** (sugerido×escolhido presentes e diferentes) — concordâncias e FA-01 (sem sugestão) não poluem `analysis_divergences`; anti-fachada.
- **`novaRevisao` = cópia da última em rascunho** (edição/recálculo); a finalizada nunca é alterada (append-only). **Recalcular** (reexecutar o motor repondo `engine_snapshot`) é variação explícita documentada — NÃO implementada aqui para manter escopo (seria reuso do `PreAnaliseService` numa nova revisão).
- **show via Inertia / ações e painéis via JSON** — a página da ficha é 10-17; autosave/finalizar/nova-revisao/diff/precedentes são consumidos por XHR pelo painel. `autosave` retorna 200 (background save), não redirect.
- **Autosave preserva `status_sugerido`** — sem isso a comparação HU-140 na finalização seria impossível; o merge por CNAE garante que o `engine_snapshot`/sugestão do motor nunca é perdido.

## Deviations from Plan
Nenhum desvio de escopo. Plano executado como escrito; ZERO dependência nova.

Notas de execução (dentro do escopo):
- **`AnalysisRecordImutavelException`** criada (não listada nominalmente em `files_modified`) para materializar o bloqueio RN-003 como regra de domínio (espelha `DistribuicaoException`) — traduzida em 422 no controller.
- **`AnalysisRecordDiff` por method-injection** no `diff` (pura, sem estado) — sem binding necessário.

## Authentication Gates
Nenhum — sem CLI/credencial externa neste plano.

## Issues Encountered (cross-plan)
- Arquivos `.cursor/` não versionados (agents/rules) presentes no working tree — NÃO tocados (fora do escopo). Staging individual dos arquivos do plano (nunca `git add -A`).
- `STATE.md` NÃO alterado (consolidação a cargo do orquestrador — evita clobber entre executores; instrução do user query).

## Verification (evidência fresca)
- **RED Task 1:** `--filter=AnalysisRecordAutosaveTest` → 4×404. **GREEN:** 4/4 (16 asserções).
- **RED Task 2:** `--filter=AnalysisRecordFinalizeTest` → 5×404. **GREEN:** 5/5 (19 asserções).
- **RED Task 3:** `--filter="AnalysisRecordRevisionDiffTest|PrecedenteEndpointTest"` → 4×404 + 1 erro (between ausente). **GREEN:** 5/5 (24 asserções).
- **Filtro do plano:** `--filter="AnalysisRecordAutosaveTest|AnalysisRecordFinalizeTest|AnalysisRecordRevisionDiffTest|PrecedenteEndpointTest"` → **14/14 (59 asserções)**.
- **`vendor/bin/pint --dirty --format agent`** → passed (em cada task).
- **Suíte completa SQLite:** `--exclude-group postgis` → **1034/1034 (5210 asserções)** — zero regressão (baseline 1020 do 10-08 + 14 novos).
- **`php artisan route:list --path=processos`** → 6 rotas sob `processos/{viabilityRequest}` (show/autosave/finalizar/nova-revisao/diff/precedentes), todas no grupo `analisar-processos`.
- **Greps de aceite:** `processos/{viabilityRequest}` + `ficha/diff` + `precedentes` + `analisar-processos` em `routes/gestao.php`; `finalized_at` + `AnalysisDivergence::create` em `AnalysisRecordService.php`; `PrecedentService` em `PrecedenteController.php`.
- _Nota: o caminho espacial real dos precedentes (ST_Intersects) é provado no `PrecedentRepositoryPostgisTest` (@group postgis) do 10-06 — INALTERADO; este plano usa o `FakePrecedentRepository` em SQLite (sem código espacial novo)._

## Next Phase Readiness
- **10-10** (decisão técnica): lê `currentAnalysisRecord` FINALIZADA (status finalizada, `editavel=false`) para montar a `ViabilityDecision` (flow `analise_tecnica`) — a decisão NÃO está aqui; nasce da ficha finalizada. Reusa `AnalysisRecordImutavelException`/padrão 422 se precisar gatear edição.
- **10-13** (TVL PDF): a TVL lê as condicionantes/parecer da ficha finalizada (`conditions`/`parecer`/`per_cnae`).
- **10-17** (ficha SAPS UI): consome `AnalysisRecordResource` (sugerido×escolhido, `editavel`, `engine_available` para modo manual FA-01), o payload de precedentes (`{imovel, cnae_zona}` com degradação), o diff (`{campo:{de,para}}`) e os `textosPadrao` ativos; usa `autosaveDebounceMs` (config) para o debounce do PATCH. Página `gestao/ficha-analise/show` a construir.
- **HU-145** (relatório de divergências — fora desta milestone): consome `analysis_divergences` gravadas na finalização.
- ZERO dependência nova; sem código espacial novo.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
