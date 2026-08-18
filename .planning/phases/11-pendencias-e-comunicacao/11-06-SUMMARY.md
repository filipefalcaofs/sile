---
phase: 11-pendencias-e-comunicacao
plan: 06
subsystem: notifications
tags: [hu-077, hu-094, hu-096, hu-014, resultado-expresso, dispatcher, process-notification, ledger, anti-regressao, anti-fachada]

# Dependency graph
requires:
  - phase: 11-pendencias-e-comunicacao
    provides: "11-01 (Communication + enums + contrato ProcessNotification) e 11-04 (NotificationDispatcher::deliver + RegistrarEnvioComunicacao + mapa {driver→communicationId} congelado)"
  - phase: 09-fluxo-expresso
    provides: "ResultadoEmitido + NotificarResultadoExpresso (listener auto-descoberto) + ResultadoExpressoNotification (só e-mail/EmailLog) + assuntos parametrizados (expresso.notificacao.assunto_*)"
provides:
  - "ResultadoExpressoNotification como ProcessNotification multicanal (communicationType=resultado; via() dinâmico; toDatabase()); construtor ganha 3º parâmetro ?int viabilityRequestId"
  - "NotificarResultadoExpresso refatorado: degrada por toggle de TIPO, depois dispatcher->deliver (in-app + histórico communications); EmailLog removido deste fluxo"
  - "Testes da Fase 9 migrados para asserir via dispatcher/communications (ResultadoExpressoNotificationTest, NotificarResultadoExpressoListenerTest); ExpressoSmokeTest verde sem mudança"
affects:
  - "11-08 (histórico HU-096 e central in-app exibem a comunicação de tipo 'resultado' — linha communications + payload toDatabase)"
  - "11-10 (verificação anti-regressão da Fase 9: notificação 1×, decisão/auditoria intactas)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Notification de processo migrada do par EmailLog/failed() para ProcessNotification + communicationIds (ledger é a fonte de verdade; RegistrarEnvioComunicacao fecha o ciclo)"
    - "Listener de domínio mantém o gate do toggle de TIPO e o gate 'sem-destinatario' ANTES de delegar a resolução de canais ao dispatcher"
    - "via() dinâmico com fallback ['mail'] (fora do dispatcher) — robusto a uso direto sem perder o canal histórico"

key-files:
  created: []
  modified:
    - "app/Notifications/ResultadoExpressoNotification.php"
    - "app/Listeners/NotificarResultadoExpresso.php"
    - "tests/Feature/Expresso/ResultadoExpressoNotificationTest.php"
    - "tests/Feature/Expresso/NotificarResultadoExpressoListenerTest.php"

key-decisions:
  - "EmailLog/failed()/emailLogId REMOVIDOS da ResultadoExpressoNotification (resolve a contradição interna do plano Task1×Task2): manter o par seria código morto + dupla verdade — exatamente o que o plano elimina. O ledger communications passa a ser a fonte única e o RegistrarEnvioComunicacao (11-04) fecha na_fila→enviado/falhou por NotificationSent/Failed via communicationIds"
  - "Construtor ganha ?int viabilityRequestId = null (default null): preserva os testes unitários da Notification que constroem com 2 args (toMail/assunto/sem-anexo); o listener passa o id real para identificar a linha do ledger e montar o link de acompanhamento (toDatabase)"
  - "Gate 'sem-destinatario' preservado como antes (requester null OU e-mail em branco → audita e retorna) — mantém o teste da Fase 9 e a degradação honesta; o in-app não é tentado sem requerente com e-mail (escopo do plano respeitado)"
  - "toDatabase() usa route('portal.solicitacoes.show', viabilityRequestId) como link de acompanhamento (mesma rota já usada por PendenciaSolicitadaNotification)"

patterns-established:
  - "Migração de Notification só-e-mail → ProcessNotification: declarar public array frozenChannels/communicationIds, implementar o contrato, via() dinâmico, toDatabase() e remover o par EmailLog/failed()"

# Metrics
duration: ~20min
completed: 2026-06-15
---

# Phase 11 Plan 06: Unificação do Resultado Expresso no Dispatcher Summary

**A última notificação de processo que ia só por e-mail (HU-077) entrou no roteador multicanal do EP11: `ResultadoExpressoNotification` virou `ProcessNotification` (communicationType=`resultado`, `via()` dinâmico, `toDatabase()` para a central in-app) e `NotificarResultadoExpresso` passou a chamar `NotificationDispatcher::deliver` — ganhando in-app + histórico `communications` (HU-096) além do e-mail, sem anexo de TVL (RN-004) e com os assuntos parametrizados (HU-014) intactos. O toggle de TIPO `features.notificacao_resultado_expresso` segue como porta de entrada (off → audita `desativado` e não notifica); os CANAIS agora saem do `mapa_canais ∩ toggles`. ANTI-REGRESSÃO Fase 9 garantida: os testes migraram para asserir `communications` (não mais `email_logs`), a notificação segue 1× por auto-descoberta e o `ExpressoSmokeTest` ficou verde sem nenhuma mudança. Zero dupla verdade, zero dependência nova.**

## Performance
- **Started:** 2026-06-15 (Wave 3, paralelo com 11-05 e 11-07)
- **Tasks:** 2 (cada uma em ciclo TDD RED→GREEN com evidência fresca)
- **Files criados:** 0 | **modificados:** 4

## Accomplishments
- `ResultadoExpressoNotification` é `ProcessNotification` multicanal: `communicationType()=Resultado`, `viabilityRequestId()`, `freezeChannels()/channels()`, `via()` dinâmico (lê os canais congelados; fallback `['mail']`) e `toDatabase()` (desfecho + link do portal para a central in-app HU-096). `toMail()` preservado: assuntos parametrizados (`expresso.notificacao.assunto_deferida/_indeferida`) e SEM anexo de TVL (RN-004).
- `NotificarResultadoExpresso` refatorado: mantém o gate do toggle de TIPO (off → `notificacoes`/`resultado-expresso`/`desativado` + return) e o gate `sem-destinatario`, depois delega a `NotificationDispatcher::deliver($requester, new ResultadoExpressoNotification($protocolo, $outcome, $request->id))`. `EmailLog` saiu deste fluxo (import órfão removido).
- Resultado expresso ganha o ledger `communications` (mapa default `resultado` = `[email, in_app]` → duas linhas `na_fila`) e in-app real — fechado pelo `RegistrarEnvioComunicacao` (11-04) por `NotificationSent/Failed`.
- ANTI-REGRESSÃO Fase 9: `ResultadoExpressoNotificationTest` (assuntos, sem anexo, enfileirável) + `NotificarResultadoExpressoListenerTest` (1×, toggle off audita, indeferimento usa assunto, sem-destinatario) migrados e verdes; `ExpressoSmokeTest` verde sem mudança (o dispatcher ainda chama `$requester->notify`).
- Suíte completa SQLite verde: **1166 passed / 5966 assertions** (`--exclude-group=postgis`).

## Task Commits
1. **Task 1: ResultadoExpressoNotification → ProcessNotification** — `6fdad6a` (refactor) — TDD: RED (não é ProcessNotification; `freezeChannels()`/`toDatabase()` inexistentes) → GREEN (10 testes, 19 asserções).
2. **Task 2: NotificarResultadoExpresso → dispatcher** — `5102462` (refactor) — TDD: RED (tabela `communications` vazia) → GREEN (listener + smoke: 7 testes, 27 asserções).

## Mapa CA → teste (verde)
| HU / RN | Teste |
|---|---|
| HU-094/096 — resultado ganha in-app + histórico (dispatcher) | NotificarResultadoExpressoListenerTest::test_notifica_o_requerente_uma_unica_vez (communications email+in_app na_fila) |
| HU-077 RN-005 — assuntos parametrizados | ResultadoExpressoNotificationTest::test_assunto_de_deferimento/_indeferimento/_override |
| HU-077 RN-004 — e-mail sem anexo de TVL | ResultadoExpressoNotificationTest::test_nao_anexa_o_tvl |
| HU-096 — in-app traz desfecho + link | ResultadoExpressoNotificationTest::test_to_database_traz_o_desfecho_e_o_link_de_acompanhamento |
| 11-04 — via() dinâmico lê canais congelados | ResultadoExpressoNotificationTest::test_via_e_dinamico_e_le_os_canais_congelados / _fallback |
| HU-014 — toggle de TIPO off degrada comunicado | NotificarResultadoExpressoListenerTest::test_toggle_desligado_nao_envia_e_audita_a_degradacao |
| ANTI-REGRESSÃO Fase 9 — 1×, smoke verde | NotificarResultadoExpressoListenerTest + ExpressoSmokeTest |

## Evidência fresca (output real)
- `--filter=ResultadoExpressoNotificationTest`: **10 passed / 19 assertions**.
- `--filter="NotificarResultadoExpressoListenerTest|ExpressoSmokeTest"`: **7 passed / 27 assertions**.
- `--filter="ResultadoExpressoNotificationTest|NotificarResultadoExpressoListenerTest|ExpressoSmokeTest"` (filtro do plano): **17 passed / 46 assertions** (baseline pré-refactor: 14).
- `tests/Feature/Expresso/` (anti-regressão do fluxo expresso inteiro): **102 passed / 402 assertions**.
- Suíte completa SQLite (`--exclude-group=postgis`): **1166 passed / 5966 assertions**.
- `vendor/bin/pint --dirty --format agent`: passed.

## Deviations from Plan
**1. [Decisão de implementação] Remoção do par `EmailLog`/`failed()`/`emailLogId` da `ResultadoExpressoNotification`.**
- **Contexto:** o plano tinha uma contradição interna — Task 1 dizia "manter ShouldQueue + failed()" (o `failed()` usa `EmailLog::find($this->emailLogId)`), enquanto a Task 2 mandava "remover o uso de EmailLog para este fluxo (imports órfãos limpos)". Como o listener deixou de criar o `EmailLog` e de injetar o `emailLogId`, manter o `failed()`/`emailLogId` viraria código morto e manteria a dupla verdade (EmailLog vs communications) que o próprio objetivo do plano elimina.
- **Resolução:** removidos `public ?int $emailLogId`, o método `failed()` e o import de `EmailLog`; adicionados `public array $frozenChannels = []` e `public array $communicationIds = []` (contrato do 11-04). O fechamento do ledger — inclusive falha — passa a ser 100% do `RegistrarEnvioComunicacao` (11-04) via `NotificationSent/NotificationFailed` + `communicationIds`, mais honesto (registra por canal no ledger de processo). `ShouldQueue` foi mantido.
- **Impacto:** nenhum teste cobria `failed()`/`emailLogId` da Notification (verificado por grep); suíte completa verde.

**2. [Escopo] `toWhatsApp()` NÃO adicionado.** O mapa default de `resultado` é `[email, in_app]` (sem whatsapp) e o plano só pede `via()` dinâmico + `toDatabase()`. Adicionar `toWhatsApp` seria fora do escopo; quando `resultado` ganhar whatsapp no mapa, o método entra junto (padrão do `WhatsAppChannel`).

## Issues Encountered
- Estado intermediário entre Task 1 e Task 2: após remover `emailLogId` da Notification, o listener antigo ainda atribuía `$notification->emailLogId` (propriedade dinâmica — deprecation, não fatal; `phpunit.xml` não converte deprecations em erro). Resolvido na Task 2 ao refatorar o listener. Os filtros foram rodados por task, sem rodar a suíte completa nesse intervalo.

## Next Phase Readiness
- **11-08** lê `communications` (tipo `resultado`) para o histórico HU-096 e `notifications` (payload `toDatabase`: type/protocol_number/outcome/title/message/url) para a central in-app.
- **11-10** tem a anti-regressão coberta: notificação 1× por auto-descoberta, decisão/auditoria autoritativa da Fase 9 intactas (não dependem deste listener).
- **Sem bloqueios.** Zero dupla verdade, zero dependência nova; o e-mail ao cidadão continua sem anexo de TVL.

---
*Phase: 11-pendencias-e-comunicacao*
*Completed: 2026-06-15*
