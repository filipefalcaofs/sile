# Phase 9: Fluxo Expresso - Context

**Gathered:** 2026-06-14
**Status:** Ready for planning
**Source:** Agents analista-negocio + arquiteto-tecnico (política agentes-sile.mdc) + spec `docs/superpowers/specs/2026-06-14-fluxo-expresso-design.md`

<domain>
## Phase Boundary

O CORE VALUE do produto: avaliar todo protocolo automaticamente e DEFERIR/INDEFERIR quando a lei permite, com decisão fundamentada e auditável, sem análise humana. Consome o protocolo da Fase 8 (evento SolicitacaoProtocolada) e os motores reais (LOUOS Fase 5 / risco Fase 6 via ConsultaViabilidadeService Fase 7). Caminho real: protocolada → elegibilidade (HU-073) → deferir/indeferir automático (HU-074/075) → emitir resultado interno + número TVL (HU-076 parte real) → auditar (HU-078) → notificar e-mail sem anexo (HU-077).

Fora do escopo / bloqueado: análise técnica humana (Fase 10, recebe os em_analise); TVL PDF (HU-132, Fase 10); canais plenos de notificação (EP11). BLOQUEADO → Fase 13 (degrada honesto): transmissão Regin/Junta (HU-104) e SEFAZ (HU-110) atrás de contrato indisponível; HU-134 (prazo BAP) dormente (nada entra em aguardando_bap até o Regin). Degradação CRÍTICA herdada: sem a zona (Quadro 10), o veredito é pendente → a solicitação vai para em_analise (NÃO é deferida/indeferida) — o motor é real e liga sozinho quando a zona oficial entrar.
</domain>

<decisions>
## Implementation Decisions

### Gatilho e orquestração (arquiteto)
- Listener AUTO-DESCOBERTO `AvaliarFluxoExpresso` no evento SolicitacaoProtocolada (Fase 8) → despacha DecidirFluxoExpressoJob (ShouldQueue, tries/timeout/backoff) — NÃO decide inline. Scheduler `expresso:reavaliar` = rede de segurança/reprocesso (não gatilho principal). Comando `expresso:decidir {solicitacao}` para evidência/reprocesso.
- NÃO registrar listeners via Event::listen (lição Fase 8 — duplica auditoria). Auto-descoberta é o registro único; travar com teste por CONTAGEM.

### Decisão autoritativa, não snapshot (analista + arquiteto)
- A decisão vinculante REEXECUTA os motores frescos (entrada/regras/versões da época) — NÃO confia no simulation_snapshot pré-protocolo (orientativo, pode estar stale). HU-078 RN-005 exige a versão da regra da decisão.
- Extrair `SolicitacaoViabilityResolver` de `SimulacaoSolicitacaoService::simulate` (app/Services/Solicitacao): itera CNAEs via ConsultaViabilidadeService::consultarPorPontoConhecido, propaga veredito, consolida pior caso, devolve por_cnae[]+consolidado+rules_versions SEM persistir. Consumido por SimulacaoSolicitacaoService (snapshot orientativo — manter testes Fase 8 verdes) e FluxoExpressoService (decisão). Cada por_cnae carrega o ConsultaViabilidadeResult (lê risco.encaminhamento.fluxo + vereditoLocacional().resultado numa passada).

### Regras de decisão
- Elegibilidade HU-073: elegível só se TODOS os CNAEs têm Fluxo::Expresso (enum app/Enums/Fluxo.php; risco já entrega via risco.mapa_encaminhamento). Algum em analise (alto/gatilho/não-classificado) → inelegível → em_analise (semi-expresso, motivo auditado). Veredito consolidado pendente (sem zona) → em_analise SEM ResultadoEmitido.
- Consolidação RN-009 (SAPS): todos permitido/permitido_com_condicoes → defere; algum nao_permitido → indefere o processo inteiro.

### Máquina de estados (ADICIONAR, sem tocar protocolo)
- protocolada → {cancelada, em_analise, deferida, indeferida, aguardando_bap}; aguardando_bap → {deferida, indeferida, em_analise}. em_pendencia fica gancho (EP11). StateMachine não abre transação (FluxoExpressoService controla); cada transição grava timeline + auditoria RN-002.

### Idempotência + persistência HU-076
- Cache::lock("expresso:decisao:{id}") + re-check status===protocolada dentro do lock. Tabela IMUTÁVEL viability_decisions (1:1): flow, outcome (deferida/indeferida), consolidated_result, tvl_product_number (unique, só defere), per_cnae jsonb, rules_versions jsonb, fundamentacao jsonb, reason, decided_by_user_id (null=sistema), decided_at (append-only). Fonte do PDF/TVL (Fase 10/HU-132) e explicabilidade (Fase 12/HU-099). TvlNumberGenerator (TVL-AAAA-NNNNNN, lockForUpdate+unique; concorrência @group postgis).

### Segundo evento de domínio ResultadoEmitido
- app/Events/ResultadoEmitido (ShouldDispatchAfterCommit, carrega ViabilityRequest + ViabilityDecision), após commit. 3 listeners AUTO-DESCOBERTOS (ShouldQueue): NotificarResultadoExpresso (HU-077), ComunicarResultadoRegin (HU-104 bloqueado), EnviarViabilidadeSefaz (HU-110 bloqueado, SÓ deferida — HU-134 RN-003).
- Auditoria autoritativa HU-078 é SÍNCRONA na transação — NÃO depende do evento (lição Fase 8). Evento = só efeitos colaterais desacoplados.

### Contratos bloqueados → Fase 13 (padrão PropertyRegistryLookup)
- ReginParecerNotifier + UnavailableReginParecerNotifier (lança ReginUnavailableException); SefazViabilidadeGateway + UnavailableSefazViabilidadeGateway; BapRegistry + UnavailableBapRegistry (findLinkage → null). Bindings no AppServiceProvider::register(). Listeners capturam a exceção e AUDITAM pendência (integracoes, result:'bloqueado') — nunca sucesso falso.
- HU-134 DORMENTE: comando expresso:indeferir-sem-bap (parametrizável expresso.bap.prazo_horas=48, withoutOverlapping/onOneServer) varre aguardando_bap vencidas e indefere (reason 'indeferido sem atuação', ResultadoEmitido → Regin, SEM SEFAZ). No-op em produção (varre zero) até o Regin alimentar bap_due_at. Seam BusinessDeadlineCalculator p/ dias úteis (HU-137 feriados pendente — horas-calendário até lá).

### Notificação HU-077
- App\Notifications\ResultadoExpressoNotification (mail, ShouldQueue) reusa EmailLog/padrão VerifyEmailQueued. SEM anexo de TVL. Assunto/texto parametrizáveis. Toggle features.notificacao_resultado_expresso (off → não envia, audita). Canais plenos → EP11.

### TVL
- Número de produto TVL no deferimento (HU-076 RN-007) é registro INTERNO → entra agora (gerador interno). PDF/TVL (HU-132) é Fase 10. Defere → gera número; não gera PDF, não entrega ao cidadão.

### Parâmetros / Claude's Discretion
- Parâmetros: features.fluxo_expresso, features.notificacao_resultado_expresso, expresso.bap.prazo_horas (48), expresso.notificacao.assunto_deferida/_indeferida, expresso.tvl.prefixo/padding. Reusa risco.mapa_encaminhamento (não criar novo). Constantes técnicas (lock timeout, cadência scheduler, fila, tries/backoff) em config/sile.php.
- Discrição: nomes exatos de migrations/colunas/classes/jobs; shape dos DTOs (ResolvedViability, DecisionOutcome); rotas/estrutura da UI de retaguarda; formato do seed dev de zona fictícia (SÓ dev/teste).
</decisions>

<canonical_refs>
## Canonical References

### Spec + agents
- `docs/superpowers/specs/2026-06-14-fluxo-expresso-design.md`.
- Agents (2026-06-14): analista-negocio (escopo/sequenciamento, decisão autoritativa, degradação por zona, bloqueios) e arquiteto-tecnico (FluxoExpressoService, ResultadoEmitido, viability_decisions, contratos, scheduler).

### HUs
- `docs/SILE_HUs_Completas_MD/EP09-Fluxo-Expresso/HU-073..HU-078.md`, `HU-134`.

### Reuso (NÃO recriar — consumir/estender)
- Evento+listener: `app/Events/SolicitacaoProtocolada.php` + `app/Listeners/RegistrarTrilhaProtocolo.php` (padrão auto-descoberta; ShouldDispatchAfterCommit).
- Estado: `app/Enums/ViabilityRequestStatus.php` (ganchos) + `app/Services/Solicitacao/ViabilityRequestStateMachine.php` (adicionar transições).
- Motores: `app/Services/Viabilidade/ConsultaViabilidadeService.php` (consultarPorPontoConhecido; vereditoLocacional) + `ConsultaViabilidadeResult.php` + `app/Enums/Fluxo.php` (Expresso/Analise) + RiscoClassificationService/LouosEnquadramentoService.
- A EXTRAIR: `app/Services/Solicitacao/SimulacaoSolicitacaoService.php::simulate` (iteração CNAE + consolidar → SolicitacaoViabilityResolver).
- Número concorrente-seguro: `app/Services/Solicitacao/ProtocolNumberGenerator.php` (padrão p/ TvlNumberGenerator).
- Contrato bloqueado: `app/Services/Realty/PropertyRegistryLookup.php` + `UnavailablePropertyRegistryLookup.php`.
- Scheduler/jobs: `routes/console.php` (withoutOverlapping/onOneServer) + jobs da Fase 3.1 (ImportRedesimJob padrão de retry/failed auditado).
- E-mail: EmailLog + notificações ShouldQueue (VerifyEmailQueued/ResetPasswordQueued) + LogNotificationSent. Auditoria: AuditService. Parametrização: Settings::get + config/sile.php.

### Testes
- phpunit.xml SQLite; baseline atual 848 (+ @group postgis). Concorrência (TvlNumberGenerator/idempotência da emissão) usa @group postgis (PostgisTestCase + geo:preparar-banco-de-testes). Motores em SQLite com fake SpatialRepository. Golden cases #[DataProvider] (padrão Fases 5/6/7/8). Estrutura: tests/Feature/Expresso/.
</canonical_refs>

<specifics>
## Specific Ideas
- O motor de decisão é real e testado AGORA; sem a zona (Quadro 10) ele roteia a maioria para análise — liga sozinho quando a base oficial entrar (muda a carga, não a lógica). Registrar essa expectativa para a gestão não ler como falha.
- ResultadoEmitido é o SEGUNDO evento de domínio — base desacoplada para EP10/11/13 (só listeners depois). Auditoria da decisão é síncrona (não depende do evento).
- A decisão vinculante reexecuta os motores (autoritativa), nunca decide sobre o snapshot orientativo da Fase 8.
- TVL = número interno agora; PDF é Fase 10.
</specifics>

<deferred>
## Deferred Ideas
- Transmissão Regin/Junta (HU-104) e SEFAZ (HU-110) — Fase 13 (contratos prontos, binding indisponível; listeners auditam pendência).
- HU-134 ativa (indeferir por prazo BAP real) — Fase 13 (rotina dormente + estado + parâmetro prontos; depende da vinculação BAP/Regin).
- TVL PDF no backoffice (HU-132) — Fase 10 (viability_decisions é a fonte).
- Canais plenos de notificação (WhatsApp, in-app) — EP11.
- Contagem do prazo BAP em dias úteis (HU-137 feriados) — seam BusinessDeadlineCalculator pronto; horas-calendário até a HU-137.
- Captura da resposta de condicionante-pergunta no fluxo da solicitação — retrofit Fase 8 ou tratamento na análise (Fase 10); hoje condicionante não respondida → análise.
</deferred>

---

*Phase: 09-fluxo-expresso*
*Context gathered: 2026-06-14 via agents analista-negocio + arquiteto-tecnico*
