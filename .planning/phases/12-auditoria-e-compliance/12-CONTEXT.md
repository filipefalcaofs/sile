# Phase 12: Auditoria e Compliance - Context

**Gathered:** 2026-06-15
**Status:** Ready for planning
**Source:** Agents analista-negocio + arquiteto-tecnico (política agentes-sile.mdc) + spec `docs/superpowers/specs/2026-06-15-auditoria-compliance-design.md`

<domain>
## Phase Boundary

SUPERFÍCIE DE LEITURA sobre a trilha de auditoria RN-002 já registrada (Fase 1+, todas as fases): consulta unificada (HU-100), histórico de alterações (HU-098), regras aplicadas com explicabilidade passo a passo (HU-099), exportação CSV (HU-101), monitoramento LGPD (HU-102) e detecção de abuso/fraude (HU-149: alerta + malha fina, NUNCA punição automática). HU-097 (log das decisões) JÁ existe (Fases 1/9/10). Princípio raiz: activity_log É a trilha unificada — consultar direto (server-driven), sem materializar; access_logs é fonte secundária (merge na aplicação).

Fora do escopo / bloqueado: export pleno XLSX/PDF → HU-131/Fase 15 (CSV agora); verificação IA polígono×fachada → HU-115/EP14; drill-down de efetividade pleno → HU-145/Fase 15. Pendências SEDUR/DPO: padrões/limiares oficiais de fraude (HU-149 nasce DESLIGADA), política de retenção/eliminação LGPD, papel auditor dedicado.
</domain>

<decisions>
## Implementation Decisions

### Trilha unificada (HU-100) + alterações (HU-098)
- activity_log = espinha (centraliza HasAuditoria + AuditService + 403 auditado). NÃO criar tabela/índice denormalizado nem materialized view. AuditTrailQueryService (espelha ProcessoQueryService): filtered(filtros) — período/usuário(causer)/entidade(subject_type+id)/ação(log_name+event)/resultado/fonte; whereLike caseSensitive:false; orderByDesc; eager causer/actingFor/subject. apenasAlteracoes = whereNotNull(attribute_changes). acessos = variante global sobre access_logs. Gestao\AuditoriaController (index roteia fonte; pagina; AUDITA a própria consulta — meta-auditoria CA-02). ActivityResource. Migration aditiva de índices (created_at; (log_name,created_at); event).

### HU-099 explicabilidade (RN-004/005) — decision_trace forward
- ViabilityDecision.per_cnae hoje é COMPACTO; o trace completo (Quadro 7/10/11/11A + risco, cada um versao_regra/motivo) está no consulta_array mas é descartado. Migration aditiva decision_trace JSON nullable (imutável). Nos DOIS pontos de escrita (FluxoExpressoService::emitir Fase 9 — $resolved em escopo; AnaliseTecnicaDecisionService Fase 10) gravar decision_trace do consulta_array/ficha JÁ em memória (aditivo, NÃO muda lógica; ANTI-REGRESSÃO 9/10). Shape por CNAE: entrada→risco→louos(quadro7→10→11→11a {entrada,resultado_parcial,motivo,versao_regra})→consolidação→desfecho.
- DecisionExplanationService::explain(ViabilityDecision) — PROJEÇÃO PURA (decision_trace+rules_versions+fundamentacao); NUNCA chama motor (RN-005, spy=0 chamadas). Legado sem trace → explicação compacta + marca passos não snapshotados como "não registrado". Exposto em ResultadoExpressoController::show + ProcessoController::show (consultar-solicitacoes) + trilha (consultar-auditoria).

### HU-101 export CSV
- AuditoriaController::export reusa streamDownload+fputcsv+chunk(200) da Fase 10; mesmos filtros; AUDITA. auditoria.export.max_linhas em config. Pleno XLSX/PDF → HU-131/Fase 15.

### HU-102 LGPD
- Painel: consentimentos (LegalTerm::current('lgpd') × LegalTermAcceptance), retenção (retencao.access_logs.dias + último pruning de activity_log log_name='retencao'; decisões FORA do pruning — compliance), acessos a dado pessoal (migration aditiva personal_data boolean nullable INDEXADA em activity_log + AuditService::log(...,bool $personalData=false) aditivo, marcado nos call sites REAIS de leitura sensível). LgpdMonitorService + Gestao\LgpdMonitorController (monitorar-lgpd, auditado, minimizado). Pendência DPO: eliminação/anonimização (conflita com retenção legal — não inventar rito).

### HU-149 abuso (alerta + malha fina, NUNCA punição)
- Detectores DETERMINÍSTICOS parametrizáveis (Strategy) no scheduler idempotente → abuse_alerts + (acima do limiar) MalhaFinaService::encaminhar (Fase 10). NUNCA indefere/cassa (RN-001). DESLIGADO default (features.deteccao_abuso=0).
- abuse_alerts (AbuseAlert+HasAuditoria+factory): rule_key, severity (baixa/media/alta), status (aberto/confirmado/descartado), fingerprint (índice único parcial p/ abertos = idempotência), evidence json, viability_request_id nullable, subject morphs nullable, window_start/end, detected_at, resolved_by/at, justification, fine_mesh_referral_id nullable.
- interface AbuseDetector{key();detect(Janela):iterable} + VolumeCnpjDetector/VolumeContadorDetector/InscricaoAtividadesIncompativeisDetector/CondicionanteEvasaoDetector/PoligonoRepetidoDetector (EscritorioVirtualEncadeado 2ª onda). AbuseDetectionService (Settings habilita; upsert idempotente; severity>=abuso.severidade_malha_fina → malha fina + fine_mesh_referral_id; audita). abuso:detectar scheduler (daily/withoutOverlapping/onOneServer; no-op se off). AbusoController (index+efetividade confirmados÷gerados, confirmar/descartar justification obrigatória auditados; gerenciar-alertas-abuso). Anti-FP punitivo: malha fina ortogonal ao status, alerta = insumo humano.

### Parâmetros / permissões / Claude's Discretion
- Parâmetros: ui.auditoria.per_page(20), features.deteccao_abuso(0), abuso.janela_dias(30), abuso.volume_cnpj.limite(5), abuso.volume_contador.limite(20), abuso.escritorio_virtual.limite(3), abuso.severidade_malha_fina(alta). Reusa retencao.access_logs.dias. Constantes técnicas em config. Permissões aditivas: consultar-auditoria (gestor/admin), monitorar-lgpd (admin), gerenciar-alertas-abuso (gestor/admin).
- Discrição: nomes exatos de migrations/colunas/services/detectores; shape do decision_trace e do evidence; rotas/UI; quais call sites marcam personal_data.
</decisions>

<canonical_refs>
## Canonical References

### Spec + agents
- `docs/superpowers/specs/2026-06-15-auditoria-compliance-design.md`.
- Agents (2026-06-15): analista-negocio (superfície sobre dado real; HU-097 já existe; decision_trace; HU-149 alerta+malha fina nunca punição) e arquiteto-tecnico (AuditTrailQueryService/activity_log; DecisionExplanationService; abuse_alerts/detectores; LgpdMonitorService).

### HUs
- `docs/SILE_HUs_Completas_MD/EP12-Auditoria-e-Compliance/HU-097..HU-102.md`, `HU-149`.

### Reuso (NÃO recriar — consumir/estender)
- Trilha: `app/Support/Audit/AuditService.php` (log assinatura extensível — add personalData), `app/Concerns/HasAuditoria.php` (attribute_changes), activity_log (spatie via create_activity_log_table), `app/Models/AccessLog.php` + AccessHistoryController (variante global).
- Explicabilidade: `app/Models/ViabilityDecision.php` (per_cnae/rules_versions/fundamentacao — add decision_trace) + `app/Services/Expresso/FluxoExpressoService.php::emitir` ($resolved em escopo) + `app/Services/Analise/AnaliseTecnicaDecisionService.php` (Fase 10) + ConsultaViabilidadeResult::toArray (consulta_array).
- Malha fina (HU-149 destino): `app/Services/Analise/MalhaFinaService.php`. LGPD: `app/Models/LegalTerm.php`/`LegalTermAcceptance.php` + middleware de aceite por versão. Retenção: pruning Fase 3.1 (AuditModelsPruned). Padrões: ProcessoQueryService/ProcessoController (server-driven + CSV streaming), scheduler routes/console.php, RolesAndPermissionsSeeder.

### Testes
- phpunit.xml SQLite; baseline atual 1205 SQLite + 29 @group postgis (verificar via composer test, 2 processos). Spy no motor p/ provar RN-005 (zero recompute). Estrutura: tests/Feature/Auditoria/.
</canonical_refs>

<specifics>
## Specific Ideas
- HU-097 já está entregue (ViabilityDecision imutável + auditoria síncrona) — a Fase 12 verifica cobertura, não recria.
- A explicabilidade LÊ o decision_trace gravado; NUNCA recomputa (RN-005). Decisão legada degrada honesto ("não registrado nesta decisão"), nunca inventa.
- HU-149 é o teste anti-fachada mais importante: alerta + malha fina (ortogonal ao status), NUNCA punição/indeferimento automático; nasce desligada com defaults conservadores.
- Consultar/exportar auditoria é, em si, auditável (meta-auditoria) — relevante p/ LGPD (quem viu PII de quem).
</specifics>

<deferred>
## Deferred Ideas
- Export pleno XLSX/PDF (HU-131) → Fase 15 (CSV agora).
- Verificação IA polígono×fachada (RN-004 HU-149) → HU-115/EP14.
- Drill-down pleno de efetividade da detecção → HU-145/Fase 15.
- Eliminação/anonimização de dados do titular (LGPD art. 18) → DPO/SEDUR (conflita com retenção legal da trilha; não inventar rito).
- EscritorioVirtualEncadeadoDetector — 2ª onda (HU-139 é texto livre; depende de marcação estruturada).
- Papel auditor/DPO dedicado — default gestor+admin.
</deferred>

---

*Phase: 12-auditoria-e-compliance*
*Context gathered: 2026-06-15 via agents analista-negocio + arquiteto-tecnico*
