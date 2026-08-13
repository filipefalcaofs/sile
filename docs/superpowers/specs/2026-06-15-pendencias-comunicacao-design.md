# Spec de Design — Fase 11: Pendências e Comunicação (EP11)

**Data:** 2026-06-15
**Status:** Aprovado (brainstorming via agents analista-negocio + arquiteto-tecnico)
**Fontes:** análise de negócio + arquitetura (sessão 2026-06-15), ROADMAP Phase 11, HUs EP11 (HU-090..096, HU-147).

## Problema

Requerentes são notificados de pendências e vencimentos pelos canais configurados e respondem pelo próprio sistema, reabrindo a análise; processos parados além do SLA escalam (analista→gestor). É a **generalização e ativação** do que a Fase 10 deixou pronto: o ciclo em_analise↔em_pendencia, o evento `PendenciaSolicitada` (sem listeners) e o `analysis_due_at` (gravado mas não enforced).

## Decisão de escopo (os dois agents convergiram)

**O ciclo notificar→responder→reabrir NÃO é reconstruído** — a Fase 10 já entrega `PendenciaService::abrir/responder` + portal + e-mail simples + evento. A Fase 11 ADICIONA: multicanal (in-app + WhatsApp), central de pendências/notificações (UI), templates, histórico unificado (HU-096), e ATIVA a expiração de prazo (HU-093) e o escalonamento por SLA (HU-147) que estavam dormentes.

**Real agora:** e-mail (HU-094, já existe) + in-app (canal `database` nativo) + escalonamento/vencimento no scheduler (HU-093/147, reusam `analysis_due_at`/`AnalysisSlaService`) + histórico (HU-096).

**Bloqueado honesto (toggle/contrato, nunca finge):** WhatsApp (HU-095) — canal customizado atrás de `WhatsAppGateway`/`UnavailableWhatsAppGateway` + toggle `features.notificacao_whatsapp` OFF; provedor real quando houver credencial (pode destravar isolado, é API comercial). Convite/resposta via Simplifica/Regin (HU-091 RN-004) → Fase 13 (resposta pelo portal SILE já funciona).

## Arquitetura

### Três mecanismos, sem dupla verdade
1. **In-app → canal `database` nativo** (tabela `notifications` padrão; `read_at`/`markAsRead`). `User` já é `Notifiable`. **Falta a migration** → Wave 1 roda `php artisan make:notifications-table`.
2. **Histórico por processo (HU-096) → tabela dedicada `communications`** (NÃO view). Razão: `EmailLog` não tem `viability_request_id` (é genérico de conta) e `notifications` é morph por usuário — unir por processo seria heurística frágil. Segue o padrão de ledger dedicado imutável (`access_logs`/`viability_request_transitions`).
   - Schema: `viability_request_id` (FK nullable, índice), `recipient_user_id` (FK nullable), `channel` (email/in_app/whatsapp), `type` (pendencia_aberta/pendencia_respondida/prazo_vencendo/escalonamento_sla/resultado…), `status` (na_fila/enviado/falhou/bloqueado/desativado — honesto), `title`/`summary`, `error_message`, `meta` jsonb, `queued_at`/`sent_at`/`failed_at`. Índices `(viability_request_id, type, channel)` (idempotência das rotinas) e `(viability_request_id, created_at)` (consulta).
3. **`EmailLog` permanece** para e-mails transacionais de CONTA (verificação/senha). E-mails de PROCESSO passam a ter `communications` como fonte de verdade (evita dupla verdade).

### Canais e roteamento
- **E-mail (HU-094):** reusa Notification ShouldQueue + toMail (padrão ResultadoExpressoNotification).
- **In-app:** Notifications de negócio declaram `toDatabase()`.
- **WhatsApp (HU-095):** `App\Notifications\Channels\WhatsAppChannel` + contrato `App\Services\Whatsapp\WhatsAppGateway` + DTO `WhatsAppMessage` readonly + `UnavailableWhatsAppGateway` (lança `WhatsAppUnavailableException`). Binding default no `AppServiceProvider::register` (ao lado de Regin/SEFAZ/Bap). `User::routeNotificationForWhatsapp()` = phone E.164. Toggle off → dispatcher não inclui o canal e grava communications=`desativado`. On + indisponível → `bloqueado` + auditoria. On + gateway real (Fase 13) → envia (troca só o binding). Credenciais criptografadas + teste de conexão (HU-014).
- **`NotificationDispatcher::deliver(User $recipient, ProcessNotification $notification)`**: resolve canais (toggles + `notificacoes.mapa_canais` json type→[canais] + gancho preferência) SÍNCRONO no disparo; cria `communications` (na_fila dos habilitados; desativado+auditoria dos off); congela os canais na Notification (lido pelo `via()`) e chama `$recipient->notify()` (envio enfileirado). Espelha `VerifyEmailQueued::freezeUrlFor`. Notifications de negócio implementam `ProcessNotification` (viabilityRequestId, communicationType, channels).
- Listener auto-descoberto `RegistrarEnvioComunicacao` em `NotificationSent`/`NotificationFailed` marca enviado/falhou em `communications` (espelha o atual `LogNotificationSent`).

### HU-090/091/092 — SEM refazer o estado (refactor anti-duplicação)
- **HU-090:** listener AUTO-DESCOBERTO `NotificarPendencia` (type-hint `PendenciaSolicitada`; NÃO Event::listen — lição Fases 8/9, travar por contagem) → `NotificationDispatcher` (multicanal).
- **CRÍTICO (anti-duplicação):** o `PendenciaService::abrir` hoje dispara o evento E chama `notificarRequerente()` (e-mail direto, linha 97). REMOVER `notificarRequerente()` (e o método privado) — o listener passa a notificar. Os testes da Fase 10 que asseriam o e-mail na abertura MIGRAM para asserir via `Notification::fake`/`communications` (cobertura mantida, NÃO removida).
- **HU-091/092 (já entregues):** ADICIONAR evento `App\Events\PendenciaRespondida` (after-commit no fim de `responder()`) + listener `NotificarRespostaPendencia` → notifica o analista responsável (assigned_user_id) — a Fase 10 não fechava esse retorno. RN-005 (prazo expirado → tratamento) coberto na rotina de expiração.
- **Reuso transversal:** refatorar `NotificarResultadoExpresso` (HU-077, Fase 9 — só e-mail) para passar pelo `NotificationDispatcher` (ganha in-app + histórico), unificando as notificações de processo. ANTI-REGRESSÃO: os testes da Fase 9 (ResultadoEmitido/notificação) seguem verdes (migrados para asserir via dispatcher/Notification::fake).

### HU-093 (vencimentos) + HU-147 (escalonamento) — fonte única, scheduler idempotente
- Fonte ÚNICA de prazo: `analysis_due_at` (lido por `AnalysisSlaService`, reusa `BusinessDeadlineCalculator`/seam HU-137); pendências usam `analysis_pendencies.due_at`. As rotinas SÓ notificam (não criam transição/timeline → sem dupla contagem com HU-129/Fase 15).
- Comandos (padrão `ExpressoIndeferirSemBapCommand` + `withoutOverlapping()->onOneServer()`):
  - `notificacoes:alertar-vencimentos` (HU-093, dailyAt 07:00): processos em_analise com analysis_due_at próximo (`notificacoes.vencimento.antecedencia_dias`) e pendências aberta com due_at próximo → notifica analista/requerente.
  - `notificacoes:escalonar-sla` (HU-147, hourly): em_analise com analysis_due_at vencido (isOverdue) → escala ao(s) gestor(es) (role `gestor` parametrizável, pois não há "gestor do setor" no schema — pendência SEDUR); limiar amarelo alerta o analista. Tratamento parametrizável (`notificacoes.escalonamento.tratamento`, default só notificar).
  - `pendencias:expirar` (HU-091 RN-005, dailyAt 06:00): due_at vencido sem resposta → `AnalysisPendencyStatus::Expirada` (enum já existe) + notifica analista. SEM decisão automática (indeferir por não-resposta é rito → SEDUR).
- **Idempotência (RN-004) sem schema novo:** cada rotina checa `communications` (existe type=X para o processo/etapa após analysis_stage_started_at?) antes de notificar — o índice (viability_request_id, type, channel) serve a isso.

### Parametrização (HU-014)
Parâmetros: `features.notificacao_email` (1), `features.notificacao_in_app` (1), `features.notificacao_whatsapp` (0), `notificacoes.mapa_canais` (json), `notificacoes.vencimento.antecedencia_dias` (3), `notificacoes.escalonamento.tratamento` (json), `notificacoes.pendencia.assunto`/corpo (templates), `integrations.whatsapp.base_url`/`.token` (sensitive, requires_connection_test — Fase 13). Constantes técnicas (cadência, tries/timeout WhatsApp) em config/sile.php.

## Plano de testes (TDD)
- `Notification::fake()` — canais resolvidos/destinatários por tipo/toggle. Canal database — assertDatabaseHas('notifications') + unreadNotifications. WhatsApp off → desativado+auditoria; on+Unavailable → bloqueado+auditoria (nunca "enviado", espelha ComunicarResultadoReginListenerTest); on+spy → envia. Listener único por evento (contagem) p/ PendenciaSolicitada/PendenciaRespondida/ResultadoEmitido. after-commit (rollback não dispara). Scheduler idempotente (2× não duplica communications; prazo coincide com AnalysisSlaService — CA-02 HU-147; no-op honesto). HU-096 consulta unificada + escopo de permissão. ANTI-REGRESSÃO Fase 9/10 (notificações migradas verdes).

## Waves (para o gsd-planner)

| Wave | Conteúdo |
|---|---|
| **1 — Fundação** | make:notifications-table; migration+model+factory `Communication`; contrato `ProcessNotification`; parâmetros HU-014 (dono único do catálogo) + fallback + seeder-tests; permissões se necessárias |
| **2 — Canais e roteador** | WhatsAppGateway/WhatsAppMessage/UnavailableWhatsAppGateway/WhatsAppUnavailableException + binding; WhatsAppChannel; NotificationDispatcher; listener RegistrarEnvioComunicacao (NotificationSent/Failed); User::routeNotificationForWhatsapp |
| **3 — HU-090/091/092** | listener NotificarPendencia + REFACTOR PendenciaService::abrir (remove e-mail direto); evento PendenciaRespondida + NotificarRespostaPendencia; PendenciaSolicitadaNotification ganha database/whatsapp; refatora NotificarResultadoExpresso p/ o pipeline (anti-regressão Fase 9) |
| **4 — Central in-app (UI)** | NotificationCenterController (index/markAsRead/markAllAsRead) portal+gestão; shared prop no HandleInertiaRequests; sininho + página + central de pendências; histórico HU-096 (backend + tela) |
| **5 — Scheduler** | notificacoes:alertar-vencimentos (HU-093), notificacoes:escalonar-sla (HU-147), pendencias:expirar; agenda idempotente; ProcessoEscalonadoNotification/PrazoVencendoNotification |
| **6 — Fechamento** | seeds dev (notificações/comunicações fictícias, lógica real), smoke navegável (abrir pendência → in-app+e-mail reais → responder → reabre → analista notificado), verificação integral + guardião |

Paralelizável: Wave 2 ‖ início da 4 (central só depende do canal database). Dono único de routes/*.php e do ParameterSeeder por wave.

## Bloqueios e pendências SEDUR (registrar, não travar)
- **WhatsApp (HU-095):** provedor + credenciais + templates → toggle off + contrato Unavailable agora; adaptador real quando houver credencial (API comercial, pode destravar isolado / Fase 13).
- **Destinatário do escalonamento (HU-147):** "gestor do setor" não existe no schema (roteamento ao setor pendente SEDUR) → default role `gestor` parametrizável.
- **Convite/resposta Simplifica/Regin (HU-091 RN-004):** → Fase 13 (portal SILE já funciona).
- **Prazos/antecedência + SLA por etapa:** defaults parametrizáveis; valores oficiais → SEDUR.
- **Rito de não-resposta (indeferir por prazo?):** sem HU/rito → NÃO inventar; hoje expira+notifica+mantém estado. Confirmar SEDUR.
- **Templates oficiais** (texto/identidade/base legal) → SEDUR (pt-BR default agora).
