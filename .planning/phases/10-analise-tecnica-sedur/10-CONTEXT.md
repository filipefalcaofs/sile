# Phase 10: Análise Técnica SEDUR - Context

**Gathered:** 2026-06-14
**Status:** Ready for planning
**Source:** Agents analista-negocio + arquiteto-tecnico (política agentes-sile.mdc) + spec `docs/superpowers/specs/2026-06-14-analise-tecnica-design.md`

<domain>
## Phase Boundary

Analistas da SEDUR fecham o ciclo da decisão HUMANA dos processos que o fluxo expresso (Fase 9) NÃO decide automaticamente (em_analise: sem zona/Quadro 10, semi-expresso/gatilho, toggle off). Caminho real: encaminhar (HU-079, já vem da Fase 9) → setores/caixa (HU-138 pré-req + HU-080 distribuir + HU-081 assumir) → fila com SLA (HU-144) → consulta (HU-082) → ficha pré-analisada pelo motor (HU-140) + precedentes (HU-142) + preencher/autosave/diff (HU-135) → parecer/textos-padrão (HU-085) → deferir/indeferir/condicionantes (HU-086/087/088) → encerrar (HU-089), com malha fina (HU-136) e TVL PDF interno (HU-132). Reusa ViabilityDecision/StateMachine/SolicitacaoViabilityResolver/ResultadoEmitido (Fase 9), geometry property_polygon (Fase 8), motores (5/6), console SEDUR.

Fora do escopo / bloqueado: transmissão Regin/SEFAZ na conclusão (HU-104/110) → Fase 13 (reusa contratos Unavailable + ResultadoEmitido — auditam pendência, nunca "enviado"). HU-083/084 PARCIAL: ciclo de pendência interno (portal + e-mail) AGORA; convite Simplifica/Regin + multicanal → EP11. Exportação plena (HU-131) → Fase 15. Zona oficial Quadro 10 pendente SEDUR (precedentes/veredito degradam; o humano decide assim mesmo — é o caso que exige humano).
</domain>

<decisions>
## Implementation Decisions

### Decisão central (arquiteto) — parecer humano REUSA ViabilityDecision
- A decisão técnica final grava na MESMA `viability_decisions` (Fase 9) com flow='analise_tecnica' e decided_by_user_id=analista (≠ null). Fonte única do TVL PDF (HU-132) e da explicabilidade (Fase 12). ResultadoExpressoController/ViabilityDecisionResource servem os 2 flows sem reescrita.
- `AnaliseTecnicaDecisionService::decide()` constrói a decisão a partir da FICHA do analista (não do resolver puro) — pode DEFERIR sem zona oficial (o humano é quem decide o caso pendente), com fundamentação própria. Transação: cria ViabilityDecision (flow analise_tecnica, decided_by, per_cnae/fundamentacao/rules_versions da ficha, TVL só no deferimento via TvlNumberGenerator) → transiciona → auditoria SÍNCRONA → após commit dispara ResultadoEmitido (3 listeners da Fase 9 reusam; Regin/SEFAZ bloqueados → Fase 13).

### Modelo de dados
- Setores HU-138: `sectors` (name/active; inativa, não exclui com pendência) + pivot `sector_user` (analista N:N). CRUD Gestao\SectorController, permissão manter-setores.
- Campos de análise em `viability_requests` (alter, FORA do fillable, como protocoled_at/bap_*): sector_id, assigned_user_id, assigned_at, analysis_category (expresso/semi_expresso, índice), in_fine_mesh (bool índice, ORTOGONAL ao status), analysis_stage, analysis_stage_started_at, analysis_due_at (índice — base do SLA). Histórico de atribuição via AuditService.
- `analysis_records` (ficha versionada HU-135/140): viability_request_id, revision, status (rascunho/finalizada), analyst_user_id, engine_snapshot/engine_rules_versions jsonb, engine_available bool, per_cnae jsonb (escolhido×sugerido/grupo uso/valor TLL/gatilhos/condicionantes — espelha ficha SAPS), conditions jsonb, parking jsonb (vagas req×exigido×vistoria), parecer text, finalized_at. Revisão imutável após finalizar (RN-003); diff = 2 linhas; autosave PATCH debounce config.
- `analysis_divergences` (HU-140 → HU-145): analysis_record_id, cnae, field, suggested_value, final_value, justification (append na finalização; tabela dedicada — relatório cruza processos).
- `analysis_pendencies` (HU-083/084): description, status (aberta/respondida/expirada), due_at, response.
- `fine_mesh_referrals` (HU-136): reason obrigatório, created/resolved (tabela, não status — ortogonal, repetível, lote; liga in_fine_mesh).
- `standard_texts` (HU-085): category/content/active/version (biblioteca administrável).
- `tvl_documents` (HU-132): viability_decision_id, disk, path, verification_code, generated_by/at (cada emissão/reimpressão = linha auditada).

### Máquina de estados (ADICIONA ao mapa, sem tocar Fase 8/9)
- em_analise → {em_pendencia, deferida, indeferida}; em_pendencia → {em_analise}. deferida/indeferida seguem finais (= encerramento HU-089). Anti-regressão: deferida→em_analise inválida. Cada transição grava timeline + auditoria.

### Ficha pré-analisada HU-140 (reuso, zero recomputo)
- Transição → em_analise dispara evento `EncaminhadoParaAnalise` (after-commit); listener AUTO-DESCOBERTO `PreAnalisarProcesso` roda SolicitacaoViabilityResolver::resolve e cria analysis_records rev 1 (rascunho) pré-preenchida. FluxoExpressoService só passa a dispatch o evento no encaminharAnalise. Degradação (motor indisponível → ficha vazia + engine_available=false + aviso). Recalcular = ação explícita (nova revisão). Divergência → analysis_divergences.

### Precedentes HU-142 (@group postgis)
- Contrato PrecedentRepository + PostgisPrecedentRepository (espelha SpatialRepository) + fake. Imóvel: ST_Intersects(property_polygon, ST_GeomFromGeoJSON(:current)) join viability_decisions, ordenado decided_at, limit param; fallback street+number. CNAE na zona: agrega por outcome em janela param (zona do engine_snapshot — degrada honesto sem zona). LGPD: nunca CPF.

### TVL PDF HU-132
- DEPENDÊNCIA NOVA: barryvdh/laravel-dompdf (pré-aprovada PROJECT.md; adicionar na wave do TVL). TvlPdfService::generate(ViabilityDecision): TvlDocument. Só outcome=deferida (FA-01) + perfil emitir-tvl. Storage disk parametrizado storage.tvl.disk NUNCA público; download temporarySignedRoute (TTL param). Emissão/reimpressão auditadas em tvl_documents. NÃO vai ao cidadão. Assinatura imagem parametrizável (tvl.assinatura.*); gov.br/ICP gancho → SEDUR.

### Fila SLA HU-144
- AnalysisSlaService materializa analysis_due_at (recalculado a cada transição) reusando BusinessDeadlineCalculator (Fase 9; HU-137 troca só ali). Semáforo + tempo restante on-the-fly. Fila ordenada por analysis_due_at (indexado). HU-147 (Fase 11) lê no scheduler.

### Consulta HU-082
- Gestao\ProcessoController@index server-driven (espelha ResultadoExpressoController). Filtros completos SAPS (print 16) + analista + categoria (derivadas) + paginação server-side + índices; whereLike caseSensitive:false. Busca global Cmd+K (endpoint leve). Detalhe: abas + timeline + mini-mapa Leaflet. CSV simples; export pleno → HU-131/Fase 15.

### Parâmetros / permissões / Claude's Discretion
- Parâmetros grupo `analise`: features.analise_tecnica, analise.sla.*, analise.pendencia.prazo_resposta_dias, analise.precedentes.janela_meses/max_itens, storage.tvl.disk, tvl.assinatura.modo/imagem_path, tvl.download.assinatura_ttl_minutos. Constantes técnicas (autosave debounce) em config. Permissões aditivas: analisar-processos, distribuir-processos, emitir-tvl, encaminhar-malha-fina, manter-setores; reusa consultar-solicitacoes.
- Discrição: nomes exatos de migrations/colunas/classes/eventos; shape do analysis_records.per_cnae espelhando a ficha SAPS; rotas/estrutura da UI; assinatura do AnaliseTecnicaDecisionService/PrecedentRepository.
</decisions>

<canonical_refs>
## Canonical References

### Spec + agents
- `docs/superpowers/specs/2026-06-14-analise-tecnica-design.md`.
- Agents (2026-06-14): analista-negocio (escopo/sequenciamento, núcleo, parciais EP11, bloqueios) e arquiteto-tecnico (reuso ViabilityDecision, analysis_records, PrecedentRepository, TVL PDF, SLA, consulta).

### HUs
- `docs/SILE_HUs_Completas_MD/EP10-Análise-Tecnica-SEDUR/HU-079..HU-089.md`, HU-132, HU-135, HU-136, HU-140, HU-142, HU-144.
- `docs/SILE_HUs_Completas_MD/EP02-Administracao/HU-138-Manter-setores.md` (pré-req).
- Telas legado SAPS: `docs/legado-saps/01..08,16.jpg` (ficha de análise, filtros, gatilhos, condicionantes, vagas).

### Reuso (NÃO recriar — consumir/estender)
- Decisão: `app/Models/ViabilityDecision.php` (flow/decided_by_user_id já existem) + `app/Enums/DecisionOutcome.php` + `app/Services/Expresso/TvlNumberGenerator.php` + `app/Events/ResultadoEmitido.php` + os 3 listeners (Fase 9).
- Estado: `app/Services/Solicitacao/ViabilityRequestStateMachine.php` (mapa pronto p/ adicionar em_analise→...) + `app/Enums/ViabilityRequestStatus.php`.
- Ficha pré-analisada: `app/Services/Solicitacao/SolicitacaoViabilityResolver.php` (Fase 9) + `app/Services/Expresso/FluxoExpressoService.php::encaminharAnalise` (só passa a dispatch o evento).
- Precedentes: `app/Services/Geo/SpatialRepository.php`/`PostgisSpatialRepository.php` (padrão p/ PrecedentRepository) + geometry property_polygon (08-06).
- Prazo: `app/Services/Expresso/BusinessDeadlineCalculator.php` (Fase 9). Consulta/Resource: `app/Http/Controllers/Gestao/ResultadoExpressoController.php` + `app/Http/Resources/ViabilityDecisionResource.php`. Mapa: Leaflet Fase 4. Auditoria: AuditService. Parametrização: Settings::get + config/sile.php. CRUD console: ViabilityServiceTypeController/RiscoController padrão.

### Testes
- phpunit.xml SQLite; baseline atual 955 (+ @group postgis). Precedentes/geometria usam @group postgis (PostgisTestCase + geo:preparar-banco-de-testes). TVL PDF com Storage::fake (assert %PDF). Golden/smoke #[DataProvider]. Estrutura: tests/Feature/Analise/.
</canonical_refs>

<specifics>
## Specific Ideas
- A pré-análise REEXECUTA o motor real (resolver) e grava o sugerido; o analista confirma ou DIVERGE — cada divergência registrada (insumo HU-145). É a alavanca ~30min→~5min, sem fachada.
- O parecer humano nasce na conclusão como ViabilityDecision (flow analise_tecnica) — mesma tabela do expresso, fontes diferentes. Pode deferir sem zona (o humano decide o caso pendente).
- Malha fina é ORTOGONAL ao status (flag + tabela), pode atingir até deferido (corrige bug legado).
- Só a transmissão Regin/SEFAZ fica bloqueada; a decisão/TVL/auditoria são reais.
</specifics>

<deferred>
## Deferred Ideas
- Transmissão Regin/SEFAZ na conclusão (HU-104/110) — Fase 13 (reusa ResultadoEmitido/contratos Unavailable).
- HU-083/084 convite via Simplifica/Regin + comunicação multicanal (WhatsApp/in-app/templates) — EP11.
- Exportação plena CSV/XLSX/PDF da consulta (HU-082 RN-011 / HU-131) — Fase 15 (CSV simples agora).
- Assinatura digital gov.br/ICP do TVL (HU-132 RN-005) — gancho; default imagem do diretor.
- Valor TLL monetário na ficha — depende da tabela de taxas/DAM (bloqueado → Fase 13); exibe referência/pendente, nunca inventa.
- Contagem de SLA em dias úteis (HU-137 feriados) — seam BusinessDeadlineCalculator pronto; horas/dias-corridos até lá.
- Recurso administrativo contra indeferimento — fora do escopo até confirmar com a SEDUR.
</deferred>

---

*Phase: 10-analise-tecnica-sedur*
*Context gathered: 2026-06-14 via agents analista-negocio + arquiteto-tecnico*
