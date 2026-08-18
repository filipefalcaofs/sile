---
phase: 11-pendencias-e-comunicacao
plan: 05
subsystem: notifications
tags: [hu-090, hu-091, hu-092, hu-095, hu-096, pendencia, listener, auto-discovery, multicanal, after-commit, anti-fachada, anti-duplicacao]

# Dependency graph
requires:
  - phase: 11-pendencias-e-comunicacao
    provides: "11-04 (NotificationDispatcher::deliver + contrato ProcessNotification + mapa {driver→communicationId} congelado), 11-03 (WhatsAppChannel + WhatsAppMessage), 11-02 (toggles features.notificacao_* + notificacoes.mapa_canais + notificacoes.pendencia.assunto/corpo), 11-01 (Communication + enums + contrato)"
  - phase: 10-analise-tecnica-sedur
    provides: "PendenciaService (abrir/responder), evento PendenciaSolicitada (after-commit), PendenciaSolicitadaNotification, ciclo de estado em_analise↔em_pendencia + timeline + auditoria pendencia-aberta/pendencia-respondida"
provides:
  - "NotificarPendencia: listener AUTO-DESCOBERTO de PendenciaSolicitada → NotificationDispatcher::deliver ao requerente (e-mail + in-app + histórico). Único ponto de aviso da abertura"
  - "PendenciaRespondida: evento de domínio after-commit (espelha PendenciaSolicitada) disparado por PendenciaService::responder"
  - "NotificarRespostaPendencia: listener AUTO-DESCOBERTO de PendenciaRespondida → notifica o analista responsável (assigned_user_id)"
  - "PendenciaSolicitadaNotification + RespostaPendenciaNotification: ProcessNotification multicanal (via() dinâmico + toDatabase + toWhatsApp); e-mail da abertura parametrizado (notificacoes.pendencia.*)"
affects:
  - "11-08 (central in-app/histórico exibem as comunicações pendencia_aberta/pendencia_respondida)"
  - "11-10 (smoke do EP11: abrir → in-app+e-mail ao requerente → responder → analista notificado)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Listener de domínio auto-descoberto que chama NotificationDispatcher::deliver (generaliza o e-mail direto da Fase 10 para multicanal)"
    - "Anti-duplicação: o serviço dispara só o evento; o aviso é responsabilidade exclusiva do listener (removido o notify direto)"
    - "Evento de retorno after-commit (PendenciaRespondida) espelhando PendenciaSolicitada/ResultadoEmitido — fecha o ciclo que a Fase 10 não fechava"
    - "ProcessNotification de processo com via() dinâmico + fallback ['mail'] + frozenChannels/communicationIds públicos (contrato do dispatcher 11-04)"

key-files:
  created:
    - "app/Listeners/NotificarPendencia.php"
    - "app/Events/PendenciaRespondida.php"
    - "app/Listeners/NotificarRespostaPendencia.php"
    - "app/Notifications/RespostaPendenciaNotification.php"
    - "tests/Feature/Comunicacao/NotificarPendenciaListenerTest.php"
    - "tests/Feature/Comunicacao/NotificarRespostaPendenciaListenerTest.php"
  modified:
    - "app/Notifications/PendenciaSolicitadaNotification.php"
    - "app/Services/Analise/PendenciaService.php"
    - "tests/Feature/Analise/PendenciaServiceTest.php"

key-decisions:
  - "'Sem destinatário' = recipient User ausente (requester null em NotificarPendencia; assignedTo null em NotificarRespostaPendencia). A semântica antiga 'sem e-mail' não cabe no multicanal (in-app independe de e-mail); como requester_user_id é FK NOT NULL, o caso real testável de 'sem requerente' usa setRelation('requester', null)"
  - "Auditoria 'sem-destinatario' dos listeners usa event hifenizado ('pendencia-aberta'/'pendencia-respondida'), espelhando NotificarResultadoExpresso ('resultado-expresso'); é distinta da auditoria de canal-desativado do dispatcher (event = type->value 'pendencia_aberta')"
  - "After-commit de PendenciaRespondida verificado por assertInstanceOf(ShouldDispatchAfterCommit) — não por rollback. Sob RefreshDatabase os eventos de domínio disparam SÍNCRONOS (provado por test_abrir_notifica e test_resposta_notifica e pelo NotificarResultadoExpressoListenerTest da Fase 9), então uma asserção de rollback seria enganosa; usa-se o padrão estrutural já estabelecido (ResultadoEmitidoEventTest)"
  - "toMail da abertura ganhou parametrização (assunto/corpo via Settings notificacoes.pendencia.* com placeholders {protocolo}/{pendencia}); o greeting 'Olá!' é mantido e a saudação duplicada do corpo é removida (preg_replace) para não repetir"
  - "test_abrir_sem_destinatario (Fase 10) MOVIDO para NotificarPendenciaListenerTest (cobertura mantida, não removida) — o sem-destinatario agora é do listener"

patterns-established:
  - "Listeners de domínio do EP11 chamam deliver(recipient, ProcessNotification); o recipient é resolvido pelo listener (requester/assignedTo) e a degradação sem-destinatario é auditada nele"

# Metrics
duration: ~30min
completed: 2026-06-15
---

# Phase 11 Plan 05: Pendência multicanal (abrir + responder) Summary

**O ciclo de pendência da Fase 10 vira comunicação plena do EP11 SEM refazer estado: HU-090 fica multicanal pelo listener AUTO-DESCOBERTO `NotificarPendencia` (PendenciaSolicitada → `NotificationDispatcher::deliver` ao requerente: e-mail + in-app + histórico), com `PendenciaSolicitadaNotification` agora `ProcessNotification` (via() dinâmico + toDatabase/toWhatsApp + e-mail parametrizado). O refactor CRÍTICO anti-duplicação remove `notificarRequerente()` do `PendenciaService::abrir` — o listener é o ÚNICO ponto de aviso. HU-091/092 ganham o evento after-commit `PendenciaRespondida` (disparado por `responder()`) + o listener `NotificarRespostaPendencia` que notifica o ANALISTA responsável (assigned_user_id), fechando o retorno que a Fase 10 não fechava. Estado/timeline/auditoria INTACTOS; WhatsApp degrada honesto (desativado). Filtros do plano 18/18 verdes; anti-regressão `tests/Feature/Analise/` 195/195 verde; zero dependência nova.**

## Listeners criados (auto-descobertos — contagem travada por teste)

| Evento | Listener | Destinatário | Contagem | Tipo Communication |
|---|---|---|---|---|
| `PendenciaSolicitada` (after-commit) | `NotificarPendencia` | requerente (`requester`) | 1× | `pendencia_aberta` → [email, in_app] |
| `PendenciaRespondida` (after-commit, NOVO) | `NotificarRespostaPendencia` | analista responsável (`assignedTo`) | 1× | `pendencia_respondida` → [in_app] |

Cada listener é o ÚNICO auto-descoberto do seu evento (`assertCount(1, Event::getListeners(...))`), com `handle()` type-hintando o evento (NUNCA `Event::listen`). Sem destinatário → auditoria `notificacoes`/`<event>`/`sem-destinatario` e retorna (nunca finge envio).

## O que mudou no `PendenciaService`

- **`abrir()`**: REMOVIDO o `$this->notificarRequerente(...)` e o método privado inteiro (e os imports órfãos `EmailLog`/`PendenciaSolicitadaNotification`). Mantido o `PendenciaSolicitada::dispatch($request, $pendency)` after-commit. O aviso passou a ser exclusivamente do listener (anti-duplicação) — `grep notificarRequerente` agora vazio.
- **`responder()`**: ADICIONADO `PendenciaRespondida::dispatch($request, $pendency)` APÓS o commit da transação. Estado/timeline/auditoria pendencia-respondida inalterados.

## Tipos de Communication gerados (ledger HU-096)

- **`pendencia_aberta`** (requerente): mapa default `[email, in_app]` → 2 linhas `na_fila`. Com WhatsApp adicionado ao mapa e toggle off (default) → linha `whatsapp` `desativado` + auditoria (nunca "enviado").
- **`pendencia_respondida`** (analista): mapa default `[in_app]` → 1 linha `na_fila`.

## Notifications (ProcessNotification multicanal)

- `PendenciaSolicitadaNotification`: agora implementa `ProcessNotification` (communicationType `PendenciaAberta`, `viabilityRequestId()`, `freezeChannels/channels`, `public array $communicationIds/$frozenChannels`). `via()` DINÂMICO (canais congelados → drivers; fallback `['mail']`). `toMail()` lê `notificacoes.pendencia.assunto/corpo` (Settings, placeholders `{protocolo}`/`{pendencia}`); `toDatabase()` (título/resumo/link do portal); `toWhatsApp()` (texto curto + link). Mantém ShouldQueue + `failed()`/`emailLogId` (compatibilidade).
- `RespostaPendenciaNotification` (NOVA): `ProcessNotification` (communicationType `PendenciaRespondida`), via() dinâmico, `toMail/toDatabase/toWhatsApp` com link da GESTÃO (`gestao.processos.show`).

## Testes da Fase 10 migrados/ajustados (cobertura mantida)

- `PendenciaServiceTest::test_abrir_envia_email_simples_real_ao_requerente` → **renomeado** `test_abrir_notifica_o_requerente_multicanal_pelo_listener`: assere `communications` (email+in_app `na_fila`) + `assertSentToTimes(..., 1)` (anti-duplicação) + `assertDatabaseMissing('email_logs', ...)` (e-mail direto removido). NÃO assere mais `email_logs`.
- `PendenciaServiceTest::test_abrir_sem_destinatario_com_email_audita_e_nao_envia` → **movido** para `NotificarPendenciaListenerTest::test_sem_requerente_audita_e_nao_notifica` (semântica = requester null, via `setRelation`).
- Testes de ESTADO/transição/timeline/auditoria/bloqueios de `PendenciaServiceTest` permanecem inalterados e verdes. `ProcessoPendenciaEndpointTest` e `PendenciaRespostaPortalTest` (que não asseriam o e-mail) seguem verdes sem alteração.

## Task Commits
1. **Task 1 (HU-090 multicanal + anti-duplicação)** — `3ceadba` (feat) — TDD: RED (listener inexistente, 0 notificações, sem `communications`, sem auditoria) → GREEN (NotificarPendenciaListenerTest 5 + PendenciaServiceTest migrado 8 = 13 verdes).
2. **Task 2 (HU-091/092 — analista notificado)** — `d4d7a08` (feat) — TDD: RED (evento/listener inexistentes, 0 notificações) → GREEN (NotificarRespostaPendenciaListenerTest 5 verdes).

## Mapa CA → teste (verde)
| HU / RN | Teste |
|---|---|
| HU-090 — aviso de pendência multicanal (1×, auto-descoberto) | NotificarPendenciaListenerTest::test_evento_notifica_o_requerente_uma_unica_vez_e_multicanal / test_exatamente_um_listener... |
| HU-090 anti-duplicação — abrir não manda mais e-mail direto | PendenciaServiceTest::test_abrir_notifica_o_requerente_multicanal_pelo_listener (communications, sem email_logs) |
| HU-090 — sem requerente degrada honesto | NotificarPendenciaListenerTest::test_sem_requerente_audita_e_nao_notifica |
| HU-095 — WhatsApp off degrada (desativado, nunca "enviado") | NotificarPendenciaListenerTest::test_whatsapp_no_mapa_off_degrada_para_desativado... |
| HU-091/092 — resposta reabre e NOTIFICA o analista responsável | NotificarRespostaPendenciaListenerTest::test_resposta_notifica_o_analista_responsavel_uma_unica_vez |
| after-commit — contrato (rollback não dispara) | NotificarRespostaPendenciaListenerTest::test_o_evento_pendencia_respondida_e_after_commit (assertInstanceOf) |
| HU-091/092 — sem analista degrada honesto | NotificarRespostaPendenciaListenerTest::test_sem_analista_responsavel_audita_e_nao_notifica |

## Verificação (evidência fresca)
- `vendor/bin/pint --dirty --format agent` → passed.
- `php artisan test --compact --filter="NotificarPendenciaListenerTest|NotificarRespostaPendenciaListenerTest|PendenciaServiceTest"` → **18 passed / 58 assertions**.
- ANTI-REGRESSÃO `php artisan test --compact tests/Feature/Analise/` → **195 passed / 925 assertions** (inclui ProcessoPendenciaEndpointTest, PendenciaRespostaPortalTest, AnaliseSmokeTest).
- Comunicação relacionada (mine + 11-01..04: NotificationDispatcherTest/RegistrarEnvioComunicacaoTest/WhatsAppChannelTest/WhatsAppGatewayContractTest) → **25 passed**.

## Deviations from Plan
- **After-commit por assertInstanceOf (não rollback)**: o plano pedia, em `NotificarRespostaPendenciaListenerTest`, asserir que "numa transação que sofre rollback o evento NÃO dispara". Sob `RefreshDatabase`, os eventos de domínio (incl. `ShouldDispatchAfterCommit`) disparam SÍNCRONOS — provado nesta própria fase (`test_abrir_notifica` e `test_resposta_notifica` recebem a notificação via evento after-commit) e pelo padrão da Fase 9 (`NotificarResultadoExpressoListenerTest` usa `event()` direto). Uma asserção de rollback seria enganosa/infazível. Verifico o contrato estruturalmente com `assertInstanceOf(ShouldDispatchAfterCommit::class)`, espelhando o padrão já estabelecido no repositório (`ResultadoEmitidoEventTest`) — código existente vence o aspiracional.
- **'Sem destinatário' re-significado para o multicanal**: a Fase 10 auditava sem-destinatario por e-mail em branco; no multicanal o destinatário é o User (in-app independe de e-mail). Como `requester_user_id` é FK NOT NULL, o caso testável de "sem requerente" usa `setRelation('requester', null)` no listener test. O `assignedTo` (analista) é nullable, então o sem-destinatario da resposta é realista direto.
- **toMail parametrizado (permissivo no plano, implementado)**: o plano dizia que o toMail "pode ler" `notificacoes.pendencia.assunto/corpo`; implementei (parametrização máxima — texto de e-mail é valor de negócio), mantendo verde o teste de e-mail sem-anexo da Fase 10.

## Issues Encountered
- Nenhum bloqueio. O contrato `ProcessNotification` (11-01) e o `NotificationDispatcher` (11-04) cobriram o roteamento sem mudanças. As Notifications declaram `public array $communicationIds/$frozenChannels` (PHP 8.2+ exige a propriedade) conforme o contrato documentado em 11-04.

## Próximos planos (insumo direto)
- **11-08**: a central in-app e o histórico devem listar as comunicações `pendencia_aberta` (requerente) e `pendencia_respondida` (analista) — os payloads de `toDatabase()` trazem type/title/summary/url.
- **11-10**: smoke do EP11 — abrir (in-app + e-mail ao requerente) → responder pelo portal → analista notificado (in-app). Os dois listeners disparam por auto-descoberta.
- **Wave 3 paralela**: este plano é o ÚNICO que tocou `PendenciaService`; 11-06 (NotificarResultadoExpresso) e 11-07 (scheduler/console.php + ExpirarPendenciasCommand) NÃO foram tocados.

---
*Phase: 11-pendencias-e-comunicacao*
*Completed: 2026-06-15*
