---
phase: 11-pendencias-e-comunicacao
plan: 07
subsystem: scheduler
tags: [hu-093, hu-147, hu-091, scheduler, sla, idempotencia, communications, dispatcher, anti-fachada]

# Dependency graph
requires:
  - phase: 11-pendencias-e-comunicacao
    provides: "11-04 (NotificationDispatcher::deliver + contrato ProcessNotification/communicationIds) e 11-02 (notificacoes.vencimento.antecedencia_dias, notificacoes.escalonamento.tratamento/.gestor_role, notificacoes.mapa_canais)"
  - phase: 10-analise-tecnica
    provides: "analysis_due_at/analysis_stage_started_at + AnalysisSlaService::statusFor (fonte única do SLA) + AnalysisPendencyStatus::Expirada"
  - phase: 09-fluxo-expresso
    provides: "padrão de comando idempotente (ExpressoIndeferirSemBapCommand) + scheduler withoutOverlapping/onOneServer (routes/console.php)"
provides:
  - "notificacoes:alertar-vencimentos (HU-093, dailyAt 07:00): alerta analista (analysis_due_at) e requerente (pendencia.due_at) sobre vencimentos próximos — SÓ notifica"
  - "notificacoes:escalonar-sla (HU-147, hourly): escala por SLA na fonte única analysis_due_at (amarelo→analista, vencido→gestor parametrizável) — SÓ notifica"
  - "pendencias:expirar (HU-091 RN-005, dailyAt 06:00): marca Expirada + notifica analista, SEM decisão automática"
  - "3 Notifications de processo (PrazoVencendo/ProcessoEscalonado/PendenciaExpirada) multicanal via dispatcher"
affects:
  - "11-08 (central/histórico HU-096 exibem as communications prazo_vencendo/escalonamento_sla/pendencia_expirada geradas aqui)"
  - "11-10 (smoke do scheduler + verificação da fase consomem estas 3 rotinas)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Comando de scheduler idempotente que SÓ notifica (sem transição/timeline → sem dupla contagem HU-129), espelhando ExpressoIndeferirSemBapCommand"
    - "Idempotência por consulta ao ledger communications (viability_request_id + type + recipient_user_id + janela temporal), sem schema novo (RN-004)"
    - "Escalonamento lê o semáforo da FONTE ÚNICA via AnalysisSlaService::statusFor (sem recalcular prazo à parte — CA-02)"
    - "Expiração idempotente pela própria transição de estado Aberta→Expirada (a 2ª passada não acha mais o que processar)"
    - "Notifications de processo implementam ProcessNotification (via() dinâmico + communicationIds) e percorrem o NotificationDispatcher (multicanal + ledger)"

key-files:
  created:
    - "app/Console/Commands/AlertarVencimentosCommand.php"
    - "app/Console/Commands/EscalonarSlaCommand.php"
    - "app/Console/Commands/ExpirarPendenciasCommand.php"
    - "app/Notifications/PrazoVencendoNotification.php"
    - "app/Notifications/ProcessoEscalonadoNotification.php"
    - "app/Notifications/PendenciaExpiradaNotification.php"
    - "tests/Feature/Comunicacao/AlertarVencimentosCommandTest.php"
    - "tests/Feature/Comunicacao/EscalonarSlaCommandTest.php"
    - "tests/Feature/Comunicacao/ExpirarPendenciasCommandTest.php"
  modified:
    - "routes/console.php"
    - "config/sile.php"
    - "database/seeders/ParameterSeeder.php"
    - "tests/Feature/Seeders/ParameterSeederTest.php"

key-decisions:
  - "alertar-vencimentos cobre só prazos AINDA NÃO vencidos (analysis_due_at > now e <= now+antecedência); o vencido é tratamento da HU-147 (escalonar-sla) — evita sobreposição entre as duas rotinas"
  - "Idempotência do escalonamento usa recipient_user_id como discriminador: amarelo (analista) e vencido (gestor) não colidem mesmo com o mesmo type=escalonamento_sla; cada gestor é deduplicado individualmente"
  - "Janela de idempotência: processos usam created_at >= analysis_stage_started_at (a etapa atual); pendências usam created_at >= (due_at − antecedência) — sem persistir pendency_id no ledger (o dispatcher 11-04 não grava meta)"
  - "Expiração NÃO faz checagem extra em communications: a transição Aberta→Expirada já é a idempotência; checar o ledger colapsaria múltiplas expirações do mesmo processo (o ledger não discrimina pendência)"
  - "Escalonamento usa whereHas('roles') em vez do scope spatie role() para não lançar RoleDoesNotExist se o gestor_role parametrizado não existir (degrada honesto: zero destinatários)"
  - "DESVIO (anti-fachada): adicionado pendencia_expirada ao notificacoes.mapa_canais (config/sile.php + ParameterSeeder) — sem o mapeamento o dispatcher resolvia ZERO canais e a notificação de expiração não saía; contagem de parâmetros inalterada (78)"

patterns-established:
  - "Rotina de prazo do EP11 = comando idempotente + Notification ProcessNotification + agenda withoutOverlapping/onOneServer, tudo passando pelo NotificationDispatcher"
  - "Idempotência de notificação sem tabela de controle: consulta ao próprio ledger communications por (processo, tipo, destinatário, janela)"

# Metrics
duration: ~40min
completed: 2026-06-15
---

# Phase 11 Plan 07: Scheduler de prazos (vencimento, escalonamento, expiração) — Summary

**Ativa o que estava DORMENTE no EP11: três rotinas idempotentes de scheduler que SÓ notificam (exceto a expiração, que tem regra própria de estado, mas SEM decisão automática). `notificacoes:alertar-vencimentos` (HU-093, 07:00) alerta analista/requerente sobre prazos próximos; `notificacoes:escalonar-sla` (HU-147, hourly) escala por SLA lendo o semáforo da FONTE ÚNICA `analysis_due_at` via `AnalysisSlaService` (amarelo→analista, vencido→gestor parametrizável); `pendencias:expirar` (HU-091 RN-005, 06:00) marca `Expirada` + notifica o analista, mantendo o estado do processo. Todas percorrem o `NotificationDispatcher` (multicanal + ledger) e se desduplicam consultando `communications` — sem schema novo. 14 feature tests verdes (janela, idempotência 2×, no-op honesto, anti-dupla-contagem); suíte SQLite completa 1179 verde; zero dependência nova.**

## Performance
- **Started:** 2026-06-15 (Wave 3, paralela a 11-05/11-06)
- **Tasks:** 3 (cada uma em ciclo TDD RED→GREEN com evidência real)
- **Files criados:** 9 | **Files modificados:** 4

## Accomplishments
- HU-093 ativa: `AlertarVencimentosCommand` alerta o analista (processos em_analise com `analysis_due_at` na antecedência) e o requerente (pendências abertas com `due_at` na antecedência), só notificando — sem mexer no estado nem na timeline.
- HU-147 ativa: `EscalonarSlaCommand` lê `AnalysisSlaService::statusFor(analysis_due_at, analysis_stage_started_at)` — a MESMA fonte da fila/badge da Fase 10 — e escala por faixa (amarelo→analista, vencido→gestor da role parametrizável), tratamento por `notificacoes.escalonamento.tratamento`, NUNCA decidindo/transicionando.
- HU-091 RN-005 ativa: `ExpirarPendenciasCommand` marca `AnalysisPendencyStatus::Expirada` (transação + auditoria RN-002) e notifica o analista, SEM indeferimento automático (rito SEDUR não inventado) — o status do processo permanece intacto.
- 3 Notifications de processo (`PrazoVencendo`/`ProcessoEscalonado`/`PendenciaExpirada`) implementam `ProcessNotification` (via() dinâmico + `communicationIds`), com `toMail`/`toDatabase`/`toWhatsApp`, entregues pelo `NotificationDispatcher`.
- Idempotência (RN-004) provada (rodar 2× não duplica) reusando o índice `(viability_request_id, type, channel)` do ledger — sem tabela de controle nova.
- Suíte completa SQLite: **1179 passed / 6016 assertions** (`--exclude-group=postgis`), incluindo os testes de 11-02 ajustados (anti-regressão do mapa).

## Task Commits
1. **Task 1: notificacoes:alertar-vencimentos (HU-093)** — `8f314ee` (feat) — TDD: RED (5 erros "command does not exist") → GREEN (5 testes, 17 asserções).
2. **Task 2: notificacoes:escalonar-sla (HU-147)** — `410c1a4` (feat) — TDD: RED (5 erros; a asserção do AnalysisSlaService já provava o semáforo Vermelho) → GREEN (5 testes, 20 asserções).
3. **Task 3: pendencias:expirar (HU-091 RN-005)** — `aa4275c` (feat) — TDD: RED (4 erros) → GREEN (4 testes, +ajuste anti-fachada do mapa_canais).

## Contratos para os próximos planos (insumo direto)

### Signatures e cadência (routes/console.php — withoutOverlapping/onOneServer)
| Comando | Cadência | O que faz | Tipo de Communication |
|---|---|---|---|
| `notificacoes:alertar-vencimentos` | `dailyAt('07:00')` | alerta analista (analysis_due_at) e requerente (pendencia.due_at) na antecedência | `prazo_vencendo` |
| `notificacoes:escalonar-sla` | `hourly()` | amarelo→analista, vencido→gestor (role parametrizável) na fonte única | `escalonamento_sla` |
| `pendencias:expirar` | `dailyAt('06:00')` | Aberta vencida → Expirada + notifica analista (sem decisão) | `pendencia_expirada` |

### Idempotência por consulta ao ledger (chave/janela)
- **Vencimento de PROCESSO:** existe `communications` com `(viability_request_id, type=prazo_vencendo, recipient_user_id=analista)` e `created_at >= analysis_stage_started_at` (a etapa atual reabre a janela ao reentrar). Se há, pula.
- **Vencimento de PENDÊNCIA:** mesma chave para o requerente, com janela `created_at >= (due_at − antecedencia_dias)` (início da janela de antecedência atual).
- **Escalonamento:** `(viability_request_id, type=escalonamento_sla, recipient_user_id)` com `created_at >= analysis_stage_started_at`. O `recipient_user_id` separa amarelo (analista) de vencido (cada gestor) — o mesmo type não colide.
- **Expiração:** idempotência pela própria transição `Aberta→Expirada` (a 2ª passada não encontra mais Aberta vencida) — NÃO há checagem extra em communications (o ledger não guarda pendency_id e colapsaria múltiplas expirações do mesmo processo).

### Notifications de processo
- Implementam `ProcessNotification` + `ShouldQueue`, declaram `public array $communicationIds = []`, congelam canais em `freezeChannels()`/`channels()` e o `via()` mapeia `Email=>mail`, `InApp=>database`, `Whatsapp=>whatsapp`. Conteúdo (assunto/detalhe/url) montado pelo comando; o destino do WhatsApp é resolvido pelo `WhatsAppChannel`.

## Pendências SEDUR registradas (não inventadas)
- **Gestor "do setor" (HU-147):** não existe no schema. O default escala a TODOS os usuários da role `notificacoes.escalonamento.gestor_role` (default `gestor`). O roteamento ao setor específico depende de decisão da SEDUR — não foi inventado.
- **Rito de não-resposta de pendência (HU-091):** indeferir por prazo é rito SEDUR ainda não definido. A rotina hoje **expira + notifica + mantém o estado** do processo — SEM decisão automática. Ativar o tratamento (ex.: indeferir/redistribuir) é trabalho futuro com regra explícita.
- **Tratamentos de ação do escalonamento (ex.: redistribuir):** `notificacoes.escalonamento.tratamento` só executa os valores de NOTIFICAÇÃO (`notificar_analista`/`notificar_gestor`). Valores de ação são no-op honesto (não fingem ação) até a SEDUR definir a regra.

## Mapa CA → teste (verde)
| HU / RN | Teste |
|---|---|
| HU-093 — alerta de vencimento (analista/requerente) na antecedência | AlertarVencimentosCommandTest |
| HU-147 — escalonamento (amarelo→analista, vencido→gestor) na fonte única | EscalonarSlaCommandTest |
| HU-147 CA-02 — prazo coincide com AnalysisSlaService (sem fonte paralela) | EscalonarSlaCommandTest::test_prazo_coincide_com_o_analysis_sla_service_sem_fonte_paralela |
| HU-147/093 RN-004 — idempotência (rodar 2× não duplica) | os três testes (test_rodar_duas_vezes...) |
| HU-091 RN-005 — pendência expira + notifica, SEM decisão automática | ExpirarPendenciasCommandTest |
| Anti-dupla-contagem HU-129 — rotinas só notificam (status do processo inalterado) | os três testes (assert de status) |

## Deviations from Plan
**1. [Rule 3 — Blocking/anti-fachada] `pendencia_expirada` ausente do `notificacoes.mapa_canais`.**
- **Encontrado em:** Task 3 (a tabela `communications` ficava vazia após a expiração).
- **Causa raiz:** o mapa de canais (11-02) tinha os 5 outros tipos, mas omitia `pendencia_expirada`; o `NotificationDispatcher` resolve ZERO canais para tipos fora do mapa → a notificação não saía (feature de fachada).
- **Correção:** adicionado `'pendencia_expirada' => ['email', 'in_app']` em `config/sile.php` (fallback) e no `default_value` do `ParameterSeeder` (produção); a asserção do `ParameterSeederTest` foi atualizada (anti-regressão). A **contagem de parâmetros não muda** (78) — só o conteúdo do mapa.
- **Commit:** `aa4275c`.

Nenhum outro desvio. Escopo respeitado: ÚNICO editor de `routes/console.php`; os arquivos de 11-05/11-06 (PendenciaService, NotificarPendencia, ResultadoExpresso) NÃO foram tocados nem commitados.

## Issues Encountered
- O `ProcessNotification` (frozen em 11-01) não expõe título/corpo; cada Notification carrega o conteúdo montado pelo comando (assunto/detalhe/url) e o dispatcher usa `CommunicationType::label()` como `title` da linha do ledger (seam de 11-08 para enriquecer o histórico).
- O scope `User::role()` do spatie lança `RoleDoesNotExist` para role inexistente; troquei por `whereHas('roles', ...)` para degradar honesto (zero gestores) se o `gestor_role` parametrizado não existir.

## Next Phase Readiness
- **11-08** (central/histórico HU-096): lê as `communications` `prazo_vencendo`/`escalonamento_sla`/`pendencia_expirada` (e as `notifications` in-app) geradas por estas rotinas.
- **11-10** (smoke do scheduler + verificação): `schedule:list` lista as três rotinas (07:00 / hourly / 06:00) com withoutOverlapping/onOneServer.
- **Sem bloqueios.** Zero dependência nova; idempotência sem schema novo; fonte de prazo única (analysis_due_at) — sem dupla contagem com a HU-129.

---
*Phase: 11-pendencias-e-comunicacao*
*Completed: 2026-06-15*
