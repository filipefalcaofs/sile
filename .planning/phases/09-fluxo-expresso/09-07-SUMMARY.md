---
phase: 09-fluxo-expresso
plan: 07
subsystem: notificacoes
tags: [hu-077, fluxo-expresso, notificacao, email, listener-auto-descoberto, feature-toggle, anti-fachada, emaillog, shouldqueue, hu-014, expresso, evento-dominio]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "01"
    provides: "Parâmetro features.notificacao_resultado_expresso (toggle) + expresso.notificacao.assunto_deferida/_indeferida (assuntos parametrizáveis HU-014) com fallback em config/sile.php"
  - phase: 09-fluxo-expresso
    plan: "03"
    provides: "Evento de domínio ResultadoEmitido (ShouldDispatchAfterCommit, request + decision) — base desacoplada dos efeitos da Wave 4"
  - phase: 09-fluxo-expresso
    plan: "05"
    provides: "FluxoExpressoService dispara ResultadoEmitido após o commit (deferida/indeferida) — DecisionOutcome consolidado"
  - phase: 02-administracao-base
    plan: "(e-mail)"
    provides: "Infra de e-mail reusada: EmailLog + notificações ShouldQueue (VerifyEmailQueued) + LogNotificationSent (auto-descoberto marca enviado) + AuditService + Settings::get"
provides:
  - "App\\Notifications\\ResultadoExpressoNotification (mail, ShouldQueue): e-mail de ciência do resultado ao requerente, assunto parametrizável por desfecho, SEM anexo de TVL, integrado ao EmailLog (emailLogId + failed())"
  - "App\\Listeners\\NotificarResultadoExpresso: listener AUTO-DESCOBERTO do ResultadoEmitido que notifica o requerente 1x, respeita o toggle (off → audita 'desativado') e degrada honesto sem destinatário (audita 'sem-destinatario')"
affects: [09-12, 11, 13]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Efeito colateral DESACOPLADO via listener auto-descoberto no evento de domínio (espelha RegistrarTrilhaProtocolo): registro ÚNICO por auto-descoberta — NÃO Event::listen (duplicaria), travado por CONTAGEM (assertSentToTimes)"
    - "Notificação ShouldQueue + EmailLog (espelha VerifyEmailQueued): EmailLog criado no DISPARO (listener), emailLogId injetado na notificação; LogNotificationSent marca 'enviado', failed() marca 'falhou' — sem fachada, o envio passa pela infra real"
    - "Degradação COMUNICADA do toggle: features.notificacao_resultado_expresso off → audita 'desativado' e não envia (nunca silenciosa); sem e-mail válido → audita 'sem-destinatario' e não envia (honesto)"

key-files:
  created:
    - app/Notifications/ResultadoExpressoNotification.php
    - app/Listeners/NotificarResultadoExpresso.php
    - tests/Feature/Expresso/ResultadoExpressoNotificationTest.php
    - tests/Feature/Expresso/NotificarResultadoExpressoListenerTest.php
  modified: []

key-decisions:
  - "A notificação carrega DADOS DESNORMALIZADOS (protocolNumber: string + outcome: DecisionOutcome), não os models — payload enxuto para a fila e suficiente para o assunto + corpo (o plano permitia ambos)."
  - "Listener SÍNCRONO (não ShouldQueue); quem enfileira é a notificação (ShouldQueue) — espelha o padrão da casa (RegistrarTrilhaProtocolo + VerifyEmailQueued)."
  - "EmailLog criado no listener (disparo) com status 'na_fila'; emailLogId injetado na notificação — idêntico ao fluxo de verificação de e-mail (User::sendEmailVerificationNotification)."
  - "Destinatário = request->requester; sem requester ou e-mail em branco → audita 'sem-destinatario' e retorna (degradação honesta, jamais inventa envio)."
  - "Assunto via Settings::get('expresso.notificacao.assunto_deferida'|'_indeferida', config(...)) — efeito sem deploy (HU-014); deferida vs indeferida escolhem o assunto e o corpo."

patterns-established:
  - "Os 3 listeners da Wave 4 coexistem no MESMO ResultadoEmitido por auto-descoberta (NotificarResultadoExpresso síncrono + ComunicarResultadoRegin/EnviarViabilidadeSefaz ShouldQueue), cada um um efeito independente; nenhum toca a decisão (auditoria autoritativa HU-078 é síncrona em 09-05)."

# Metrics
duration: ~13 min
completed: 2026-06-14
---

# Phase 9 Plan 07: Notificação do Resultado Expresso ao Cidadão (HU-077) Summary

**O efeito desacoplado HU-077 entregue: o requerente é avisado por e-mail do desfecho do fluxo expresso — SEM anexo de TVL (o canal oficial de entrega do documento é o Regin/SEFAZ, decisão SEDUR). `ResultadoExpressoNotification` (mail, ShouldQueue) espelha o padrão `VerifyEmailQueued`/`EmailLog`: assunto parametrizável por desfecho (`expresso.notificacao.assunto_deferida`/`_indeferida` via `Settings::get`, efeito sem deploy — HU-014), corpo pt-BR com o número de protocolo e o resultado, `emailLogId` + `failed()` integrados ao `EmailLog` existente, e ZERO chamada de anexação (RN-004). O listener AUTO-DESCOBERTO `NotificarResultadoExpresso` pendura no `ResultadoEmitido` (09-03/05) e notifica o requerente EXATAMENTE 1× (auto-descoberta = registro único; NÃO `Event::listen` — lição Fase 8, travado por CONTAGEM `assertSentToTimes`), criando o `EmailLog` no disparo. Toggle `features.notificacao_resultado_expresso` off → NÃO envia e AUDITA a degradação (`notificacoes`/`resultado-expresso`, result `desativado`) — nunca falha silenciosa; sem destinatário com e-mail → audita `sem-destinatario` (honesto, não inventa envio). TDD estrito (RED→GREEN com evidência fresca): `ResultadoExpressoNotificationTest` 7/7 e `NotificarResultadoExpressoListenerTest` 4/4 (filtro combinado 11/11, 15 asserções). Suíte completa SQLite 906/906 (4590 asserções) — zero regressão, com o listener rodando de verdade no caminho real do `ResultadoEmitido`. ZERO dependência nova.**

## Performance

- **Duration:** ~13 min
- **Started:** 2026-06-14T17:55:28Z
- **Completed:** 2026-06-14T18:08:00Z (aprox.)
- **Tasks:** 2 (notificação; listener)
- **Files modified:** 4 criados — ZERO dependência nova

## Contrato (assinaturas exatas)

### `App\Notifications\ResultadoExpressoNotification` (mail, ShouldQueue)

```php
public ?int $emailLogId = null;

public function __construct(
    public string $protocolNumber,
    public DecisionOutcome $outcome,
) {}

public function via(object $notifiable): array;   // ['mail']
public function toMail(object $notifiable): MailMessage; // assunto por outcome; SEM ->attach()
public function failed(\Throwable $exception): void;     // EmailLog::markAsFailed
```

- Assunto: deferida → `Settings::get('expresso.notificacao.assunto_deferida', config('sile.expresso.notificacao.assunto_deferida'))`; indeferida → `_indeferida`.
- Corpo pt-BR: número de protocolo + resultado (deferida/indeferida) + orientação de acompanhar pelos canais Regin/Junta. **Sem anexo de TVL** (RN-004).

### `App\Listeners\NotificarResultadoExpresso` (AUTO-DESCOBERTO, síncrono)

```php
public function handle(ResultadoEmitido $event): void
```

- Toggle off (`features.notificacao_resultado_expresso`) → `audit->log('notificacoes','resultado-expresso', ..., result: 'desativado')` e RETURN.
- `request->requester` nulo ou e-mail em branco → `audit->log(..., result: 'sem-destinatario')` e RETURN.
- Caso normal: `EmailLog::create([... 'status' => 'na_fila'])` → `new ResultadoExpressoNotification($request->protocol_number, $decision->outcome)` (set `emailLogId`) → `$requester->notify($notification)`.

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-077 CA-01 — notifica o resultado ao requerente | NotificarResultadoExpressoListenerTest::test_notifica_o_requerente_uma_unica_vez | assertSentToTimes 1× + EmailLog 'na_fila' |
| HU-077 RN-004 — sem anexo de PDF/TVL | ResultadoExpressoNotificationTest::test_nao_anexa_o_tvl | attachments === [] e rawAttachments === [] |
| HU-077 RN-005 — assunto parametrizável | ResultadoExpressoNotificationTest (assunto deferida/indeferida + override administrável) | assunto por outcome; Parameter override sem deploy |
| HU-077 — toggle off degrada comunicado | NotificarResultadoExpressoListenerTest::test_toggle_desligado_nao_envia_e_audita_a_degradacao | assertNothingSent + Activity 'notificacoes' result 'desativado' |
| RN-002 (lição Fase 8) — sem duplicação | NotificarResultadoExpressoListenerTest (CONTAGEM) + AppServiceProvider sem Event::listen | assertSentToTimes 1× + grep -L |
| Anti-fachada — degradação honesta sem destinatário | NotificarResultadoExpressoListenerTest::test_sem_destinatario_com_email_audita_e_nao_envia | assertNothingSent + result 'sem-destinatario' |

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: ResultadoExpressoNotification (mail, ShouldQueue, sem anexo, assunto parametrizável)** — `cc7ce5b` (feat) — RED: 7 erros (`Class "App\Notifications\ResultadoExpressoNotification" not found`) → GREEN: 7/7 (8 asserções), pint passed.
2. **Task 2: listener NotificarResultadoExpresso (auto-descoberto, toggle + auditoria da degradação)** — `315d984` (feat) — RED: 4 falhas (0 notificações enviadas / sem auditoria 'notificacoes') → GREEN: 4/4 (7 asserções), pint passed.

**Plan metadata:** `docs(09-07)` (este SUMMARY + STATE).

_Commits intercalados com os planos paralelos da Wave 4 (09-08 `284d5d2`/`55f63a8`/`e0e9204`, 09-09 `7e84037`) na MESMA working dir — boundary respeitado, staging individual._

## Files Created/Modified

- `app/Notifications/ResultadoExpressoNotification.php` — e-mail (mail/ShouldQueue) do resultado ao requerente, assunto parametrizável, sem anexo, EmailLog.
- `app/Listeners/NotificarResultadoExpresso.php` — listener auto-descoberto do ResultadoEmitido (toggle + auditoria da degradação + EmailLog no disparo).
- `tests/Feature/Expresso/ResultadoExpressoNotificationTest.php` — 7 testes (enfileirável, canal mail, assunto por outcome, override administrável, número no corpo, sem anexo).
- `tests/Feature/Expresso/NotificarResultadoExpressoListenerTest.php` — 4 testes (notifica 1×/EmailLog, toggle off audita, indeferida usa assunto, sem destinatário audita).

## Decisions Made

- **Dados desnormalizados na notificação** (`protocolNumber` + `outcome`), não os models — payload de fila enxuto e suficiente; evita serialização do agregado.
- **Listener síncrono, notificação ShouldQueue** — espelha a casa; o enfileiramento real é da notificação.
- **EmailLog criado no disparo** (listener), `emailLogId` na notificação — anti-fachada: o envio percorre a infra real, o `LogNotificationSent` (auto-descoberto) marca 'enviado' no worker.
- **Degradação honesta** — toggle off audita 'desativado'; sem e-mail audita 'sem-destinatario'. Nunca silenciosa, nunca falsa.

## Deviations from Plan

None — plano executado exatamente como escrito (2 tasks; notificação + listener com os cenários especificados). Adicionado um 4º teste de listener (`sem-destinatario`) que cobre o caminho honesto descrito na ação da Task 2 ("sem e-mail válido → audita 'sem-destinatario' e return"); é comportamento do meu listener, dentro do escopo, sem código novo além do especificado.

## Issues Encountered

- **Comentário tropeçava no `grep -L "->attach"`**: o PHPDoc que justificava a ausência de anexo continha o literal `->attach()`, o que faria o `grep -L` de aceite não listar o arquivo. Reescrevi o comentário preservando a justificativa (RN-004) sem o token — `grep -L "->attach"` agora lista o arquivo (sem chamada de anexação real).
- **Wave 4 paralela na MESMA working dir**: 09-08 (Regin) e 09-09 (SEFAZ) commitaram intercalados e o STATE.md foi atualizado por eles. Toquei SOMENTE os meus 4 arquivos (notificação + listener + 2 testes); staging sempre individual (nunca `git add -A`). `event:list` confirma os 3 listeners coexistindo no ResultadoEmitido por auto-descoberta.

## Next Phase Readiness

- **09-12 (smoke):** o deferimento navegável dispara `ResultadoEmitido` → `NotificarResultadoExpresso` cria o EmailLog e enfileira a notificação (processa a fila ou desliga o fake do harness). A trilha `notificacoes`/`resultado-expresso` fica disponível quando o toggle estiver off.
- **EP11 (canais plenos):** WhatsApp/in-app pluga novos canais na mesma notificação/listener (o `via()` cresce); base desacoplada pronta.
- **Bloqueio honesto mantido:** este e-mail NÃO entrega o TVL — o documento oficial é Regin/SEFAZ (HU-104/HU-110, bloqueados → Fase 13). Sem fachada: o toggle off degrada comunicado (auditado), nunca finge envio.

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
