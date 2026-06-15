---
phase: 11-pendencias-e-comunicacao
plan: 04
subsystem: notifications
tags: [hu-090, hu-094, hu-096, dispatcher, multicanal, ledger, communications, auto-discovery, anti-fachada]

# Dependency graph
requires:
  - phase: 11-pendencias-e-comunicacao
    provides: "11-01 (Communication + 3 enums + marcadores honestos + contrato ProcessNotification freezeChannels/channels) e 11-02 (toggles features.notificacao_* + notificacoes.mapa_canais)"
  - phase: 09-fluxo-expresso
    provides: "padrão VerifyEmailQueued::freezeUrlFor (congelar no disparo) + LogNotificationSent (marcar por evento de notificação) + AuditService"
provides:
  - "NotificationDispatcher::deliver(User, ProcessNotification): roteador único — resolve canais (mapa ∩ toggles), cria communications (na_fila/desativado+auditoria), congela canais + mapa {driver→communicationId}, dispara notify()"
  - "RegistrarEnvioComunicacao: listener AUTO-DESCOBERTO que fecha o ciclo do ledger (na_fila → enviado/falhou) por NotificationSent/NotificationFailed, só mail/database"
  - "Mapa congelado {driver→communicationId} na Notification (espelha emailLogId): contrato de referência da linha do ledger para o listener (mail/database) e o WhatsAppChannel (whatsapp)"
affects:
  - "11-03 (WhatsAppChannel lê communicationIds['whatsapp'] para marcar bloqueado/enviado na linha congelada)"
  - "11-05/06 (Notifications de processo implementam ProcessNotification + via() dinâmico + declaram public array communicationIds; listeners de domínio chamam deliver)"
  - "11-07 (rotinas de vencimento/escalonamento chamam deliver; idempotência via communications)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Roteador síncrono no disparo: resolve canais (mapa_canais ∩ toggles), congela na Notification e enfileira o envio (espelha freezeUrlFor)"
    - "Mapa {driver→communicationId} congelado na Notification como propriedade pública (espelha o emailLogId único) — referência explícita da linha do ledger, sem lookup heurístico"
    - "Listener único por auto-descoberta com DOIS handles (handleNotificationSent/handleNotificationFailed via Str::is('handle*')) — NUNCA Event::listen"
    - "Gate de canal anti-fachada: o listener trata só mail/database; whatsapp é dono exclusivo do WhatsAppChannel (não sobrescrever bloqueado→enviado)"
    - "Degradação COMUNICADA: canal no mapa com toggle off vira linha 'desativado' + auditoria (nunca falha silenciosa)"

key-files:
  created:
    - "app/Services/Comunicacao/NotificationDispatcher.php"
    - "app/Listeners/RegistrarEnvioComunicacao.php"
    - "tests/Feature/Comunicacao/NotificationDispatcherTest.php"
    - "tests/Feature/Comunicacao/RegistrarEnvioComunicacaoTest.php"
  modified: []

key-decisions:
  - "Mapa congelado keyed pelo DRIVER nativo (mail/database/whatsapp), não pelo canal de negócio — o listener e o WhatsAppChannel resolvem por $event->channel direto"
  - "title da linha communications = CommunicationType::label() (default honesto); summary/meta ficam vazios — seam para 11-05/06 enriquecerem o conteúdo do ledger sem mudar a assinatura"
  - "linha 'desativado' NÃO recebe queued_at (nunca foi enfileirada); só 'na_fila' carimba queued_at=now()"
  - "gancho de preferência por usuário implementado como no-op (preferredByRecipient) — default por toggle/mapa agora; ativar a preferência é local (só este método consulta)"
  - "auditoria da degradação: log_name 'notificacoes', event = type->value, result 'desativado', subject = a própria linha Communication desativada"

patterns-established:
  - "ProcessNotification implementers DEVEM declarar public array communicationIds = [] (espelha public ?int emailLogId = null) — o dispatcher congela o mapa nessa propriedade"
  - "Dois listeners auto-descobertos coexistem em NotificationSent sem conflito (LogNotificationSent gated por emailLogId/mail; RegistrarEnvioComunicacao gated por communicationIds/mail+database)"

# Metrics
duration: ~20min
completed: 2026-06-15
---

# Phase 11 Plan 04: NotificationDispatcher + RegistrarEnvioComunicacao Summary

**O coração da comunicação multicanal do EP11: o `NotificationDispatcher` resolve os canais SÍNCRONO no disparo (mapa_canais ∩ toggles), cria o ledger honesto (`na_fila` dos habilitados; `desativado` + auditoria dos canais off), congela os canais resolvidos E um mapa explícito {driver→communicationId} na Notification (espelha `freezeUrlFor`/`emailLogId`) e dispara `notify()`; o listener AUTO-DESCOBERTO `RegistrarEnvioComunicacao` fecha o ciclo (`na_fila`→`enviado`/`falhou`) por `NotificationSent`/`NotificationFailed` só para `mail`/`database` — whatsapp é excluído de propósito (dono é o `WhatsAppChannel` do 11-03). Tudo coberto por feature tests; suíte SQLite 1158 verde; zero dependência nova.**

## Performance
- **Started:** 2026-06-15T04:02:52Z
- **Tasks:** 2 (cada uma em ciclo TDD RED→GREEN com evidência real)
- **Files criados:** 4

## Accomplishments
- `NotificationDispatcher::deliver` entrega o roteamento multicanal com ledger honesto e sem dupla verdade: resolve por mapa ∩ toggles, cria `communications`, congela canais + mapa de ids, enfileira o envio.
- `RegistrarEnvioComunicacao` fecha o ciclo do ledger por evento de notificação (auto-descoberto, registro único), gated em `mail`/`database` e excluindo `whatsapp` (anti-fachada).
- Degradação COMUNICADA testada: canal no mapa com toggle off → linha `desativado` + auditoria (`notificacoes`/type/`desativado`); todos off → nada enviado, só `desativado`.
- Anti-fachada do whatsapp provada: `NotificationSent('whatsapp')` NÃO marca a linha (permanece `na_fila`) — o `WhatsAppChannel` (11-03) é o dono único.
- Notificação de CONTA (`VerifyEmailQueued`) não encosta no ledger de processo (sem `communicationIds`); o `LogNotificationSent` segue cuidando do `EmailLog`.
- Suíte completa SQLite: **1158 passed / 5937 assertions** (`--exclude-group=postgis`).

## Task Commits
1. **Task 1: NotificationDispatcher** — `30dde5d` (feat) — TDD: RED (4 erros "class does not exist") → GREEN (4 testes, 19 asserções).
2. **Task 2: RegistrarEnvioComunicacao** — `8298667` (feat) — TDD: RED (linha fica `na_fila`, listener inexistente, contagem 0) → GREEN (6 testes, 11 asserções).

## Contratos para os próximos planos (insumo direto)

### `NotificationDispatcher::deliver(User $recipient, ProcessNotification $notification): void`
Pipeline (espelha `VerifyEmailQueued::freezeUrlFor` — resolve no DISPARO, lê no envio enfileirado):
1. **Resolve** os canais do tipo: `notificacoes.mapa_canais[type]` (via `Settings::get` → fallback `config('sile.notificacoes.mapa_canais')`) ∩ toggles `features.notificacao_email/_in_app/_whatsapp` (via `Settings::enabled("notificacao_{$channel->value}")`) + gancho `preferredByRecipient` (no-op).
2. **Cria o ledger** SÍNCRONO: por canal HABILITADO uma linha `communications` `na_fila` (`channel`/`type`/`viability_request_id`/`recipient_user_id`/`title=type->label()`/`queued_at=now()`); por canal do mapa com toggle OFF uma linha `desativado` + `AuditService::log('notificacoes', type->value, ..., result: 'desativado')`.
3. **Congela** os canais resolvidos via `freezeChannels(array<CommunicationChannel>)` (lidos pelo `via()`) E o mapa `{driver→communicationId}` na propriedade pública `communicationIds`.
4. **Dispara** `$recipient->notify($notification)` (envio enfileirado pela ShouldQueue da Notification). Se NENHUM canal habilitado: NÃO notifica (só o ledger `desativado` — honesto).

### Mapa congelado {driver→communicationId} (referência da linha, NÃO lookup)
- Propriedade pública na Notification: `public array $communicationIds = []` — keyed pelo **driver nativo** (`'mail'`, `'database'`, `'whatsapp'`), valor = `communications.id` da linha `na_fila` daquele canal. Espelha o `emailLogId` único do `VerifyEmailQueued`/`LogNotificationSent`.
- **11-05/06**: as Notifications de processo DEVEM declarar `public array $communicationIds = [];` (como já declaram `public ?int $emailLogId = null;`), implementar `ProcessNotification` (`viabilityRequestId`/`communicationType`/`freezeChannels`/`channels`) e o `via()` dinâmico que mapeia cada `CommunicationChannel` congelado para o driver (`Email=>mail`, `InApp=>database`, `Whatsapp=>whatsapp`). Os listeners de domínio (PendenciaSolicitada etc.) chamam `deliver()`.
- **11-03**: o `WhatsAppChannel` localiza a sua linha por `$notification->communicationIds['whatsapp']` (id congelado) — sem lookup heurístico por chave.

### `RegistrarEnvioComunicacao` (listener auto-descoberto, registro único)
- Dois handles type-hintados (auto-descoberta `Str::is('handle*')`, confirmada no `event:list`; NUNCA `Event::listen`): `handleNotificationSent(NotificationSent)` → `markAsSent()`; `handleNotificationFailed(NotificationFailed)` → `markAsFailed($motivo)` (motivo de `$event->data['exception']`).
- **Gate**: trata SOMENTE `mail` e `database` (`TRACKED_CHANNELS`). **whatsapp EXCLUÍDO** — o `WhatsAppChannel` (11-03) é o dono único da linha whatsapp (distingue `enviado` de `bloqueado`); tratar aqui sobrescreveria `bloqueado→enviado` (fachada). Resolve a linha por `$event->channel` no mapa congelado; sem mapa (conta) → null (no-op), `LogNotificationSent` cuida do `EmailLog`.
- Coexiste com `LogNotificationSent` em `NotificationSent` sem conflito (gates por propriedade distinta: `emailLogId` vs `communicationIds`).

## Mapa CA → teste (verde)
| HU / RN | Teste |
|---|---|
| HU-090/094 — canais por tipo (mapa ∩ toggles), via() congelado | NotificationDispatcherTest::test_mapa_com_email_e_in_app_... |
| HU-014/094 — toggle off degrada comunicado (desativado + auditoria) | NotificationDispatcherTest::test_toggle_in_app_off_..., test_whatsapp_no_mapa_..., test_todos_os_canais_off_... |
| HU-096 — ledger na_fila → enviado/falhou | RegistrarEnvioComunicacaoTest::test_envio_real_..., test_notification_failed_... |
| Anti-fachada — whatsapp não é tratado aqui | RegistrarEnvioComunicacaoTest::test_canal_whatsapp_nao_e_tratado_... |
| Lição Fases 8/9 — auto-descoberta, registro único | RegistrarEnvioComunicacaoTest::test_exatamente_um_listener_..., test_handles_fazem_type_hint_... |

## Deviations from Plan
None - plano executado exatamente como escrito. Escopo respeitado: tocados apenas `NotificationDispatcher` + `RegistrarEnvioComunicacao` + os dois testes. AppServiceProvider, User, WhatsAppChannel (11-03) e o contrato `ProcessNotification` (11-01) NÃO foram alterados.

## Issues Encountered
- O contrato `ProcessNotification` (frozen em 11-01) não expõe `title/summary/meta`; o dispatcher usa `CommunicationType::label()` como `title` (default honesto) — `summary`/`meta` ficam vazios como seam para 11-05/06 enriquecerem.
- O mapa `{driver→communicationId}` viaja como propriedade pública `communicationIds` na Notification (mesmo arranjo do `emailLogId`, que também não está em interface). 11-05/06 DEVEM declarar a propriedade para evitar dynamic property (PHP 8.2+) — documentado acima.

## Next Phase Readiness
- **11-03** pode congelar/ler `communicationIds['whatsapp']` para fechar a linha whatsapp (bloqueado/enviado) — par paralelo já compatível.
- **11-05/06** têm a assinatura de `deliver`, o contrato de `communicationIds` e a regra do `via()` dinâmico; os listeners de domínio chamam `deliver`.
- **11-07** chama `deliver` nas rotinas; a idempotência continua via `communications` (sem schema novo).
- **Sem bloqueios.** Zero dupla verdade; zero dependência nova. Os testes do dispatcher usam `Notification::fake` (independentes do driver whatsapp de 11-03).

---
*Phase: 11-pendencias-e-comunicacao*
*Completed: 2026-06-15*
