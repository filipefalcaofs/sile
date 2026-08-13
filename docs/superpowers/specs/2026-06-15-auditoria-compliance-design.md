# Spec de Design — Fase 12: Auditoria e Compliance (EP12)

**Data:** 2026-06-15
**Status:** Aprovado (brainstorming via agents analista-negocio + arquiteto-tecnico)
**Fontes:** análise de negócio + arquitetura (sessão 2026-06-15), ROADMAP Phase 12, HUs EP12 (HU-097..102, HU-149).

## Problema

A trilha de auditoria RN-002 (registrada desde a Fase 1 por `HasAuditoria`/`AuditService`/`activity_log`/`access_logs` e alimentada por TODAS as fases) precisa ser **consultável, exportável e monitorada para LGPD**, com explicabilidade passo a passo das decisões automáticas e detecção de abuso/fraude. É majoritariamente SUPERFÍCIE DE LEITURA sobre dado já real — alto valor, baixo risco de fachada. HU-097 (log das decisões) JÁ está entregue (Fases 1/9/10).

## Decisão de escopo (os dois agents convergiram)

**Entregável agora (sem fachada):** HU-098 (histórico de alterações), HU-099 (regras aplicadas / explicabilidade), HU-100 (trilha unificada), HU-101 (exportar CSV), HU-102 (LGPD), HU-149 (detecção de abuso). HU-097 já existe (verificar cobertura).

**Princípio raiz:** `activity_log` JÁ É a trilha unificada (centraliza HasAuditoria + AuditService + 403 auditado). NÃO criar tabela/índice denormalizado nem materialized view — consultar direto (server-driven), com índices aditivos. `access_logs` é a ÚNICA fonte fora dela (prunável); entra como fonte secundária na mesma UI (merge na aplicação, não UNION SQL).

**Bloqueado/pendente honesto:** export pleno XLSX/PDF → HU-131/Fase 15 (CSV agora); verificação IA polígono×fachada → HU-115/EP14; drill-down de efetividade pleno → HU-145/Fase 15. Pendências SEDUR/DPO: padrões/limiares oficiais de fraude (HU-149 nasce DESLIGADA), política de retenção/eliminação LGPD, papel auditor dedicado.

## Arquitetura

### Trilha unificada (HU-100) + histórico de alterações (HU-098)
- `AuditTrailQueryService` (espelha ProcessoQueryService): `filtered(filtros)` sobre `activity_log` — filtros período/usuário(causer)/entidade(subject_type+id)/ação(log_name+event)/resultado/fonte; whereLike caseSensitive:false; orderByDesc; eager-load causer/actingFor/subject. `apenasAlteracoes` = whereNotNull(attribute_changes) (HU-098). `acessos` = variante global do AccessHistoryController sobre access_logs.
- `Gestao\AuditoriaController` (index roteia por fonte atividade/alteracoes/acessos; pagina server-side; AUDITA a própria consulta — meta-auditoria CA-02; Inertia render). `ActivityResource` (created_at/log_name/event/description/causer/acting_for/subject rótulo/result/rules_version/ip/channel + attribute_changes p/ HU-098).
- Migration aditiva: índices em activity_log (created_at; composto (log_name,created_at); event).

### HU-099 — Explicabilidade passo a passo (RN-004/RN-005)
- **DESCOBERTA:** `ViabilityDecision.per_cnae` hoje grava forma COMPACTA (perCnae do FluxoExpressoService); o trace completo (Quadro 7/10/11/11A + risco, cada um com versao_regra/motivo) está no `consulta_array` (ConsultaViabilidadeResult::toArray) mas é DESCARTADO. RN-004 exige passo a passo; RN-005 proíbe recomputar.
- **Solução (forward, aditiva):** migration aditiva `decision_trace` JSON nullable (imutável) em viability_decisions. Nos DOIS pontos de escrita (Fase 9 `FluxoExpressoService::emitir` e Fase 10 `AnaliseTecnicaDecisionService`) gravar `decision_trace` a partir do consulta_array/ficha JÁ em memória (NÃO altera a lógica de decisão — só amplia o snapshot; ANTI-REGRESSÃO das suítes 9/10). Shape por CNAE ordenado: entrada(área/ponto/CNAE) → risco(municipal/sanitário/encaminhamento+versão) → louos(quadro7→quadro10→quadro11→quadro11a, cada {entrada, resultado_parcial, motivo, versao_regra}) → consolidação(veredito) → desfecho.
- `DecisionExplanationService::explain(ViabilityDecision): array` — PROJEÇÃO PURA do decision_trace + rules_versions + fundamentacao gravados; NUNCA chama motor. Decisão legada (trace null) → monta explicação compacta de per_cnae/fundamentacao/rules_versions e MARCA explicitamente os passos não snapshotados como "não registrado nesta decisão" (anti-fachada — não inventa nem recomputa). Exposto em ResultadoExpressoController::show + ProcessoController::show (gate consultar-solicitacoes) e na trilha (gate consultar-auditoria).

### HU-101 — Exportar (CSV)
- `AuditoriaController::export` reusa o padrão da Fase 10 (`streamDownload` + `fputcsv` + `->chunk(200)`), mesmos filtros do index, AUDITA a exportação. Guard de volume técnico (`auditoria.export.max_linhas` em config). Export pleno XLSX/PDF → HU-131/Fase 15.

### HU-102 — LGPD
- Painel agrega 3 fontes reais: (1) consentimentos (LegalTerm::current('lgpd') × LegalTermAcceptance — % aceite da versão vigente, pendentes de re-aceite, série); (2) retenção (`retencao.access_logs.dias` + último pruning lido de activity_log log_name='retencao'; trilha de decisões FICA FORA do pruning — compliance, Fase 3.1); (3) acessos a dado pessoal — migration aditiva `personal_data` boolean nullable INDEXADA em activity_log + `AuditService::log(..., bool $personalData=false)` (aditivo) marcado nos call sites REAIS de leitura sensível (detalhe de processo, histórico de acessos de terceiros, gestão de usuários). `LgpdMonitorService` (consentimentos/acessosDadoPessoal/retencao) + `Gestao\LgpdMonitorController` (gate monitorar-lgpd, auditado, minimizado).
- Pendência DPO: direitos do titular (eliminação/anonimização) — confirmação/acesso já existe; eliminação conflita com retenção legal (não inventar rito). "Acesso a dado pessoal" como evento dedicado além do já logado — confirmar.

### HU-149 — Detecção de abuso (ALERTA + malha fina, NUNCA punição)
- Motor de DETECTORES DETERMINÍSTICOS parametrizáveis (Strategy, não rule-engine genérico) no scheduler (idempotente) sobre dados reais → cria `abuse_alerts` + (acima do limiar) encaminha à malha fina (`MalhaFinaService` Fase 10). NUNCA indefere/cassa (RN-001). DESLIGADO por default (features.deteccao_abuso=0) até a SEDUR validar.
- Tabela `abuse_alerts` (model AbuseAlert + HasAuditoria + factory): rule_key, severity (baixa/media/alta), status (aberto/confirmado/descartado), fingerprint (idempotência — índice único parcial (rule_key,fingerprint) p/ abertos), evidence json, viability_request_id nullable, subject morphs nullable, window_start/end, detected_at, resolved_by/at, justification, fine_mesh_referral_id nullable.
- `interface AbuseDetector { key(); detect(Janela): iterable<AbuseFinding>; }` + detectores: VolumeCnpjDetector, VolumeContadorDetector, InscricaoAtividadesIncompativeisDetector, CondicionanteEvasaoDetector, PoligonoRepetidoDetector (EscritorioVirtualEncadeado fica 2ª onda — HU-139 é texto livre). `AbuseDetectionService` itera detectores habilitados via Settings, upsert idempotente, severity>=abuso.severidade_malha_fina → MalhaFinaService::encaminhar(ator=sistema, "suspeita de abuso: {rule_key}") + grava fine_mesh_referral_id. `abuso:detectar` no scheduler (daily/withoutOverlapping/onOneServer; no-op honesto se toggle off).
- Anti-falso-positivo punitivo: a malha fina é ORTOGONAL ao status (não transiciona/decide); o alerta é insumo de revisão humana; nada decidido sem ação humana (CA-02). Efetividade confirmados÷gerados (RN-005). `AbusoController` (index+filtros+efetividade, confirmar/descartar com justification obrigatória, auditados; gate gerenciar-alertas-abuso). Simulação antes de ativar (HU-143 padrão).

### Parametrização (HU-014) + permissões
- Parâmetros: ui.auditoria.per_page (20), features.deteccao_abuso (0), abuso.janela_dias (30), abuso.volume_cnpj.limite (5), abuso.volume_contador.limite (20), abuso.escritorio_virtual.limite (3), abuso.severidade_malha_fina (alta). Reusa retencao.access_logs.dias. Constantes técnicas (export.max_linhas/TTL) em config.
- Permissões aditivas: consultar-auditoria (HU-097..101 → gestor/admin), monitorar-lgpd (HU-102 → admin), gerenciar-alertas-abuso (HU-149 → gestor/admin). Rotas gestao.auditoria.*/lgpd.*/abuso.*.

## Plano de testes (TDD)
- Trilha: filtros/paginação; fonte=alteracoes só attribute_changes; fonte=acessos sobre access_logs; 403 auditado.
- Explicabilidade: projeção dos passos na ordem a partir do decision_trace; SPY no motor = ZERO chamadas (RN-005); legado sem trace degrada honesto.
- Export: CSV respeita filtros + auditada.
- LGPD: cobertura consentimento versão vigente; acessos marcados personal_data; retenção + último pruning.
- Abuso: alerta+malha fina acima do limiar; NUNCA altera status (CA-02 anti-fachada); idempotência; no-op toggle off; efetividade; confirmar/descartar auditados.
- ANTI-REGRESSÃO Fases 9/10 (decision_trace aditivo) + AuditService (personal_data aditivo).

## Waves (para o gsd-planner)

| Wave | Conteúdo |
|---|---|
| **1 — Fundação** | migrations aditivas (índices activity_log + personal_data; viability_decisions.decision_trace; abuse_alerts) + model AbuseAlert/factory + parâmetros HU-014 + permissões; ENRIQUECIMENTO decision_trace nos pontos de escrita Fases 9/10 (ANTI-REGRESSÃO 9/10) + AuditService::log personalData (aditivo) |
| **2 — Consulta (paralelo)** | AuditTrailQueryService+AuditoriaController+CSV (HU-098/100/101) ‖ DecisionExplanationService+resource (HU-099) ‖ LgpdMonitorService+controller (HU-102) |
| **3 — Abuso** | detectores + AbuseDetectionService + abuso:detectar (scheduler) + AbusoController |
| **4 — UI** | páginas React (auditoria, lgpd, abuso) + navegação + Cmd+K |
| **5 — Fechamento** | seeds dev (alertas/acessos via fluxo real) + verificação integral fresca (composer test) + smoke navegável + guardião |

## Escalonamentos SEDUR/DPO (não decidir sozinho)
- Padrões/limiares oficiais de fraude (HU-149) — detectores nascem DESLIGADOS; SEDUR valida e liga.
- Política de retenção/eliminação LGPD do activity_log (trilha de decisões fora do pruning) — DPO confirma período legal.
- Se "acesso a dado pessoal" exige trilha dedicada além do activity_log — DPO.
- Papel auditor/DPO dedicado (default gestor+admin; criar role auditor se pedir segregação).
- Export pleno XLSX/PDF → HU-131/Fase 15.
