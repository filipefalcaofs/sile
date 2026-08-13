# Phase 11: Pendências e Comunicação - Context

**Gathered:** 2026-06-15
**Status:** Ready for planning
**Source:** Agents analista-negocio + arquiteto-tecnico (política agentes-sile.mdc) + spec `docs/superpowers/specs/2026-06-15-pendencias-comunicacao-design.md`

<domain>
## Phase Boundary

Generaliza e ATIVA o que a Fase 10 deixou pronto/dormente: o ciclo notificar→responder→reabrir (em_analise↔em_pendencia) já existe — a Fase 11 ADICIONA multicanal (in-app via canal database + WhatsApp), central de pendências/notificações (UI), templates, histórico unificado (HU-096), e ATIVA a expiração de prazo de pendência (HU-093) e o escalonamento por SLA analista→gestor (HU-147, reusa analysis_due_at/AnalysisSlaService + scheduler da Fase 3.1). E-mail (HU-094) e in-app são REAIS; WhatsApp (HU-095) entra atrás de toggle off + contrato indisponível (degrada honesto). NÃO reconstrói estado.

Fora do escopo / bloqueado: convite/resposta via Simplifica/Regin (HU-091 RN-004) → Fase 13 (resposta pelo portal SILE já funciona desde a Fase 10); provedor real de WhatsApp → quando houver credencial (API comercial, pode destravar isolado/Fase 13); destinatário "gestor do setor" → default role gestor (roteamento ao setor é pendência SEDUR); rito de não-resposta (indeferir por prazo) → NÃO inventar (SEDUR).
</domain>

<decisions>
## Implementation Decisions

### Três mecanismos, sem dupla verdade (arquiteto)
- In-app = canal `database` NATIVO (tabela notifications padrão; read_at/markAsRead). User já é Notifiable (confirmado). FALTA a migration → `php artisan make:notifications-table` (Wave 1).
- Histórico HU-096 = tabela DEDICADA `communications` (NÃO view): EmailLog não tem viability_request_id (genérico de conta) e notifications é morph por usuário — unir por processo seria frágil. Ledger imutável (padrão access_logs/transitions). Schema: viability_request_id (FK nullable, índice), recipient_user_id, channel (email/in_app/whatsapp), type (pendencia_aberta/pendencia_respondida/prazo_vencendo/escalonamento_sla/resultado), status (na_fila/enviado/falhou/bloqueado/desativado), title/summary, error_message, meta jsonb, queued_at/sent_at/failed_at; índices (viability_request_id,type,channel) + (viability_request_id,created_at).
- EmailLog PERMANECE para e-mails de CONTA (verificação/senha); e-mails de PROCESSO → communications é a fonte de verdade.

### Canais + roteador
- E-mail reusa Notification ShouldQueue+toMail. In-app → toDatabase(). WhatsApp → WhatsAppChannel + contrato WhatsAppGateway + DTO WhatsAppMessage + UnavailableWhatsAppGateway (lança WhatsAppUnavailableException); binding no AppServiceProvider::register (ao lado Regin/SEFAZ/Bap). User::routeNotificationForWhatsapp = phone E.164. Toggle features.notificacao_whatsapp OFF default. Credenciais criptografadas + teste de conexão.
- NotificationDispatcher::deliver(User, ProcessNotification): resolve canais SÍNCRONO no disparo (toggles + notificacoes.mapa_canais json type→[canais] + gancho preferência), cria communications (na_fila habilitados; desativado+auditoria off), congela canais na Notification (lido pelo via()), chama notify() (envio enfileirado). Espelha VerifyEmailQueued::freezeUrlFor. ProcessNotification contract: viabilityRequestId/communicationType/channels.
- Listener auto-descoberto RegistrarEnvioComunicacao em NotificationSent/NotificationFailed marca enviado/falhou (espelha LogNotificationSent).

### HU-090/091/092 — refactor anti-duplicação (NÃO refazer estado)
- HU-090: listener AUTO-DESCOBERTO NotificarPendencia em PendenciaSolicitada (NÃO Event::listen; contagem) → dispatcher multicanal.
- CRÍTICO: PendenciaService::abrir hoje dispara o evento E chama notificarRequerente() (e-mail direto, linha 97). REMOVER notificarRequerente (e o método) — o listener notifica. Testes da Fase 10 que asseriam o e-mail MIGRAM para Notification::fake/communications (cobertura mantida).
- HU-091/092 já entregues; ADICIONAR evento PendenciaRespondida (after-commit no responder()) + listener NotificarRespostaPendencia → notifica analista (assigned_user_id). RN-005 (expirado→tratamento) na rotina de expiração.
- Refatorar NotificarResultadoExpresso (HU-077 Fase 9, só e-mail) p/ passar pelo dispatcher (ganha in-app+histórico). ANTI-REGRESSÃO: testes Fase 9 verdes (migrados p/ asserir via dispatcher).

### HU-093 + HU-147 — fonte única, scheduler idempotente
- Fonte ÚNICA = analysis_due_at (AnalysisSlaService/BusinessDeadlineCalculator); pendências = analysis_pendencies.due_at. Rotinas SÓ notificam (sem transição/timeline → sem dupla contagem HU-129). Comandos withoutOverlapping/onOneServer: notificacoes:alertar-vencimentos (HU-093 dailyAt 07:00), notificacoes:escalonar-sla (HU-147 hourly; gestor role parametrizável; limiar amarelo→analista, vencido→gestor; tratamento notificacoes.escalonamento.tratamento), pendencias:expirar (HU-091 RN-005 dailyAt 06:00 → Expirada + notifica, SEM decisão automática). Idempotência via checagem em communications (sem schema novo).

### Parâmetros (HU-014)
- features.notificacao_email(1)/_in_app(1)/_whatsapp(0); notificacoes.mapa_canais(json); notificacoes.vencimento.antecedencia_dias(3); notificacoes.escalonamento.tratamento(json); notificacoes.pendencia.assunto+corpo (templates); integrations.whatsapp.base_url/.token (sensitive, requires_connection_test). Constantes técnicas (cadência/tries) em config.

### Claude's Discretion
- Nomes exatos de migrations/colunas/classes/eventos; assinatura do NotificationDispatcher/WhatsAppChannel/ProcessNotification; shape do communications.meta; rotas/UI da central; ledger único vs. detalhe (arquiteto decidiu ledger communications).
</decisions>

<canonical_refs>
## Canonical References

### Spec + agents
- `docs/superpowers/specs/2026-06-15-pendencias-comunicacao-design.md`.
- Agents (2026-06-15): analista-negocio (ciclo já pronto da Fase 10, multicanal, WhatsApp toggle/contrato, HU-093/147 ativam dormente) e arquiteto-tecnico (communications ledger, canal database, WhatsAppGateway, NotificationDispatcher, refactor anti-dup, scheduler idempotente).

### HUs
- `docs/SILE_HUs_Completas_MD/EP11-Pendências-e-Comunicacao/HU-090..HU-096.md`, `HU-147`.

### Reuso (NÃO recriar — estender)
- Ciclo de pendência: `app/Services/Analise/PendenciaService.php` (abrir/responder — REMOVER notificarRequerente; ADICIONAR dispatch PendenciaRespondida) + `app/Events/PendenciaSolicitada.php` (já existe; pendurar listener) + `app/Http/Controllers/.../PendenciaRespostaController` (portal).
- Notificações: `app/Notifications/*` (VerifyEmailQueued::freezeUrlFor padrão de congelar no disparo; ResultadoExpressoNotification; PendenciaSolicitadaNotification — ganha toDatabase/toWhatsApp/via dinâmico) + `app/Listeners/LogNotificationSent.php` (padrão p/ RegistrarEnvioComunicacao) + `app/Models/EmailLog.php` (conta).
- SLA: `app/Services/Analise/AnalysisSlaService.php` + `app/Services/Expresso/BusinessDeadlineCalculator.php` + coluna analysis_due_at (HU-147/093).
- Contrato indisponível: `app/Services/Realty/PropertyRegistryLookup.php`/Unavailable (padrão p/ WhatsAppGateway) + bindings no AppServiceProvider (Regin/SEFAZ/Bap).
- Scheduler: `routes/console.php` (withoutOverlapping/onOneServer) + `app/Console/Commands/ExpressoIndeferirSemBapCommand.php` (padrão de comando idempotente). User Notifiable (app/Models/User.php). Refatorar `app/Listeners/NotificarResultadoExpresso.php` (Fase 9).

### Testes
- phpunit.xml SQLite; baseline atual 1132 SQLite + 29 @group postgis (verificar via `composer test` — 2 processos). Notification::fake; canal database (assertDatabaseHas notifications); scheduler idempotente. Estrutura: tests/Feature/Comunicacao/ (ou Notificacoes).
</canonical_refs>

<specifics>
## Specific Ideas
- O ciclo de pendência não é reconstruído — é generalizado (multicanal/templates) e ativado (expiração/escalonamento dormentes).
- WhatsApp NUNCA finge envio: toggle off → desativado; on+indisponível → bloqueado (auditado); on+gateway real (Fase 13) → envia trocando só o binding.
- communications é o ledger único do histórico por processo E o controle de idempotência das rotinas (sem tabela de controle nova).
- Refactor anti-duplicação do PendenciaService + unificação do NotificarResultadoExpresso são os maiores riscos de regressão — testes das Fases 9/10 MIGRAM (não somem).
</specifics>

<deferred>
## Deferred Ideas
- Provedor real de WhatsApp (HU-095) — toggle off + contrato Unavailable agora; adaptador HTTP quando houver credencial (API comercial, isolado/Fase 13).
- Convite/resposta via Simplifica/Regin (HU-091 RN-004) — Fase 13.
- Destinatário "gestor do setor" do escalonamento (HU-147) — depende do roteamento ao setor (pendência SEDUR); default role gestor parametrizável.
- Rito de não-resposta de pendência (indeferir por prazo) — sem rito definido; NÃO inventar (SEDUR). Hoje expira+notifica+mantém estado.
- Preferência de canal por usuário — gancho no NotificationDispatcher; default por toggle/mapa agora.
- Templates oficiais (texto/identidade/base legal) — SEDUR (pt-BR default agora).
</deferred>

---

*Phase: 11-pendencias-e-comunicacao*
*Context gathered: 2026-06-15 via agents analista-negocio + arquiteto-tecnico*
