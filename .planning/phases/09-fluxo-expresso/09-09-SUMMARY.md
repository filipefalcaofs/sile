---
phase: 09-fluxo-expresso
plan: 09
subsystem: integracoes
tags: [hu-110, hu-134, hu-076, fluxo-expresso, sefaz, contrato-bloqueado, anti-fachada, listener, auto-descoberta, after-commit, should-queue, expresso]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "03"
    provides: "SefazViabilidadeGateway + UnavailableSefazViabilidadeGateway (lança SefazUnavailableException) + evento ResultadoEmitido (ShouldDispatchAfterCommit, request+decision)"
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "FluxoExpressoService dispara ResultadoEmitido após o commit; DecisionOutcome (deferida/indeferida) + ViabilityDecision::isDeferida()"
provides:
  - "App\\Listeners\\EnviarViabilidadeSefaz — listener AUTO-DESCOBERTO (ShouldQueue) no ResultadoEmitido que envia a viabilidade à SEFAZ SÓ no deferimento (HU-110)"
  - "Indeferimento ignorado de forma auditável (result 'ignorado') — HU-134 RN-003: SEFAZ não se aplica ao indeferimento"
  - "Bloqueio honesto: captura SefazUnavailableException e audita pendência ('integracoes'/'sefaz-viabilidade'/'bloqueado') — nunca finge envio (anti-fachada)"
affects: [09-10, 09-12, 13]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Efeito desacoplado bloqueado: listener ShouldQueue auto-descoberto consome o contrato (SefazViabilidadeGateway), captura a exceção de indisponibilidade e audita a pendência — a Fase 13 liga a SEFAZ trocando SÓ o binding, sem tocar o listener (espelha o padrão de 09-03/PropertyRegistryLookup)"
    - "Guarda de regra de negócio no listener: só age no deferimento (isDeferida()); o indeferimento é registrado como 'ignorado' (toda saída deixa trilha — RN-002), sem nunca tocar o gateway"
    - "A integração bloqueada NÃO faz a decisão falhar: a exceção é absorvida (não relançada) — a decisão já está efetivada e auditada de forma síncrona pelo 09-05"

key-files:
  created:
    - app/Listeners/EnviarViabilidadeSefaz.php
    - tests/Feature/Expresso/EnviarViabilidadeSefazListenerTest.php
  modified: []

key-decisions:
  - "Listener ShouldQueue (efeito colateral assíncrono, fora do request da decisão), espelhando a intenção de 09-03 (Wave 4 toda em listeners auto-descobertos ShouldQueue); na suíte a fila é sync e o listener roda inline."
  - "Guarda HU-134 RN-003 ANTES de tocar o gateway: indeferida nem resolve a SEFAZ — audita 'ignorado' e retorna. Evita acoplar o indeferimento à integração de saída e prova (spy zero chamadas) que não há envio."
  - "Auditoria de pendência reusa o logName 'integracoes' com event 'sefaz-viabilidade' (distinto do Regin/notificação) — a CONTAGEM por (log_name,event,result) isola este listener dos demais efeitos do mesmo evento, travando a não-duplicação."
  - "Exceção SefazUnavailableException capturada e absorvida (não relançada): a integração bloqueada é pendência honesta, não falha da decisão — coerente com a auditoria síncrona autoritativa do 09-05."

patterns-established:
  - "Integração de saída bloqueada vira efeito auditável: deferida+disponível → 'sucesso'; deferida+bloqueado → 'bloqueado' (pendência); indeferida → 'ignorado'. A Fase 13 só troca o binding; o comportamento de negócio (só deferimento) já está testado."

# Metrics
duration: ~12 min
completed: 2026-06-14
---

# Phase 9 Plan 09: EnviarViabilidadeSefaz — Envio à SEFAZ só no Deferimento (HU-110, bloqueado honesto) Summary

**O efeito desacoplado HU-110 entrou na Wave 4: o listener AUTO-DESCOBERTO `App\Listeners\EnviarViabilidadeSefaz` (ShouldQueue) consome o `ResultadoEmitido` e, SÓ quando a decisão é DEFERIDA, envia os dados de viabilidade à SEFAZ municipal via o contrato `SefazViabilidadeGateway` (09-03). Como a transmissão está BLOQUEADA (sem contrato/homologação), o binding padrão `UnavailableSefazViabilidadeGateway` LANÇA `SefazUnavailableException`; o listener CAPTURA a exceção e AUDITA a pendência (`integracoes`/`sefaz-viabilidade`/result `bloqueado`) — NUNCA finge o envio (anti-fachada). O INDEFERIMENTO NÃO vai à SEFAZ (HU-134 RN-003): o listener nem toca o gateway e registra `ignorado`, mantendo a trilha (RN-002). A exceção de indisponibilidade é absorvida (não relançada) — a decisão já está efetivada e auditada de forma síncrona pelo 09-05; a integração bloqueada não a faz falhar. Registro ÚNICO por auto-descoberta (`event:list` confirma `ResultadoEmitido → EnviarViabilidadeSefaz@handle (ShouldQueue)`); NÃO há `Event::listen` no `AppServiceProvider`, e a não-duplicação é travada por CONTAGEM (1 auditoria por resultado emitido). TDD estrito (RED→GREEN com evidência fresca): `EnviarViabilidadeSefazListenerTest` 3/3 (9 asserções) — deferida+bloqueado audita 'bloqueado' sem quebrar o fluxo; deferida+gateway disponível (spy) chama `sendViabilidade` e audita 'sucesso'; indeferida não chama o gateway (spy zero chamadas) e audita 'ignorado'. Suíte completa SQLite 902/902 (4583 asserções) — zero regressão, mesmo com o listener agora rodando em todo dispatch de `ResultadoEmitido`. ZERO dependência nova; sem adaptador falso.**

## Performance

- **Duration:** ~12 min
- **Tasks:** 1 (listener + feature test)
- **Files modified:** 2 criados — ZERO dependência nova

## O que o listener faz (assinatura + comportamento)

`App\Listeners\EnviarViabilidadeSefaz implements ShouldQueue`

```php
public function __construct(
    private SefazViabilidadeGateway $sefaz,
    private AuditService $audit,
) {}

public function handle(ResultadoEmitido $event): void
```

| Cenário | Gateway | Auditoria (`log_name` / `event` / `result`) |
|---|---|---|
| Decisão DEFERIDA + SEFAZ disponível | `sendViabilidade()` chamado | `integracoes` / `sefaz-viabilidade` / `sucesso` |
| Decisão DEFERIDA + SEFAZ bloqueada (Fase 13) | `sendViabilidade()` lança `SefazUnavailableException` (capturada) | `integracoes` / `sefaz-viabilidade` / `bloqueado` |
| Decisão INDEFERIDA (HU-134 RN-003) | NÃO tocado | `integracoes` / `sefaz-viabilidade` / `ignorado` |

- **Guarda de negócio:** `if (! $event->decision->isDeferida())` audita `ignorado` e retorna ANTES de resolver/chamar o gateway.
- **Propriedades auditadas** (em todos os caminhos): `viability_request_id`, `protocol_number`, `outcome`; `subject` = a `ViabilityRequest`.
- **Não relança** a `SefazUnavailableException` — a decisão não falha pela integração bloqueada.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes do commit):

1. **Task 1: listener EnviarViabilidadeSefaz (só deferida; bloqueado → audita pendência; indeferida ignora)** — `7e84037` (feat) — RED: 3 falhas pelo motivo certo (sem o listener, 0 auditorias e gateway nunca chamado: "0 is identical to 1" / "called 0 times") → GREEN: `EnviarViabilidadeSefazListenerTest` 3/3 (9 asserções), pint passed.

**Plan metadata:** `docs(09-09)` (este SUMMARY + STATE).

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-110 / HU-076 RN-008 — envia viabilidade à SEFAZ no deferimento | EnviarViabilidadeSefazListenerTest (sucesso com spy) | `sendViabilidade` chamado 1×; audita 'sucesso' |
| HU-134 RN-003 — indeferimento NÃO envia à SEFAZ | EnviarViabilidadeSefazListenerTest (indeferida ignora) | spy zero chamadas; audita 'ignorado' |
| HU-076 FA-03 / anti-fachada — bloqueado audita pendência | EnviarViabilidadeSefazListenerTest (bloqueado) | result 'bloqueado' 1×; sem exceção que quebre o fluxo |
| RN-002 (lição Fase 8) — sem duplicação | EnviarViabilidadeSefazListenerTest (CONTAGEM) + grep sem Event::listen | `event:list` mostra 1 registro auto-descoberto; AppServiceProvider sem o listener |

## Decisions Made

- **ShouldQueue (efeito assíncrono):** espelha a intenção do 09-03 (Wave 4 toda em listeners auto-descobertos ShouldQueue). Na suíte a fila é `sync`, então o listener roda inline no dispatch — o `Queue::fake` parcial do `TestCase` base intercepta SÓ o `DecidirFluxoExpressoJob`, não o `CallQueuedListener` deste listener.
- **Guarda RN-003 antes do gateway:** o indeferimento nem resolve a SEFAZ — audita 'ignorado' e retorna. Mantém o indeferimento desacoplado da integração de saída e é provável por spy (zero chamadas).
- **Auditoria de pendência absorve a exceção:** `SefazUnavailableException` é capturada e NÃO relançada — pendência honesta, não falha da decisão (a auditoria autoritativa da decisão já é síncrona, 09-05).
- **Isolamento por (log_name, event, result):** event 'sefaz-viabilidade' distingue este listener do Regin (09-08) e da notificação (09-07) no MESMO evento — a CONTAGEM trava a não-duplicação sem colidir com os outros efeitos.

## Deviations from Plan

None — plano executado exatamente como escrito (1 task; listener + 1 feature test com os 3 cenários especificados). Greps de aceite confirmados: `SefazUnavailableException`, `isDeferida`, `result: 'bloqueado'`, `result: 'ignorado'` presentes no listener; `AppServiceProvider` NÃO referencia o listener (auto-descoberta).

## Authentication Gates

Nenhum — sem CLI/credencial externa neste plano.

## Issues Encountered

- **Coordenação Wave 4 (3 listeners no MESMO `ResultadoEmitido`):** 09-07 (notificação) já estava commitado e 09-08 (`ComunicarResultadoRegin`) estava como arquivos não-commitados na working dir. Toquei SÓ os meus arquivos (`EnviarViabilidadeSefaz` + teste), staging individual (`git add` nominal — nunca `git add -A`); os arquivos do 09-08 permaneceram intactos/untracked. Os três listeners coexistem por auto-descoberta sem duplicar (a suíte verde os exercitou juntos; meu teste isola pelo event 'sefaz-viabilidade').
- **Listener agora roda em TODO dispatch de `ResultadoEmitido`:** confirmei a ausência de regressão rodando a suíte completa (902/902) — o efeito extra (audita 'bloqueado' nos testes do motor 09-05/06) é inócuo (linha de auditoria 'integracoes' adicional, não asserida pelos testes da decisão).

## Verification (evidência fresca)

- **RED:** `--filter=EnviarViabilidadeSefazListenerTest` → 3 falhas pelo motivo certo (sem listener: "Failed asserting that 0 is identical to 1" nos audits; spy "called 0 times").
- **GREEN:** `--filter=EnviarViabilidadeSefazListenerTest` → **3/3 (9 asserções)**.
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **Greps de aceite:** `SefazUnavailableException` + `isDeferida` + `result: 'bloqueado'` + `result: 'ignorado'` + `result: 'sucesso'` + `ShouldQueue` no listener; `AppServiceProvider` SEM `EnviarViabilidadeSefaz` (auto-descoberta).
- **Auto-descoberta:** `php artisan event:list` → `App\Events\ResultadoEmitido` ⇂ `App\Listeners\EnviarViabilidadeSefaz@handle (ShouldQueue)` (registro único, sem Event::listen).
- **Suíte completa SQLite:** `php artisan test --compact --exclude-group postgis` → **902 testes, 902 passaram (4583 asserções)** — zero regressão (inclui o trabalho commitado dos planos paralelos da Wave 4).

## Next Phase Readiness

- **09-10** (HU-134 dormente — indeferimento por prazo BAP): o indeferimento sem atuação BAP emite `ResultadoEmitido` → este listener IGNORA a SEFAZ (HU-134 RN-003), comportamento já testado (indeferida não aciona o gateway).
- **09-12** (smoke do fluxo automático): no deferimento, a trilha registra a pendência SEFAZ ('integracoes'/'sefaz-viabilidade'/'bloqueado'); no indeferimento, registra 'ignorado'. Evidência honesta de que o canal está bloqueado, não simulado.
- **Fase 13** (ligar a SEFAZ conveniada): trocar SÓ o binding `SefazViabilidadeGateway` no `AppServiceProvider` — o listener passa a auditar 'sucesso' sem nenhuma alteração de código (caminho já provado com gateway disponível por spy).

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
