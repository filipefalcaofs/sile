---
phase: 09-fluxo-expresso
plan: 08
subsystem: integracoes
tags: [hu-104, hu-076, fluxo-expresso, regin, contrato-bloqueado, anti-fachada, listener, auto-descoberta, should-queue, evento-dominio, expresso]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "03"
    provides: "ReginParecerNotifier + UnavailableReginParecerNotifier (lança ReginUnavailableException) + evento ResultadoEmitido (request + decision)"
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "FluxoExpressoService dispara ResultadoEmitido após o commit (deferimento/indeferimento) — o gatilho real deste listener"
provides:
  - "Listener AUTO-DESCOBERTO App\\Listeners\\ComunicarResultadoRegin (ShouldQueue) no ResultadoEmitido (HU-104)"
  - "Caminho BLOQUEADO honesto: captura ReginUnavailableException e audita pendência (integracoes/regin-parecer, result 'bloqueado') — nunca sucesso fictício"
  - "Caminho de SUCESSO ligável: chama ReginParecerNotifier::notifyParecer e audita 'sucesso' (provado com fake; Fase 13 só troca o binding)"
  - "Parecer comunicado ao Regin nos DOIS desfechos (deferido e indeferido — HU-076 RN-008)"
affects: [09-10, 09-12, 13]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Listener de integração de saída bloqueada (espelha o padrão do contrato Unavailable): try { notify } catch (Unavailable) { audita 'bloqueado' } — a exceção NÃO é relançada (a decisão, já gravada/auditada síncrona, jamais falha por causa da integração pendente); a pendência auditada é a falha visível (outbox/trilha)"
    - "Efeito desacoplado ShouldQueue auto-descoberto no segundo evento de domínio (type-hint no handle; NÃO Event::listen — lição Fase 8) — a Fase 13 liga o canal real trocando SÓ o binding, sem tocar este listener"

key-files:
  created:
    - app/Listeners/ComunicarResultadoRegin.php
    - tests/Feature/Expresso/ComunicarResultadoReginListenerTest.php
  modified: []

key-decisions:
  - "O listener é ShouldQueue (integração externa roda na fila — não atrasa nem derruba a decisão); a exceção de indisponibilidade é capturada e auditada, JAMAIS relançada para fora do handle."
  - "Auditoria da pendência/sucesso usa o mesmo logName 'integracoes' + event 'regin-parecer'; o result ('bloqueado' vs 'sucesso') é o que distingue — facilita a varredura de pendências (09-12) e o corte limpo na Fase 13."
  - "properties carregam viability_request_id + protocolo + outcome (e 'erro' no bloqueado) com subject = a solicitação — a trilha aponta para o agregado certo (RN-002)."
  - "Caminho de sucesso provado com um fake do notifier SÓ no teste (prática padrão) — prova de que a lógica liga sozinha quando a Fase 13 trocar o binding; ZERO adaptador falso em runtime."

patterns-established:
  - "Listener consumidor de contrato de saída bloqueado: o efeito nasce completo (sucesso + bloqueado) e auditado; a Fase 13 não escreve código novo no consumidor, só troca o provider no AppServiceProvider."

# Metrics
duration: ~12 min
completed: 2026-06-14
---

# Phase 9 Plan 08: Comunicação do Parecer ao Regin/Junta (HU-104, bloqueado honesto) Summary

**O efeito desacoplado da HU-104 entrou (Wave 4, track ‖): o listener AUTO-DESCOBERTO `App\Listeners\ComunicarResultadoRegin` (ShouldQueue) pendura no SEGUNDO evento de domínio `ResultadoEmitido` e tenta comunicar o parecer — deferido OU indeferido (HU-076 RN-008: o parecer vai ao Regin nos dois casos) — ao integrador Regin/Junta via o contrato `ReginParecerNotifier`. Como a integração está BLOQUEADA (Fase 13), o binding atual `UnavailableReginParecerNotifier` LANÇA `ReginUnavailableException`; o listener CAPTURA a exceção e AUDITA a pendência de integração (logName `integracoes`, event `regin-parecer`, result `bloqueado`, subject = a solicitação) — NUNCA registra sucesso fictício, NUNCA simula o envio (anti-fachada/entrega-funcional). A exceção NÃO é relançada para fora do `handle`: a decisão, já gravada e auditada SÍNCRONA (HU-078), jamais falha por causa da integração pendente — a pendência `bloqueado` é a falha visível (outbox/trilha). O caminho de SUCESSO está implementado e provado com um fake do notifier nos testes (chama `notifyParecer` e audita `sucesso`), demonstrando que a lógica liga sozinha quando a Fase 13 trocar SÓ o binding no `AppServiceProvider`, sem tocar este listener. Registro ÚNICO por auto-descoberta (`event:list` mostra `ResultadoEmitido → ComunicarResultadoRegin@handle (ShouldQueue)`); NUNCA `Event::listen` (lição Fase 8 — RN-002), travado por CONTAGEM no teste. TDD estrito (RED→GREEN com evidência fresca): `ComunicarResultadoReginListenerTest` 3/3 (10 asserções) — bloqueado audita 1×, sucesso com fake, indeferimento também comunica. Suíte completa SQLite 902/902 (4583 asserções) — zero regressão, com o listener rodando de verdade no caminho real do `ResultadoEmitido` (testes de decisão não-fakeados). ZERO dependência nova.**

## Performance

- **Duration:** ~12 min
- **Tasks:** 1 (listener auto-descoberto + caminho bloqueado/sucesso/indeferida)
- **Files modified:** 2 criados — ZERO dependência nova

## Contrato do listener (comportamento exato — insumo de 09-10/09-12 e Fase 13)

### `App\Listeners\ComunicarResultadoRegin` (auto-descoberto, ShouldQueue)

```php
public function __construct(private ReginParecerNotifier $regin, private AuditService $audit) {}

public function handle(ResultadoEmitido $event): void
{
    // try { $this->regin->notifyParecer($request, $decision); audita 'sucesso' }
    // catch (ReginUnavailableException $e) { audita 'bloqueado' (com 'erro') } — NÃO relança
}
```

| Caminho | Binding | Auditoria (`integracoes`/`regin-parecer`) | properties |
|---|---|---|---|
| BLOQUEADO (hoje) | `UnavailableReginParecerNotifier` lança | result `bloqueado` | `viability_request_id`, `protocolo`, `outcome`, `erro` |
| SUCESSO (Fase 13) | provider conveniado | result `sucesso` | `viability_request_id`, `protocolo`, `outcome` |

- `subject` = a `ViabilityRequest` (a trilha aponta para o agregado certo, RN-002).
- Funciona para `outcome` **deferida E indeferida** (RN-008 — o parecer vai ao Regin nos dois casos).
- A exceção é capturada, **nunca relançada**: a integração bloqueada não quebra o efeito do resultado.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes do commit):

1. **Task 1 (RED):** `test(09-08)` — `284d5d2` — `ComunicarResultadoReginListenerTest` escrito; RED: 3 falhas pelo motivo certo (`actual size 0 matches expected size 1` — sem listener, nenhuma activity/chamada).
2. **Task 1 (GREEN):** `feat(09-08)` — `55f63a8` — `ComunicarResultadoRegin` (auto-descoberto, ShouldQueue, try/catch + auditoria) → 3/3 (10 asserções), pint passed.

**Plan metadata:** `docs(09-08)` (este SUMMARY + STATE).

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-104 / HU-076 RN-008 — comunica o parecer ao Regin (sucesso, ligável Fase 13) | ComunicarResultadoReginListenerTest::sucesso | fake chamado 1×; activity result 'sucesso' 1× |
| HU-076 RN-008 — parecer vai ao Regin no indeferimento também | ComunicarResultadoReginListenerTest::indeferido | fake chamado com decision outcome Indeferida |
| HU-076 FA-03 / anti-fachada — bloqueado audita pendência, sem simular | ComunicarResultadoReginListenerTest::bloqueado | activity result 'bloqueado' 1×; 'sucesso' 0× |
| RN-002 (lição Fase 8) — sem duplicação / auto-descoberta | CONTAGEM (1×) + `event:list` + grep sem `Event::listen` | registro único |

## Decisions Made

- **ShouldQueue + exceção nunca relançada:** a integração de saída roda na fila e a `ReginUnavailableException` é capturada e auditada como pendência — a decisão (gravada/auditada síncrona, HU-078) não pode falhar por causa da integração bloqueada. O registro `bloqueado` é a pendência honesta visível (entrega-funcional), não um job destrutivo nem um sucesso falso.
- **Mesmo logName/event, result distinto:** `integracoes`/`regin-parecer` com result `bloqueado` vs `sucesso` — a varredura de pendências (09-12) filtra por result e a Fase 13 vira o caminho sem mudar a chave da trilha.
- **Sucesso provado com fake SÓ no teste:** demonstra que `notifyParecer` é chamado e audita `sucesso` quando o contrato existir — prova de que a Fase 13 é só a troca do binding (`app()->instance` no teste; ZERO adaptador falso em runtime).
- **properties + subject:** `viability_request_id`/`protocolo`/`outcome` (+`erro` no bloqueado) com subject = a solicitação — trilha rastreável ao agregado (RN-002).

## Deviations from Plan

None — plano executado exatamente como escrito (1 task; listener auto-descoberto + os 3 cenários: bloqueado audita 1×, sucesso com fake, indeferimento também comunica). Boundary da Wave 4 respeitado: criados apenas `ComunicarResultadoRegin` + seu teste; NÃO toquei a notificação (09-07) nem o SEFAZ (09-09); staging individual.

## Issues Encountered

- **Wave 4 em paralelo (09-07/09-09 no MESMO evento `ResultadoEmitido`):** três listeners distintos auto-descobertos no mesmo evento. Toquei só os meus arquivos. O arquivo `tests/Feature/Expresso/ResultadoExpressoNotificationTest.php` (untracked, 09-07) NÃO foi versionado por mim. Os testes do listener isolam o efeito por `log_name`/`event` (`integracoes`/`regin-parecer`), então a CONTAGEM permanece correta independentemente dos outros listeners da wave.
- **ShouldDispatchAfterCommit + fila sync sob RefreshDatabase:** o `event(new ResultadoEmitido(...))` aciona o listener ShouldQueue de forma síncrona na suíte (QUEUE_CONNECTION=sync), igual ao precedente `SolicitacaoProtocolada` (09-06) — confirmado pelo RED (event() roda sem erro) e pelo GREEN (activity gravada).

## Verification (evidência fresca)

- **RED:** `--filter=ComunicarResultadoReginListenerTest` → 3 falhas (`actual size 0 matches expected size 1`) — sem listener, nenhuma activity/chamada (motivo certo).
- **GREEN:** `--filter=ComunicarResultadoReginListenerTest` → **3/3 (10 asserções)**.
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **Auto-descoberta:** `php artisan event:list` → `App\Events\ResultadoEmitido ⇂ App\Listeners\ComunicarResultadoRegin@handle (ShouldQueue)` (registro único).
- **Greps de aceite:** `ReginUnavailableException` + `logName: 'integracoes'` + `result: 'bloqueado'` presentes no listener; `ComunicarResultadoRegin` AUSENTE do `AppServiceProvider` (auto-descoberta); nenhum `Event::listen` real (só comentários "NÃO registrar via Event::listen").
- **Suíte completa SQLite:** `php artisan test --compact --exclude-group postgis` → **902 testes, 902 passaram (4583 asserções)** — zero regressão; o listener roda de verdade no caminho real do `ResultadoEmitido` (testes de decisão não-fakeados) sem quebrar nenhum.

## Next Phase Readiness

- **09-10** (HU-134 dormente): o indeferimento sem atuação BAP emite `ResultadoEmitido` → este listener comunica o parecer ao Regin (bloqueado → pendência auditada) automaticamente, sem código novo.
- **09-12** (smoke): a varredura/relatório de pendências encontra a trilha `integracoes`/`regin-parecer` result `bloqueado` por decisão emitida.
- **Fase 13** (liga o Regin real): trocar SÓ o binding `ReginParecerNotifier` no `AppServiceProvider` faz o listener passar a auditar `sucesso` (caminho já implementado e provado com fake) — ZERO alteração neste listener.
- **Bloqueio honesto mantido:** nenhuma fachada — o `Unavailable` nunca simula a transmissão; o listener nunca registra sucesso fictício; a pendência fica visível na trilha até a Fase 13.

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
