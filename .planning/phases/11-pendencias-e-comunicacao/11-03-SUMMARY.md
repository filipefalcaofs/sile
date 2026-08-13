---
phase: 11-pendencias-e-comunicacao
plan: 03
subsystem: infra
tags: [hu-095, whatsapp, notification-channel, contract, unavailable, anti-fachada, e164, audit]

# Dependency graph
requires:
  - phase: 11-pendencias-e-comunicacao
    provides: "11-01 (Communication + markAsBlocked/markAsSent + contrato ProcessNotification + enums) e 11-02 (toggle features.notificacao_whatsapp OFF + integrations.whatsapp.* parametrizado)"
  - phase: 09-fluxo-expresso
    provides: "padrão Unavailable + binding + listener que audita 'bloqueado' (ReginParecerNotifier / ComunicarResultadoRegin)"
provides:
  - "Stack WhatsApp atrás de contrato: WhatsAppGateway (interface) + WhatsAppMessage (DTO imutável) + UnavailableWhatsAppGateway (lança WhatsAppUnavailableException) + binding default no AppServiceProvider"
  - "WhatsAppChannel customizado (canal 'whatsapp'): dono único da linha communications do canal whatsapp — indisponível → bloqueado+auditoria, disponível (spy/Fase 13) → enviado, sem destino → degrada"
  - "User::routeNotificationForWhatsapp() = phone (E.164) — o destino do canal"
affects:
  - "11-04 (dispatcher inclui o canal 'whatsapp' quando toggle on e cria a linha communications na_fila que o canal atualiza)"
  - "11-05/06 (Notifications de processo implementam toWhatsApp(): WhatsAppMessage)"
  - "11-07 (escalonamento multicanal usa o mesmo canal/contrato)"
  - "Fase 13 (liga o provedor real trocando SÓ o binding WhatsAppGateway → adaptador HTTP)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Contrato + Unavailable que LANÇA exceção (degradação honesta, nunca simula) — espelha Regin/SEFAZ/Bap"
    - "Canal customizado de Notification via Notification::extend('whatsapp', ...) resolvido pelo container"
    - "Canal como dono único da linha do ledger: captura Unavailable → markAsBlocked + auditoria (espelha ComunicarResultadoRegin), nunca relança"
    - "Destino do canal resolvido por routeNotificationForWhatsapp (E.164); conteúdo pela Notification (toWhatsApp)"

key-files:
  created:
    - "app/Services/Whatsapp/WhatsAppGateway.php"
    - "app/Services/Whatsapp/WhatsAppMessage.php"
    - "app/Services/Whatsapp/UnavailableWhatsAppGateway.php"
    - "app/Services/Whatsapp/WhatsAppUnavailableException.php"
    - "app/Notifications/Channels/WhatsAppChannel.php"
    - "tests/Feature/Comunicacao/WhatsAppGatewayContractTest.php"
    - "tests/Feature/Comunicacao/WhatsAppChannelTest.php"
  modified:
    - "app/Providers/AppServiceProvider.php"
    - "app/Models/User.php"

key-decisions:
  - "WhatsAppChannel localiza o Communication por LOOKUP (contrato ProcessNotification: viability_request_id + communicationType + channel=whatsapp + status na_fila), não por id congelado — autossuficiente, sem acoplar ao dispatcher 11-04 (paralelo)"
  - "WhatsAppUnavailableException extends RuntimeException (conforme plano); UnavailableWhatsAppGateway::send() SEMPRE lança (nunca simula)"
  - "O canal injeta o destino E.164 (routeNotificationForWhatsapp); o toWhatsApp() devolve body/meta — o canal é a autoridade do 'para onde'"
  - "Auditoria do bloqueio: logName 'notificacoes', event = communicationType->value, result 'bloqueado', subject = a linha Communication"

patterns-established:
  - "Canal de Notification que é dono único da linha do ledger e degrada para bloqueado sem relançar"
  - "Spy de gateway no teste prova que o canal 'liga' na Fase 13 trocando só o binding"

# Metrics
duration: ~16min
completed: 2026-06-15
---

# Phase 11 Plan 03: Stack WhatsApp bloqueada honesta (contrato + canal + rota E.164) Summary

**WhatsApp atrás de contrato `WhatsAppGateway` + DTO `WhatsAppMessage` + `UnavailableWhatsAppGateway` (lança `WhatsAppUnavailableException`) com binding default ao lado de Regin/SEFAZ/Bap, e um `WhatsAppChannel` customizado que é dono único da linha `communications` do canal whatsapp — indisponível degrada para `bloqueado`+auditoria (nunca "enviado"), disponível (spy) envia em E.164 e marca `enviado` — tudo coberto por feature tests, suíte completa verde (1148), zero dependência nova.**

## Performance

- **Duration:** ~16 min
- **Started:** 2026-06-15T01:02:00-03:00
- **Completed:** 2026-06-15T01:18:00-03:00
- **Tasks:** 2 (cada uma com ciclo TDD RED → GREEN confirmado com output real)
- **Files criados:** 7 | **modificados:** 2

## Accomplishments

- Contrato WhatsApp degradado honesto: o binding default `WhatsAppGateway → UnavailableWhatsAppGateway` LANÇA `WhatsAppUnavailableException` — a transmissão não ocorre, nunca é simulada (provedor/credencial pendentes → Fase 13).
- `WhatsAppChannel` (canal `'whatsapp'`) registrado via `Notification::extend` e resolvido pelo container: captura `WhatsAppUnavailableException` → `markAsBlocked()` na linha `communications` + auditoria `'bloqueado'`, **não relança** (degrada controlado); com gateway disponível (spy) → `markAsSent()` e mensagem em E.164. Espelha `ComunicarResultadoRegin`.
- `User::routeNotificationForWhatsapp()` devolve o `phone` (E.164) — o destino do canal; sem telefone, o canal degrada honesto (não envia).
- TDD estrito com evidência fresca: RED confirmado (classes ausentes; depois `Driver [whatsapp] not supported`), GREEN com 5 testes do plano verdes; suíte SQLite completa verde (1148 testes, 5907 asserções) — único editor de `AppServiceProvider`/`User`, sem regressão.

## Task Commits

Cada task foi commitada atomicamente (TDD RED → GREEN dentro de cada uma):

1. **Task 1: contrato + DTO + Unavailable + Exception + binding** — `5ccb53f` (feat)
2. **Task 2: WhatsAppChannel + routeNotificationForWhatsapp + registro do canal** — `044d3e3` (feat)

## Contratos para os próximos planos (insumo direto)

### `App\Services\Whatsapp\WhatsAppGateway` (interface)
```
send(WhatsAppMessage $message): void   // @throws WhatsAppUnavailableException
```
Binding default em `AppServiceProvider::register` (ao lado de Regin/SEFAZ/Bap): `WhatsAppGateway → UnavailableWhatsAppGateway`. **Fase 13** troca SÓ este binding pelo adaptador HTTP real (lendo `integrations.whatsapp.base_url/.token` do 11-02 + constantes técnicas de `config('sile.integrations.whatsapp.*')`); nenhum call site muda.

### `App\Services\Whatsapp\WhatsAppMessage` (DTO imutável)
```
new WhatsAppMessage(string $to, string $body, array $meta = [])
// to: telefone do destinatário em E.164; body: conteúdo; meta: livre
```
Propriedades `readonly`. O `to` é injetado pelo **canal** (do `routeNotificationForWhatsapp`), não pela Notification.

### `App\Services\Whatsapp\WhatsAppUnavailableException` (extends RuntimeException)
Mensagem honesta: "Envio de WhatsApp indisponível: provedor/credencial pendentes (Fase 13)." `motivo` opcional. `UnavailableWhatsAppGateway::send()` SEMPRE a lança.

### Canal `'whatsapp'` (`App\Notifications\Channels\WhatsAppChannel`)
Registrado em `AppServiceProvider::boot` com `Notification::extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class))`. Assinatura: `send(object $notifiable, Notification $notification): void`. Fluxo:
1. `$to = $notifiable->routeNotificationFor('whatsapp', $notification)`; **sem destino → return** (degrada honesto: não envia, não marca).
2. `$content = $notification->toWhatsApp($notifiable)` (WhatsAppMessage); o canal reconstrói `new WhatsAppMessage($to, $content->body, $content->meta)` — **o destino vem do User**.
3. Localiza a linha do ledger (ver abaixo) e chama `gateway->send()`:
   - sucesso → `Communication::markAsSent()`.
   - `WhatsAppUnavailableException` → `Communication::markAsBlocked($e->getMessage())` + auditoria (`logName 'notificacoes'`, `event = communicationType->value`, `result 'bloqueado'`, `subject = a linha Communication`); **não relança**.

### Como o canal localiza/atualiza o `Communication`
**Decisão (à discrição do plano, documentada): LOOKUP via contrato `ProcessNotification`** — não id congelado. Quando a Notification é `ProcessNotification` e tem `viabilityRequestId() !== null`, o canal busca:
```
Communication::where('viability_request_id', $notification->viabilityRequestId())
    ->where('type', $notification->communicationType())
    ->where('channel', CommunicationChannel::Whatsapp)
    ->where('status', CommunicationStatus::NaFila)
    ->latest('id')->first()
```
Usa o índice composto `(viability_request_id, type, channel)` criado em 11-01. É **autossuficiente** e não acopla ao dispatcher 11-04 (que roda em paralelo). **Para 11-04/10:** o dispatcher cria a linha `communications` do canal whatsapp em `na_fila` ANTES de `notify()`; o canal a encontra e a transiciona (bloqueado/enviado) — o canal é o **dono único** dessa linha (a guarda anti-fachada de `markAsSent` protege contra um `NotificationSent` tardio sobre status terminal). Se no futuro o 11-04 congelar um mapa `{canal→communicationId}` na Notification, o canal pode evoluir para o id explícito; o lookup permanece como fallback correto.

### Contrato esperado das Notifications de processo (11-05/06)
Além de `ProcessNotification` (já implementado pelo contrato em 11-01), as Notifications que roteiam para WhatsApp devem expor:
```
toWhatsApp(object $notifiable): WhatsAppMessage   // body/meta; o `to` é resolvido pelo canal
via(object $notifiable): array                    // inclui 'whatsapp' quando o canal está congelado
```

### `User::routeNotificationForWhatsapp()`
```
routeNotificationForWhatsapp(?Notification $notification = null): ?string  // => $this->phone (E.164)
```

## Mapa CA → teste (verde)
| HU / RN | Teste |
|---|---|
| HU-095 — provedor indisponível NUNCA finge (contrato Unavailable lança) | `WhatsAppGatewayContractTest::test_gateway_indisponivel_lanca_excecao_e_nunca_simula_envio` |
| HU-095 — binding default ao lado de Regin/SEFAZ/Bap | `WhatsAppGatewayContractTest::test_binding_default_resolve_o_gateway_indisponivel` |
| HU-095 — canal on + indisponível → bloqueado + auditoria (nunca "enviado") | `WhatsAppChannelTest::test_canal_indisponivel_marca_communication_bloqueado_e_audita_sem_enviado` |
| HU-095 — liga sozinho na Fase 13 (troca do binding) + destino E.164 | `WhatsAppChannelTest::test_caminho_de_sucesso_com_spy_envia_em_e164_e_marca_enviado` |
| HU-095 — sem destino degrada honesto | `WhatsAppChannelTest::test_sem_telefone_no_destinatario_nao_envia_e_degrada` |

## Decisions Made
- **Lookup em vez de id congelado:** o WhatsAppChannel localiza a linha `communications` pelo contrato `ProcessNotification` + `channel=whatsapp` + `status na_fila` (índice composto do 11-01). Evita acoplar ao dispatcher 11-04 (paralelo) e não inventa método de contrato (que seria código morto/fachada). Documentado como ponto de evolução para 11-04/10.
- **Canal é a autoridade do destino:** `toWhatsApp()` devolve body/meta com `to` irrelevante; o canal injeta o E.164 do `routeNotificationForWhatsapp`. O teste prova isso (a Notification devolve `to: ''` e o spy recebe o phone do User).
- **Não relança a Unavailable:** o canal indisponível jamais quebra o fluxo da notificação (os demais canais seguem); o registro `bloqueado` + auditoria é a pendência visível — espelha `ComunicarResultadoRegin`.
- **Credenciais zero hardcoded:** o `UnavailableWhatsAppGateway` não lê credencial (só lança); o adaptador real (Fase 13) lê `integrations.whatsapp.*` (11-02). Toggle `features.notificacao_whatsapp` segue OFF (resolvido no dispatcher 11-04).

## Deviations from Plan
None - plan executed exactly as written. Escopo respeitado (sem tocar NotificationDispatcher/RegistrarEnvioComunicacao do 11-04).

## Issues Encountered
- Pint reordenou os imports do `AppServiceProvider` (`ordered_imports`) após a inclusão dos `use` do WhatsApp — correção cosmética automática, suíte verde depois.

## User Setup Required
None - nenhuma configuração de serviço externo. Zero dependência nova.

A credencial/provedor real do WhatsApp permanece **pendência externa (Fase 13)**: a feature fica explicitamente bloqueada (toggle off + gateway Unavailable que lança), nunca simulada.

## Next Phase Readiness
- **11-04** pode incluir o canal `'whatsapp'` nos canais congelados quando `features.notificacao_whatsapp` estiver on e criar a linha `communications` (na_fila) que o `WhatsAppChannel` transiciona; **não** precisa marcar enviado/bloqueado do whatsapp (o canal é o dono).
- **11-05/06** implementam `toWhatsApp(): WhatsAppMessage` nas Notifications de processo.
- **Fase 13** liga o provedor real trocando SÓ o binding `WhatsAppGateway` — o caminho de sucesso já está provado (spy).
- **Sem bloqueios** para os próximos planos da fase. STATE.md não alterado por este plano (consolidação a cargo do orquestrador da fase).

---
*Phase: 11-pendencias-e-comunicacao*
*Completed: 2026-06-15*
