---
phase: 11-pendencias-e-comunicacao
plan: 01
subsystem: database
tags: [notifications, database-channel, ledger, communications, enums, contract, inertia-react]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    provides: ResultadoExpressoNotification + EmailLog (padrão markAsSent/markAsFailed)
  - phase: 10-analise-tecnica
    provides: PendenciaSolicitadaNotification + ciclo de pendência (PendenciaService)
provides:
  - "Canal database nativo de Notifications (tabela notifications) — base da central in-app (HU-090)"
  - "Ledger imutável communications (model + 3 enums + factory + 4 marcadores honestos) — fonte de verdade do histórico por processo (HU-096) e da idempotência das rotinas (Wave 5)"
  - "Contrato App\\Notifications\\Contracts\\ProcessNotification (viabilityRequestId/communicationType/freezeChannels/channels)"
affects:
  - "11-03 (channel marca bloqueado no Communication via markAsBlocked)"
  - "11-04 (NotificationDispatcher cria communications + congela canais via freezeChannels)"
  - "11-05/06 (Notifications de processo implementam ProcessNotification)"
  - "11-07 (rotinas/scheduler usam communications para idempotência)"
  - "11-08 (histórico HU-096 lê communications; central in-app lê notifications)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Canal database NATIVO do Laravel para in-app (sem tabela própria)"
    - "Ledger dedicado e imutável por processo (espelha access_logs/viability_request_transitions)"
    - "Marcadores honestos de status espelhando EmailLog (status + carimbo, nunca 'enviado' fictício)"
    - "Guarda anti-fachada: markAsSent é no-op sobre status terminal honesto (bloqueado/desativado)"
    - "Contrato de congelamento de canais no disparo (espelha VerifyEmailQueued::freezeUrlFor)"

key-files:
  created:
    - "database/migrations/2026_06_15_034848_create_notifications_table.php"
    - "database/migrations/2026_06_15_035133_create_communications_table.php"
    - "app/Enums/CommunicationChannel.php"
    - "app/Enums/CommunicationType.php"
    - "app/Enums/CommunicationStatus.php"
    - "app/Models/Communication.php"
    - "database/factories/CommunicationFactory.php"
    - "app/Notifications/Contracts/ProcessNotification.php"
    - "tests/Feature/Comunicacao/DatabaseNotificationChannelTest.php"
    - "tests/Feature/Comunicacao/CommunicationModelTest.php"
  modified: []

key-decisions:
  - "communications.viability_request_id e recipient_user_id usam nullOnDelete (preserva a linha de auditoria do ledger imutável mesmo se o processo/usuário for removido)"
  - "meta usa jsonb (Postgres) — Laravel mapeia para text no SQLite de teste; cast 'array' no model"
  - "CommunicationType cobre os 6 tipos das Waves 3/5 de uma vez (nenhum plano posterior edita o enum)"
  - "Contrato expõe freezeChannels(array CommunicationChannel)/channels(): dispatcher congela, via() lê"

patterns-established:
  - "Marcadores honestos: markAsSent/markAsFailed/markAsBlocked/markAsDisabled mudam status + carimbo"
  - "Guarda anti-fachada no markAsSent (no-op em bloqueado/desativado)"

# Metrics
duration: ~11min
completed: 2026-06-15
---

# Phase 11 Plan 01: Fundação de Comunicação (notifications + ledger communications + contrato) Summary

**Canal database nativo habilitado (base in-app HU-090), ledger imutável `communications` por processo (HU-096) com 3 enums e 4 marcadores honestos + guarda anti-fachada, e o contrato `ProcessNotification` que congela canais no disparo — tudo coberto por feature tests, EmailLog intacto, zero dependência nova.**

## Performance

- **Duration:** ~11 min
- **Started:** 2026-06-15T00:46:00-03:00
- **Completed:** 2026-06-15T00:57:00-03:00
- **Tasks:** 3
- **Files criados:** 10

## Accomplishments

- Tabela `notifications` (canal `database` nativo) migrada — `User` (já Notifiable) recebe in-app real: `unreadNotifications`/`markAsRead`/`read_at`.
- Ledger dedicado e imutável `communications`: FKs nullable + 2 índices compostos, `meta` jsonb, carimbos `queued_at`/`sent_at`/`failed_at`, 3 enums string-backed e os 4 marcadores honestos do model.
- Guarda anti-fachada testada: `markAsSent()` é no-op quando o status já é terminal honesto (`bloqueado`/`desativado`).
- Contrato `ProcessNotification` publicado para o dispatcher (11-04) e as Notifications de processo (11-05/06).
- Suíte completa verde: 1143 SQLite + 29 @group postgis (as duas migrations aplicam em ambos os bancos).

## Task Commits

Cada task foi commitada atomicamente:

1. **Task 1: tabela notifications + smoke do canal database** — `892552d` (feat)
2. **Task 2: ledger communications (migration + enums + model + factory + marcadores)** — `6dc81fd` (feat)
3. **Task 3: contrato ProcessNotification** — `127c781` (feat)

_TDD estrito nas Tasks 1 e 2 (RED confirmado com output real antes do GREEN); Task 3 é contrato puro (interface), verificada por pint + suíte verde._

## Contratos para os próximos planos (insumo direto)

### Tabela `notifications` (canal database nativo)
Estrutura padrão do Laravel (uuid id, type, notifiable morph, data json, read_at). A **central in-app (11-08)** lê `$user->notifications` / `$user->unreadNotifications` e marca com `markAsRead()`. As Notifications de processo escrevem nela via `toDatabase()` quando o canal `database` está nos canais congelados.

### Enums (string-backed, em `App\Enums`)
- `CommunicationChannel`: `Email='email'`, `InApp='in_app'`, `Whatsapp='whatsapp'` (+ `label()`).
- `CommunicationType`: `PendenciaAberta='pendencia_aberta'`, `PendenciaRespondida='pendencia_respondida'`, `PendenciaExpirada='pendencia_expirada'`, `PrazoVencendo='prazo_vencendo'`, `EscalonamentoSla='escalonamento_sla'`, `Resultado='resultado'` (+ `label()`).
- `CommunicationStatus`: `NaFila='na_fila'`, `Enviado='enviado'`, `Falhou='falhou'`, `Bloqueado='bloqueado'`, `Desativado='desativado'` (+ `label()`).

### Tabela/Model `communications`
Colunas: `id`, `viability_request_id` (FK nullable, nullOnDelete), `recipient_user_id` (FK nullable, nullOnDelete), `channel`, `type`, `status`, `title`, `summary?`, `error_message?`, `meta` (jsonb, cast `array`), `queued_at?`, `sent_at?`, `failed_at?`, `timestamps`.
Índices compostos: `(viability_request_id, type, channel)` — **idempotência das rotinas (Wave 5)**; `(viability_request_id, created_at)` — **consulta cronológica do histórico (HU-096)**.

Casts do model: `channel`/`type`/`status` (enums), `meta` (array), `queued_at`/`sent_at`/`failed_at` (datetime). Relações: `viabilityRequest()`, `recipient()` (FK `recipient_user_id`).

**Marcadores honestos (assinaturas):**
- `markAsSent(): void` → status `Enviado` + `sent_at`. **GUARDA:** no-op se já está `Bloqueado`/`Desativado` (um NotificationSent tardio não transforma bloqueio real em envio fictício).
- `markAsFailed(string $error): void` → status `Falhou` + `error_message` + `failed_at`.
- `markAsBlocked(?string $motivo = null): void` → status `Bloqueado` + `error_message` (canal on mas indisponível — 11-03).
- `markAsDisabled(): void` → status `Desativado` (toggle off — degradação honesta).

**Shape sugerido do `meta`** (livre por design; ex.: `['pendencia_id' => int, 'tentativa' => int, ...]`) — o dispatcher (11-04) define as chaves por tipo de comunicação.

`CommunicationFactory` states: `sent()`, `failed(?error)`, `blocked(?motivo)`, `disabled()`, `inApp()`, `whatsapp()`.

### Contrato `App\Notifications\Contracts\ProcessNotification`
```
viabilityRequestId(): ?int
communicationType(): CommunicationType
freezeChannels(array $channels): void   // array<int, CommunicationChannel>
channels(): array                       // array<int, CommunicationChannel>
```
**Pipeline (espelha `VerifyEmailQueued::freezeUrlFor`):** o `NotificationDispatcher` (11-04) resolve os canais SÍNCRONO no disparo (toggles + `notificacoes.mapa_canais` + preferência), cria as linhas em `communications`, **congela** os canais com `freezeChannels()` e chama `notify()`. No worker, o `via()` de cada Notification **lê** `channels()` (sem reresolver toggles) e traduz cada `CommunicationChannel` para o canal nativo do Laravel: `Email => 'mail'`, `InApp => 'database'`, `Whatsapp => canal customizado` (Wave 2). A interface ainda **NÃO** é implementada nas Notifications existentes (isso é 11-05/06).

## Decisions Made

- **`nullOnDelete` nas duas FKs do ledger** (em vez de `cascadeOnDelete` como em transitions): `communications` é registro de auditoria imutável; perder a linha ao remover o processo/usuário apagaria histórico. Como ambas as FKs são nullable, anular preserva a linha.
- **`jsonb` em `meta`**: honra o CONTEXT ("meta jsonb") no Postgres; o grammar do SQLite mapeia `jsonb` para `text`, então o teste (SQLite :memory:) prova a portabilidade. Cast `array` no model.
- **Guarda inline no `markAsSent`** (sem método extra no enum): `in_array($this->status, [Bloqueado, Desativado], true)` — claro e autodocumentado; só `markAsSent` precisa da guarda nesta fase.
- **`CommunicationType` com os 6 tipos de uma vez**: evita que 11-05/06/07 editem o enum.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- O fixer `fully_qualified_strict_types` do Pint reescreveu `{@see communicationType()}` (método) para `{@see CommunicationType()}` (casou com a classe importada), tornando a referência ambígua. Resolvido reescrevendo a linha do PHPDoc sem `{@see}` nos métodos; Pint passou estável depois.

## User Setup Required

None - nenhuma configuração de serviço externo. Zero dependência nova adicionada.

## Next Phase Readiness

- **11-03** pode marcar `Bloqueado` no `Communication` via `markAsBlocked()` (canal WhatsApp on + gateway indisponível).
- **11-04** tem o contrato `ProcessNotification` (freeze/read de canais) e o model `Communication` para criar o ledger no disparo.
- **11-05/06** implementam `ProcessNotification` nas Notifications de processo e migram os testes das Fases 9/10.
- **11-08** lê `communications` (histórico HU-096) e `notifications` (central in-app).
- **Sem bloqueios.** EmailLog permanece intacto (comunicações de CONTA); `communications` é a fonte de verdade só de PROCESSO (zero dupla verdade).

---
*Phase: 11-pendencias-e-comunicacao*
*Completed: 2026-06-15*
