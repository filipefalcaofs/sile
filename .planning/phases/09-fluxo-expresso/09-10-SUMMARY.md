---
phase: 09-fluxo-expresso
plan: 10
subsystem: expresso
tags: [hu-134, hu-137, fluxo-expresso, bap, indeferimento, rotina-dormente, scheduler, seam, anti-fachada, parametrizacao, after-commit, expresso]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "02"
    provides: "Colunas bap_due_at/bap_linked_at (viability_requests); estado AguardandoBap + transição aguardando_bap→{deferida,indeferida,em_analise} na StateMachine; ViabilityDecision imutável + DecisionOutcome"
  - phase: 09-fluxo-expresso
    plan: "03"
    provides: "Evento ResultadoEmitido (ShouldDispatchAfterCommit); BapRegistry/UnavailableBapRegistry::findLinkage→null + DTO BapLinkage (vínculo dormente, Fase 13)"
  - phase: 09-fluxo-expresso
    plan: "01"
    provides: "Parâmetro administrável expresso.bap.prazo_horas (48) + fallback config/sile.php"
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "Padrão de emissão (decisão + transição + auditoria síncrona na transação; ResultadoEmitido após o commit)"
  - phase: 09-fluxo-expresso
    plan: "08"
    provides: "ComunicarResultadoRegin (parecer ao Regin nos dois desfechos — bloqueado honesto)"
  - phase: 09-fluxo-expresso
    plan: "09"
    provides: "EnviarViabilidadeSefaz (RN-003 — indeferimento NÃO vai à SEFAZ, audita 'ignorado')"
provides:
  - "BusinessDeadlineCalculator::dueAt/isOverdue — seam de prazo (horas-calendário hoje; dias úteis na HU-137 sem tocar call sites)"
  - "IndeferirSemBapService::indeferir(ViabilityRequest): ViabilityDecision — indeferimento por prazo BAP real (decisão 'sem_atuacao_bap'/reason 'indeferido sem atuação' + transição + auditoria + ResultadoEmitido), sem SEFAZ"
  - "Comando expresso:indeferir-sem-bap (agendado hourly/withoutOverlapping/onOneServer) — rotina DORMENTE: no-op em produção até o Regin alimentar bap_due_at (Fase 13)"
  - "ViabilityRequestFactory::awaitingBap(?Carbon $dueAt) — seed de solicitação parada em aguardando_bap (bap_due_at vencido por padrão)"
affects: [09-12, 13]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Rotina dormente honesta: comando + parâmetro + estado existem e o motor é real/testado AGORA (com seed), mas em produção a varredura é NO-OP (zero) porque nada alimenta a antessala (aguardando_bap) — liga sozinho quando o Regin entrar (muda a carga, não a lógica), sem fachada"
    - "Seam de política de prazo (BusinessDeadlineCalculator): a regra de contagem (horas-calendário → dias úteis HU-137) muda num único ponto sem tocar os call sites (comando/serviço só dependem de dueAt/isOverdue)"
    - "Indeferimento PROCEDIMENTAL distinto do veredito locacional: consolidated_result 'sem_atuacao_bap' + per_cnae/rules_versions vazios (não houve reavaliação dos motores) — honesto, o motivo legível mora no reason"
    - "Reuso do caminho de emissão (09-05) e dos efeitos desacoplados (Wave 4): o indeferimento por BAP emite ResultadoEmitido e os listeners existentes tratam Regin (comunica) e SEFAZ (ignora, RN-003) sem código novo"

key-files:
  created:
    - app/Services/Expresso/BusinessDeadlineCalculator.php
    - app/Services/Expresso/IndeferirSemBapService.php
    - app/Console/Commands/ExpressoIndeferirSemBapCommand.php
    - tests/Feature/Expresso/IndeferirSemBapTest.php
    - tests/Feature/Expresso/ExpressoIndeferirSemBapCommandTest.php
  modified:
    - routes/console.php
    - database/factories/ViabilityRequestFactory.php

key-decisions:
  - "consolidated_result = 'sem_atuacao_bap' (marcador honesto), NÃO 'nao_permitido': o indeferimento é por falta de atuação no prazo, não por veredito locacional. Coerente com per_cnae/rules_versions vazios (sem reavaliação dos motores). O plano concedeu a escolha; a retaguarda 09-11 ainda não existe, então nenhum label map quebra."
  - "Guarda de estado lança InvalidArgumentException (signature não-nullable): chamar indeferir() fora de aguardando_bap é erro de programação (o caminho normal é o FluxoExpressoService). Essencial porque protocolada→indeferida É transição válida — sem a guarda, o serviço indeferiria uma protocolada indevidamente."
  - "Comando deriva o vencimento de bap_due_at ?? dueAt(bap_linked_at, prazo): prefere o vencimento gravado e, se só houver a vinculação, calcula pelo prazo parametrizável — usa o seam de verdade, não só lê o parâmetro."
  - "ViabilityDecisionFactory::semAtuacaoBap() (09-02, fixture especulativa) NÃO foi tocada (fora do files_modified do plano); o serviço real produz 'sem_atuacao_bap'/arrays vazios — a fixture é só um artefato de teste do 09-02 e não é consumida por este escopo."
  - "Não há Cache::lock no serviço (diferente do FluxoExpressoService): a idempotência da rotina é o status (indeferida sai da varredura) + withoutOverlapping/onOneServer no scheduler — suficiente e mais simples para a rotina dormente."

patterns-established:
  - "Toda rotina acoplável bloqueada nasce dormente e auditável: o scheduler roda, encontra zero e registra no-op honesto; o teste prova (a) zero indeferimento sem BAP e (b) indeferimento real com seed — a Fase 13 só passa a alimentar a antessala."

# Metrics
duration: ~12min
completed: 2026-06-14
---

# Phase 9 Plan 10: HU-134 — Indeferir por Prazo BAP como Rotina DORMENTE Summary

**A HU-134 entrou como rotina DORMENTE honesta (Wave 5): o comando agendado `expresso:indeferir-sem-bap` varre as solicitações paradas em `aguardando_bap` cujo prazo de atuação na Junta (parâmetro `expresso.bap.prazo_horas`=48) venceu — calculado pelo seam `BusinessDeadlineCalculator` (horas-calendário hoje; a HU-137 troca por dias úteis SEM tocar call sites) — e as INDEFERE de verdade via `IndeferirSemBapService`: numa transação cria a `ViabilityDecision` imutável (outcome Indeferida, `consolidated_result` 'sem_atuacao_bap', `reason` 'indeferido sem atuação', `per_cnae`/`rules_versions`/`fundamentacao` vazios — o indeferimento é PROCEDIMENTAL, não veredito locacional), transiciona `aguardando_bap→indeferida` (timeline+auditoria) e AUDITA SÍNCRONO a decisão; APÓS o commit dispara `ResultadoEmitido` → o Regin é comunicado e a SEFAZ IGNORA (HU-134 RN-003), reusando os listeners da Wave 4 (09-08/09). Em produção é NO-OP: NADA coloca processos em `aguardando_bap` hoje (o `BapRegistry` está indisponível até o Regin, Fase 13), então a varredura encontra ZERO — provado em dev (`Nenhum processo aguardando BAP vencido`) e em teste (protocolada ativa ⇒ zero decisões). O motor é REAL e testado AGORA com seed (`ViabilityRequestFactory::awaitingBap()`): liga sozinho quando o Regin alimentar `bap_due_at` (muda a carga, não a lógica) — sem fachada, sem vínculo BAP simulado. Idempotente/reprocessável (RN-004): agendado `hourly()->withoutOverlapping()->onOneServer()`. TDD estrito (RED→GREEN com evidência fresca): `IndeferirSemBapTest` 3/3 + `ExpressoIndeferirSemBapCommandTest` 3/3 (filtro combinado 6/6, 28 asserções). Suíte completa SQLite 912/912 (4618 asserções) — zero regressão. ZERO dependência nova.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-06-14T18:23:14Z
- **Tasks:** 2 (seam + serviço + factory state; comando + scheduler + no-op/Regin/SEFAZ)
- **Files modified:** 7 (5 criados, 2 modificados) — ZERO dependência nova

## Contrato (assinaturas exatas — insumo de 09-12 e Fase 13)

### `App\Services\Expresso\BusinessDeadlineCalculator`

```php
public function dueAt(\DateTimeInterface $from, int $hours): \Illuminate\Support\Carbon  // addHours (seam HU-137)
public function isOverdue(\DateTimeInterface $dueAt, ?\DateTimeInterface $now = null): bool
```

### `App\Services\Expresso\IndeferirSemBapService`

```php
public function indeferir(ViabilityRequest $request): ViabilityDecision
```

- **Guarda:** `status !== AguardandoBap` ⇒ `InvalidArgumentException` (não cria nada).
- **Grava (DB::transaction):** `ViabilityDecision` (flow 'expresso', outcome Indeferida, `consolidated_result` 'sem_atuacao_bap', `tvl_product_number` null, `per_cnae`/`rules_versions`/`fundamentacao` `[]`, `reason` 'indeferido sem atuação', `decided_by_user_id` null, `decided_at` now) + transição `aguardando_bap→indeferida` (StateMachine) + auditoria SÍNCRONA `expresso`/`decisao`/result `indeferida`.
- **Após o commit:** `ResultadoEmitido::dispatch($request, $decision)` → Regin comunica, SEFAZ ignora (RN-003).

### Comando `expresso:indeferir-sem-bap` (auto-descoberto)

- Lê `Settings::get('expresso.bap.prazo_horas', config('sile.expresso.bap.prazo_horas', 48))`.
- Varre `status===aguardando_bap`; vencimento = `bap_due_at ?? dueAt(bap_linked_at, prazo)`; indefere quando `isOverdue`.
- No-op honesto: nenhuma vencida ⇒ `Nenhum processo aguardando BAP vencido.` + exit 0.
- Agenda em `routes/console.php`: `Schedule::command('expresso:indeferir-sem-bap')->hourly()->withoutOverlapping()->onOneServer();`

### `Database\Factories\ViabilityRequestFactory::awaitingBap(?Carbon $dueAt = null)`

- `status` AguardandoBap, `protocol_number` único, `protocoled_at` now, `bap_due_at` = `$dueAt ?? now()->subHours(72)` (vencido por padrão), `bap_linked_at` null.

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-134 RN-001 — prazo 48h parametrizável (seam) | IndeferirSemBapTest (dueAt/isOverdue) + comando lê `expresso.bap.prazo_horas` | addHours(48) correto; overdue detectado |
| HU-134 RN-002 — motivo 'indeferido sem atuação' + auditoria | IndeferirSemBapTest | reason gravado; Activity 'expresso'/'decisao'/'indeferida' 1× |
| HU-134 RN-003 — Regin sim, SEFAZ não | ExpressoIndeferirSemBapCommandTest (listeners reais) | 'integracoes'/'regin-parecer'/'bloqueado' 1× E 'sefaz-viabilidade'/'ignorado' 1× |
| HU-134 RN-004 — reprocessável/idempotente | ExpressoIndeferirSemBapCommandTest (só vencidas) + agenda | withoutOverlapping/onOneServer; status indeferida sai da varredura |
| Anti-fachada / dormência — nada em aguardando_bap hoje | ExpressoIndeferirSemBapCommandTest (no-op) + dev | protocolada ativa ⇒ ZERO decisões; dev: "Nenhum processo aguardando BAP vencido" |

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: seam + serviço + factory state** — `ab58f6c` (feat) — RED: 3 erros (`awaitingBap()` undefined; `BusinessDeadlineCalculator` not found; `IndeferirSemBapService` does not exist) → GREEN: `IndeferirSemBapTest` 3/3 (15 asserções).
2. **Task 2: comando + scheduler + no-op/Regin/SEFAZ** — `6b6f09b` (feat) — RED: 3 erros (`The command "expresso:indeferir-sem-bap" does not exist.`) → GREEN: `ExpressoIndeferirSemBapCommandTest` 3/3 (13 asserções); `--help` e `schedule:list` OK.

**Plan metadata:** `docs(09-10)` (este SUMMARY + STATE).

## Decisions Made

- **`consolidated_result` = 'sem_atuacao_bap'** (marcador honesto), não 'nao_permitido': o indeferimento é por falta de atuação no prazo, não veredito locacional. Coerente com `per_cnae`/`rules_versions` vazios (não houve reavaliação dos motores). O plano concedeu a escolha; a retaguarda 09-11 ainda não existe (sem label map a quebrar).
- **Guarda lança `InvalidArgumentException`** (signature não-nullable): chamar `indeferir()` fora de `aguardando_bap` é erro de programação. CRÍTICO — `protocolada→indeferida` É transição válida; sem a guarda o serviço indeferiria uma protocolada indevidamente (o caminho normal é o FluxoExpressoService).
- **Comando deriva `bap_due_at ?? dueAt(bap_linked_at, prazo)`**: usa o seam de verdade (não só lê o parâmetro), suportando tanto o vencimento já gravado quanto a derivação pela vinculação.
- **`ViabilityDecisionFactory::semAtuacaoBap()` (09-02) NÃO tocada**: fora do `files_modified` do plano; é fixture de teste do 09-02 (não consumida por este escopo). O serviço real produz a forma honesta.
- **Sem `Cache::lock`** no serviço: a idempotência da rotina é o status (indeferida sai da varredura) + `withoutOverlapping`/`onOneServer` no scheduler.

## Deviations from Plan

None — plano executado exatamente como escrito (2 tasks; seam + serviço + factory state + comando + scheduler + os 6 cenários de teste especificados). A escolha do marcador `consolidated_result` ('sem_atuacao_bap') foi explicitamente concedida pelo plano. Greps de aceite confirmados: `indeferido sem atuação` e `addHours`/PHPDoc HU-137 nos serviços; `awaitingBap` na factory; `expresso:indeferir-sem-bap`/`withoutOverlapping`/`onOneServer`/`expresso.bap.prazo_horas` no comando + routes/console.php.

## Authentication Gates

Nenhum — sem CLI/credencial externa neste plano.

## Issues Encountered

- **Pint removeu um import não usado** (`ViabilityDecision` no `IndeferirSemBapTest` — as asserções usam `assertDatabaseCount` por string). Reconfirmado verde após o ajuste (3/3) antes do commit.
- **Estado real de produção no dev**: `php artisan expresso:indeferir-sem-bap` retornou o no-op honesto ("Nenhum processo aguardando BAP vencido") — coerente com a dormência (nada vincula BAP até a Fase 13).

## Verification (evidência fresca)

- **RED Task 1:** `--filter=IndeferirSemBapTest` → 3 erros (classes/método inexistentes). **GREEN:** 3/3 (15 asserções).
- **RED Task 2:** `--filter=ExpressoIndeferirSemBapCommandTest` → 3 erros (comando inexistente). **GREEN:** 3/3 (13 asserções).
- **Filtro combinado do plano:** `--filter="IndeferirSemBapTest|ExpressoIndeferirSemBapCommandTest"` → **6/6 (28 asserções)**.
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).
- **`php artisan expresso:indeferir-sem-bap --help`** e **`schedule:list`** → comando descoberto e agendado (`0 * * * *`, hourly).
- **Dev (no-op dormente):** `php artisan expresso:indeferir-sem-bap` → "Nenhum processo aguardando BAP vencido." (exit 0).
- **Suíte completa SQLite:** `php artisan test --compact --exclude-group postgis` → **912 testes, 912 passaram (4618 asserções)** — zero regressão (baseline 906 do 09-07/08/09 → +6 deste plano).

## Next Phase Readiness

- **09-11** (retaguarda da decisão): ao listar/detalhar `ViabilityDecision`, tratar o caso `consolidated_result` 'sem_atuacao_bap' + `reason` 'indeferido sem atuação' (label honesto do indeferimento por prazo BAP).
- **09-12** (smoke/fechamento): incluir o comando `expresso:indeferir-sem-bap` na varredura de evidência — em produção/dev ele é no-op honesto (zero), prova de dormência.
- **Fase 13** (ligar o Regin/BAP real): trocar o binding `BapRegistry` no `AppServiceProvider`; o conector passará a alimentar `bap_due_at`/colocar em `aguardando_bap`, e a rotina (já real e testada) começará a indeferir sozinha — sem alterar este código.
- **HU-137** (feriados/dias úteis): substituir a contagem dentro de `BusinessDeadlineCalculator::dueAt` por dias úteis, sem tocar o comando/serviço (seam pronto).
- **Bloqueio honesto mantido:** nenhuma fachada — nada vincula BAP nem coloca em `aguardando_bap` hoje; a varredura é no-op até a Fase 13. O `UnavailableBapRegistry::findLinkage` continua retornando null (vínculo jamais inventado).

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
