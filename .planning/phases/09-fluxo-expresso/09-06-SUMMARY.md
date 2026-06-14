---
phase: 09-fluxo-expresso
plan: 06
subsystem: expresso
tags: [hu-073, hu-074, hu-075, hu-076, hu-134, fluxo-expresso, gatilho, listener-auto-descoberto, job-fila, shouldqueue, scheduler, rede-de-seguranca, idempotencia, rn-002, anti-fachada, expresso]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "FluxoExpressoService::decide(ViabilityRequest, ?User): DecisionResult — motor idempotente/autoritativo que o job e o comando consomem"
  - phase: 09-fluxo-expresso
    plan: "01"
    provides: "config/sile.php expresso.fila / expresso.job.* (tries/timeout/backoff) / expresso.lock — constantes técnicas da resiliência"
  - phase: 08-solicitacao-viabilidade
    plan: "(protocolo)"
    provides: "Evento SolicitacaoProtocolada (after-commit) + padrão de listener auto-descoberto (RegistrarTrilhaProtocolo) + scheduler withoutOverlapping/onOneServer (routes/console.php)"
provides:
  - "App\\Listeners\\AvaliarFluxoExpresso — gatilho AUTO-DESCOBERTO no SolicitacaoProtocolada que DESPACHA o DecidirFluxoExpressoJob (não decide inline); registro único, sem Event::listen"
  - "App\\Jobs\\DecidirFluxoExpressoJob (ShouldQueue) — chama FluxoExpressoService::decide com guard idempotente; tries/timeout/backoff/fila de config; failed() audita (RN-002)"
  - "App\\Console\\Commands\\ExpressoReavaliarCommand (expresso:reavaliar {--limit=}) — rede de segurança que redespacha protocoladas órfãs (sem decisão); no-op honesto; agendado everyTenMinutes withoutOverlapping/onOneServer"
  - "Harness de teste: TestCase fakeia só DecidirFluxoExpressoJob por padrão (opt-out $fakeExpressoDecisionJob) — desacopla testes de protocolo da decisão assíncrona, sem tocar a Fase 8"
affects: [09-07, 09-08, 09-09, 09-12]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Gatilho desacoplado: listener AUTO-DESCOBERTO no evento de domínio apenas DESPACHA um job ShouldQueue (não executa a lógica inline) — protocolo rápido + resiliência da fila; registro único por auto-descoberta, travado por CONTAGEM (1 job por evento), NUNCA Event::listen (lição Fase 8)"
    - "Fila/retry/timeout/backoff do job lidos de config/sile.php no construtor — fonte ÚNICA da fila no próprio job, de modo que gatilho e rede de segurança enfileiram no mesmo lugar (sem duplicar a escolha da fila)"
    - "Rede de segurança idempotente: comando agendado (withoutOverlapping/onOneServer) reusa o MESMO job para reprocessar órfãs (status protocolada SEM decision) — seguro porque o service é idempotente; no-op honesto quando não há órfãs"
    - "Isolamento de side-effect assíncrono em teste: fila sync rodaria o job inline; o TestCase base fakeia SÓ esse job (opt-out por propriedade) — testes de protocolo não acoplam à decisão; nenhum arquivo da fase consumidora alterado"

key-files:
  created:
    - app/Listeners/AvaliarFluxoExpresso.php
    - app/Jobs/DecidirFluxoExpressoJob.php
    - app/Console/Commands/ExpressoReavaliarCommand.php
    - tests/Feature/Expresso/AvaliarFluxoExpressoListenerTest.php
    - tests/Feature/Expresso/DecidirFluxoExpressoJobTest.php
    - tests/Feature/Expresso/ExpressoReavaliarCommandTest.php
  modified:
    - routes/console.php
    - tests/TestCase.php
    - tests/Feature/Jobs/ImportRedesimJobTest.php

key-decisions:
  - "A fila do job é fonte ÚNICA de verdade no construtor do DecidirFluxoExpressoJob (lê config('sile.expresso.fila')); o listener e o comando apenas dispatcham. Evita duplicar a lógica de onQueue no gatilho e na rede de segurança e garante consistência operacional (ambos na mesma fila)."
  - "failed() audita logName 'expresso' / event 'decisao-falha' / result 'falha' com viability_request_id + erro (espelha ImportRedesimJob::failed) — RN-002/FA-03: falha esgotada nunca é silenciosa; o job NÃO grava decisão falsa."
  - "Guard idempotente no job (null OU status != Protocolada → no-op) é uma otimização — o FluxoExpressoService também re-checa sob Cache::lock; reprocessar (reavaliar) é sempre seguro."
  - "expresso:reavaliar filtra status===protocolada + whereDoesntHave('decision') (órfãs) e redespacha o MESMO job (não decide inline no comando) — consistência total com o gatilho; --limit protege lotes grandes; no-op honesto."
  - "Isolamento de teste no TestCase base (fake só do DecidirFluxoExpressoJob, opt-out $fakeExpressoDecisionJob) em vez de editar os ~7 testes de protocolo da Fase 8 — honra 'protocolo da Fase 8 NÃO alterado' (zero arquivo/lógica da Fase 8 tocado) e reflete que em produção a decisão é assíncrona (fora do request)."

patterns-established:
  - "Adicionar um side-effect assíncrono a um evento de domínio muito usado (protocolo) exige isolar a fila nos testes que disparam o evento — feito no harness base (fake do job específico, opt-out para quem usa o worker real)."

# Metrics
duration: ~23min
completed: 2026-06-14
---

# Phase 9 Plan 06: Gatilho e Orquestração do Fluxo Expresso (listener → job + rede de segurança)

**O fluxo expresso ganhou seu GATILHO real: o listener AUTO-DESCOBERTO `AvaliarFluxoExpresso` pendura no `SolicitacaoProtocolada` (Fase 8) e apenas DESPACHA o `DecidirFluxoExpressoJob` (ShouldQueue) — não decide inline, mantendo o protocolo rápido e ganhando a resiliência da fila (retry/timeout/backoff de `config/sile.php`). Registro ÚNICO por auto-descoberta (`event:list` mostra `RegistrarTrilhaProtocolo` + `AvaliarFluxoExpresso` ligados ao evento), NUNCA `Event::listen` (lição Fase 8 — RN-002), travado por CONTAGEM (exatamente 1 job por protocolo). O job carrega só o id (serialização segura), recarrega o estado fresco, tem guard idempotente (no-op fora de protocolada — o service também re-checa sob `Cache::lock`) e chama `FluxoExpressoService::decide`; `failed()` audita 'expresso'/'decisao-falha' result 'falha' (FA-03 — nunca silenciosa). O comando/scheduler `expresso:reavaliar {--limit=}` é a REDE DE SEGURANÇA (não o gatilho principal): varre protocoladas SEM decisão (`whereDoesntHave('decision')` — órfãs) e redespacha o MESMO job, agendado `everyTenMinutes()->withoutOverlapping()->onOneServer()` — no-op honesto quando não há órfãs. TDD estrito (RED→GREEN com evidência fresca): listener 3/3 (contagem + id + fila parametrizada), job 6/6 (config; decisão real via service; no-op; failed audita), comando 3/3 (só órfãs; no-op; --limit). Suíte completa SQLite 889/889 (4556 asserções) + grupo postgis 21/21 (121) — zero regressão. ZERO dependência nova.**

## Performance
- **Duration:** ~23 min
- **Started:** 2026-06-14T17:23:31Z
- **Completed:** 2026-06-14T17:46:30Z
- **Tasks:** 2 (gatilho listener + job resiliente; comando + scheduler de segurança) + correção de harness de teste

## Contrato para os planos seguintes (assinaturas exatas)

### `App\Listeners\AvaliarFluxoExpresso` (auto-descoberto)
```php
public function handle(SolicitacaoProtocolada $event): void
// → DecidirFluxoExpressoJob::dispatch($event->request->id);  (a fila é do job)
```

### `App\Jobs\DecidirFluxoExpressoJob` (ShouldQueue)
```php
public int $tries;        // config('sile.expresso.job.tries', 3)
public int $timeout;      // config('sile.expresso.job.timeout', 120)
public array $backoff;    // config('sile.expresso.job.backoff', [30,60,120])
public function __construct(public readonly int $viabilityRequestId) // + onQueue(config('sile.expresso.fila')) se != 'default'
public function handle(FluxoExpressoService $service): void          // guard: null|status!=Protocolada → no-op; senão decide()
public function failed(?Throwable $exception): void                  // audita 'expresso'/'decisao-falha'/'falha' (id+erro)
```

### `App\Console\Commands\ExpressoReavaliarCommand`
```php
protected $signature = 'expresso:reavaliar {--limit=}';
// ViabilityRequest where status=Protocolada whereDoesntHave('decision') [->limit] → DecidirFluxoExpressoJob::dispatch(id)
// vazio → info('Nenhuma solicitação pendente de decisão.') exit 0
```
Agenda (`routes/console.php`): `Schedule::command('expresso:reavaliar')->everyTenMinutes()->withoutOverlapping()->onOneServer();`

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: listener auto-descoberto + DecidirFluxoExpressoJob** — `5ba0979` (feat) — RED: job inexistente (6 erros) + protocolo dispara evento mas 0 jobs (3 falhas — confirma que o after-commit roda em teste) → GREEN: 9/9 (19 asserções).
2. **Task 2: comando + scheduler expresso:reavaliar (+ refactor da fila para o job)** — `f8a4db0` (feat) — RED: "command expresso:reavaliar does not exist" (3 erros) → GREEN: 12/12 (30 asserções) combinado com a Task 1.
3. **Correção de harness (isolamento do job de decisão nos testes de protocolo)** — `e97d2a4` (test) — necessária porque a fila sync rodava a decisão inline (regressão de 12 testes Fase 8); fix no TestCase base + opt-out no ImportRedesimJobTest. Nenhum arquivo/lógica da Fase 8 tocado.

**Plan metadata:** `docs(09-06)` (este SUMMARY + STATE).

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-073/074/075 — decisão acionada no protocolo (gatilho) | AvaliarFluxoExpressoListenerTest | 1 job por protocolo (CONTAGEM) + id correto |
| RN-002 (lição Fase 8) — sem duplicação | AvaliarFluxoExpressoListenerTest + event:list | 1 job (auto-descoberta) + AppServiceProvider sem AvaliarFluxoExpresso |
| HU-076 FA-03 — resiliência e falha auditada | DecidirFluxoExpressoJobTest | tries/backoff de config; failed audita 'falha' |
| Anti-fachada — o job decide DE VERDADE | DecidirFluxoExpressoJobTest | handle() via service real defere (decision + status) |
| Idempotência — no-op seguro | DecidirFluxoExpressoJobTest | fora de protocolada/inexistente → service não chamado |
| HU-134 RN-004 / robustez — reprocessar órfãs | ExpressoReavaliarCommandTest | reenfileira só órfãs; no-op; --limit |

## Decisions Made

- **Fila como fonte única no job**: `DecidirFluxoExpressoJob` lê `config('sile.expresso.fila')` no construtor e faz `onQueue` quando != 'default'. O listener e o comando apenas `dispatch($id)` — sem duplicar a escolha da fila e garantindo que o gatilho e a rede de segurança enfileirem no mesmo lugar. (Refatorado na Task 2; o listener da Task 1 deixou de fazer onQueue.)
- **failed() audita pelo AuditService** ('expresso'/'decisao-falha'/'falha', id+erro), espelhando `ImportRedesimJob::failed` — RN-002/FA-03; nunca decisão falsa.
- **Guard idempotente no job** é otimização; a garantia real é o `Cache::lock` + re-check do service (09-05). Reavaliar é sempre seguro.
- **`expresso:reavaliar` só órfãs** (`status===protocolada` + `whereDoesntHave('decision')`) e redespacha o MESMO job (não decide inline) — consistência com o gatilho; `--limit` protege lotes grandes.
- **Isolamento de teste no harness, não na Fase 8**: o `TestCase` base fakeia SÓ o `DecidirFluxoExpressoJob` por padrão (opt-out `$fakeExpressoDecisionJob`). Honra "protocolo da Fase 8 NÃO alterado" (zero arquivo/lógica da Fase 8 tocado) e reflete a realidade de produção (decisão assíncrona, fora do request).

## Deviations from Plan

- **[Rule 3 — bloqueio] Isolamento do job de decisão nos testes de protocolo.** O plano assumiu que adicionar o listener manteria a suíte verde sem mexer na Fase 8. Na prática, com a fila `sync` (phpunit.xml) o `DecidirFluxoExpressoJob` roda INLINE quando o listener o despacha; como os testes de protocolo da Fase 8 não configuram zona, o motor real (corretamente) roteia para `em_analise`, mudando o status logo após o protocolo — 12 testes da Fase 8 falharam (causa-raiz investigada e confirmada via phpunit.xml + leitura dos erros). Em produção a fila é assíncrona, então o protocolo permanece `protocolada` até um worker decidir; o `sync` colapsa esse desacoplamento. **Fix (commit `e97d2a4`)**: o `TestCase` base fakeia SÓ o `DecidirFluxoExpressoJob` por padrão (opt-out `$fakeExpressoDecisionJob` desligado no `ImportRedesimJobTest`, que exercita o worker real). Nenhum arquivo nem lógica da Fase 8 alterado.
- **Correções de asserção própria durante o GREEN** (testes, não produção): (a) `failed()` audita JSON com escape unicode (`\u00e3`) — a asserção passou a decodificar o JSON e comparar o array; (b) relação no `ViabilityDecision` é `viabilityRequest` (não `request`) — o teste do comando passou a criar request+decisão explicitamente com número de protocolo único (factory `protocoled()` usa número fixo, e `protocol_number` é unique); (c) mensagem de no-op é "Nenhuma..." (maiúscula correta) — asserção alinhada.
- **Refactor da fila** (job vs listener): a fila migrou do listener (Task 1) para o construtor do job (Task 2) como fonte única — melhora de consistência/DRY, sem mudar comportamento observável (testes seguem verdes).

## Issues Encountered

- **Fila sync × side-effect assíncrono** (detalhado em Deviations): resolvido no harness de teste, sem tocar a Fase 8.
- **`event:list` confirma a auto-descoberta**: `SolicitacaoProtocolada → RegistrarTrilhaProtocolo@handle + AvaliarFluxoExpresso@handle` (registro único, sem Event::listen).
- **`schedule:list` confirma a agenda**: `*/10 * * * * php artisan expresso:reavaliar` (withoutOverlapping/onOneServer).

## Verification (evidência fresca)

- **RED Task 1:** `--filter="AvaliarFluxoExpressoListenerTest|DecidirFluxoExpressoJobTest"` → job inexistente (6 erros) + 0 jobs no protocolo (3 falhas). **GREEN:** 9/9 (após corrigir a asserção JSON do failed).
- **RED Task 2:** `--filter=ExpressoReavaliarCommandTest` → "command does not exist" (3 erros). **GREEN:** 12/12 combinado (30 asserções).
- **Filtro do plano (fresco):** `--filter="AvaliarFluxoExpressoListenerTest|DecidirFluxoExpressoJobTest|ExpressoReavaliarCommandTest"` → **12/12 (30 asserções)**.
- **Suíte completa SQLite:** `--exclude-group=postgis` → **889 testes, 889 passaram** (4556 asserções) — baseline 877 (09-05) + 12 deste plano, zero regressão.
- **Grupo postgis completo:** `--group=postgis` → **21/21 (121 asserções)** com `sile-pgsql` healthy.
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).
- **Greps de aceite:** `DecidirFluxoExpressoJob::dispatch` no listener; AppServiceProvider SEM `AvaliarFluxoExpresso` (só comentário sobre Event::listen); `ShouldQueue`+`FluxoExpressoService` no job; `expresso:reavaliar`+`whereDoesntHave('decision')` no comando; `withoutOverlapping`+`onOneServer` na agenda.
- **`php artisan expresso:reavaliar --help`** → exit 0 (signature + --limit).

## Next Phase Readiness

- **09-12** (smoke do fluxo automático protocolar→decidir): o caminho real está pronto (protocolo → listener → job → service). Para exercitar a decisão ponta a ponta, o smoke deve processar a fila (`queue:work`) OU desligar o fake do harness (`$fakeExpressoDecisionJob = false`) e rodar com sync.
- **Wave 4 (09-07/08/09)**: listeners AUTO-DESCOBERTOS no `ResultadoEmitido` (notificação HU-077, Regin HU-104 bloqueado, SEFAZ HU-110 bloqueado) — mesmo padrão de auto-descoberta deste gatilho.
- **Operação**: em produção a fila NÃO é sync — a decisão roda por worker, fora do request do protocolo (resiliência); `expresso:reavaliar` (a cada 10 min) recupera órfãs por falha/perda. `config('sile.expresso.fila')` permite uma fila dedicada sem deploy.
- **Bloqueio honesto mantido (09-05):** sem a zona oficial (Quadro 10/SEDUR) a decisão roteia para `em_analise` SEM decidir/emitir; liga sozinha quando a base entrar (muda a carga, não a lógica).

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
