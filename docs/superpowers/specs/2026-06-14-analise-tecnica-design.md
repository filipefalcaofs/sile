# Spec de Design — Fase 10: Análise Técnica SEDUR (EP10)

**Data:** 2026-06-14
**Status:** Aprovado (brainstorming via agents analista-negocio + arquiteto-tecnico)
**Fontes:** análise de negócio + arquitetura (sessão 2026-06-14), ROADMAP Phase 10, HUs EP10 (HU-079..089, 132, 135, 136, 140, 142, 144) + HU-138, telas legado SAPS (docs/legado-saps/).

## Problema

Analistas da SEDUR recebem as solicitações que o fluxo expresso (Fase 9) NÃO decide automaticamente (`em_analise`: sem zona/Quadro 10, semi-expresso/gatilho, ou toggle off) e fecham o ciclo da decisão HUMANA: distribuir → assumir → fila com SLA → ficha pré-analisada pelo motor → parecer → deferir/indeferir/condicionantes → encerrar, com malha fina e emissão de TVL PDF interno.

## Decisão de escopo (os dois agents convergiram)

**Núcleo entregável AGORA (sem fachada):** HU-079 (encaminhar — já vem da Fase 9), HU-138 (setores — PRÉ-REQUISITO, não existe), HU-080 (distribuir/caixa do setor), HU-081 (assumir), HU-082 (consulta), HU-144 (fila com SLA), HU-140 (ficha pré-analisada pelo motor), HU-142 (precedentes), HU-135 (preencher ficha/autosave/diff), HU-085 (parecer/textos-padrão), HU-086/087/088 (deferir/indeferir/condicionantes), HU-089 (encerrar), HU-136 (malha fina), HU-132 (TVL PDF). Tudo apoiado nas peças prontas das Fases 8/9 (ViabilityDecision, StateMachine, SolicitacaoViabilityResolver, geometry) e 5/6 (motores).

**BLOQUEADO honesto → Fase 13:** apenas a TRANSMISSÃO Regin/SEFAZ na conclusão (HU-104/110) — reusa os contratos Unavailable + o `ResultadoEmitido` da Fase 9 (auditam pendência, nunca "enviado"). A decisão/parecer/TVL são 100% reais e auditados.

**PARCIAL (cruza EP11):** HU-083/084 — ciclo de pendência interno (estado em_pendencia + portal SILE + e-mail simples) entra AGORA; convite via Simplifica/Regin e comunicação multicanal (WhatsApp/in-app/templates) → EP11/Fase 13.

**Dependência nova (pré-aprovada PROJECT.md):** `barryvdh/laravel-dompdf` para o TVL PDF (HU-132) — adicionada na wave do TVL, isolada. PROJECT.md já lista dompdf como lib aplicável do SIGVISA.

**→ HU-131/Fase 15:** exportação plena CSV/XLSX/PDF da consulta (HU-082 RN-011) — entrega CSV simples agora.

## Arquitetura

### Decisão central — o parecer humano REUSA `ViabilityDecision`
A decisão técnica final grava na MESMA tabela `viability_decisions` (Fase 9), com `flow='analise_tecnica'` e `decided_by_user_id=analista` (≠ null). Justificativa: a migração/PHPDoc já declaram a tabela como "fonte do PDF/TVL (Fase 10) e da explicabilidade (Fase 12)"; `ResultadoExpressoController`/`ViabilityDecisionResource` (Fase 9) servem os dois flows sem reescrita; `DecisionOutcome`/`TvlNumberGenerator`/`ResultadoEmitido` reusados. Diferença: o `FluxoExpressoService` RECUSA decidir quando o veredito é `pendente` (sem zona); o humano é quem a lei manda decidir nesse caso — `AnaliseTecnicaDecisionService` grava a decisão a partir da FICHA do analista (não do resolver puro), podendo deferir sem zona, com fundamentação própria.

### Modelo de dados
- **`sectors`** (HU-138): name, active (inativa, não exclui com pendência); pivot **`sector_user`** (analista N:N setor). CRUD `Gestao\SectorController`, permissão `manter-setores`.
- **Campos de análise em `viability_requests`** (alter, fora do fillable — como protocoled_at/bap_*): `sector_id`, `assigned_user_id`, `assigned_at`, `analysis_category` (expresso/semi_expresso, índice), `in_fine_mesh` (bool, índice — ortogonal ao status), `analysis_stage`, `analysis_stage_started_at`, `analysis_due_at` (índice — base do SLA). Histórico de atribuição via AuditService.
- **`analysis_records`** (HU-135/140 — ficha versionada): viability_request_id, revision, status (rascunho/finalizada), analyst_user_id, engine_snapshot jsonb, engine_rules_versions jsonb, engine_available bool, per_cnae jsonb (status escolhido×sugerido, grupo uso, valor TLL, gatilhos, condicionantes — espelha ficha SAPS), conditions jsonb, parking jsonb (vagas req×exigido×vistoria), parecer text, finalized_at. Revisão imutável após finalizar (RN-003); diff = comparar 2 linhas; autosave = PATCH no rascunho (debounce config).
- **`analysis_divergences`** (HU-140 RN-002/003 → HU-145): analysis_record_id, cnae, field, suggested_value, final_value, justification. Append na finalização. Tabela dedicada (relatório HU-145 cruza processos).
- **`analysis_pendencies`** (HU-083/084): viability_request_id, requested_by_user_id, description, status (aberta/respondida/expirada), due_at, responded_at, response.
- **`fine_mesh_referrals`** (HU-136): viability_request_id, referred_by_user_id, reason (obrigatório), created_at, resolved_at. Tabela (não status) — ortogonal, pode repetir, lote.
- **`standard_texts`** (HU-085): category, content, active, version. Biblioteca administrável.
- **`tvl_documents`** (HU-132): viability_decision_id, disk, path, verification_code, generated_by_user_id, generated_at. Cada emissão/reimpressão = nova linha auditada.

### Máquina de estados (ADICIONAR ao mapa)
`em_analise → {em_pendencia, deferida, indeferida}`; `em_pendencia → {em_analise}`. `deferida`/`indeferida` seguem finais (= encerramento HU-089). Malha fina NÃO muda status (flag `in_fine_mesh`). Cada transição grava timeline + auditoria. Anti-regressão: `deferida→em_analise` inválida.

### Ficha pré-analisada (HU-140) — reuso, zero recomputo
Transição `→ em_analise` dispara evento `EncaminhadoParaAnalise` (after-commit). Listener AUTO-DESCOBERTO `PreAnalisarProcesso` roda `SolicitacaoViabilityResolver::resolve` e cria `analysis_records` rev 1 (rascunho) com engine_snapshot/per_cnae pré-preenchidos. `FluxoExpressoService` só passa a `dispatch` o evento no `encaminharAnalise` (sem outra mudança). Degradação (FA-01): motor indisponível → ficha vazia + engine_available=false + aviso. Recalcular = ação explícita (nova revisão). Divergência analista×motor → `analysis_divergences` na finalização.

### Precedentes (HU-142) — @group postgis
Contrato `PrecedentRepository` + `PostgisPrecedentRepository` (espelha SpatialRepository) + fake. Imóvel: `ST_Intersects(vr.property_polygon, ST_GeomFromGeoJSON(:current))` join viability_decisions, ordenado por decided_at, limit parametrizável; fallback portável (street+number). CNAE na zona: agrega viability_decisions por outcome dentro de janela parametrizável (zona lida do engine_snapshot — degrada honesto sem zona). LGPD: nunca CPF.

### TVL PDF (HU-132)
`barryvdh/laravel-dompdf` (pré-aprovada). `TvlPdfService::generate(ViabilityDecision): TvlDocument`. Fonte = ViabilityDecision deferida + condicionantes da ficha. Só `outcome=deferida` (FA-01); perfil `emitir-tvl` (RN-001). Storage disk parametrizado (`storage.tvl.disk`, NUNCA público); download por `temporarySignedRoute` (TTL parametrizado). Emissão/reimpressão auditadas em `tvl_documents`. NÃO vai ao cidadão (CA-02). Assinatura por imagem parametrizável (`tvl.assinatura.*`); gov.br/ICP = gancho → SEDUR.

### Pendências (HU-083/084)
Analista cria pendência → `em_analise → em_pendencia` (+ analysis_pendencies). Resposta pelo portal "Minhas solicitações" → `em_pendencia → em_analise`. Evento `PendenciaSolicitada` = gancho para a notificação plena do EP11; na Fase 10, convite in-app + e-mail simples. Regin/Simplifica bloqueado → Fase 13.

### Consulta de processos (HU-082)
`Gestao\ProcessoController@index` server-driven (espelha ResultadoExpressoController). Filtros completos do SAPS (print 16) + analista + categoria (expresso/semi/malha fina/sede escritório, derivadas) + paginação server-side + índices nos campos filtrados; whereLike caseSensitive:false (PostgreSQL). Busca global Cmd+K (endpoint leve). Detalhe: abas + timeline (transitions) + mini-mapa Leaflet. CSV simples agora; export pleno → HU-131/Fase 15.

### Fila com SLA (HU-144)
`AnalysisSlaService` materializa `analysis_due_at` (prazo-limite absoluto, recalculado a cada transição) reusando `BusinessDeadlineCalculator` (Fase 9; HU-137 feriados troca só ali). Semáforo (verde/amarelo/vermelho) + tempo restante calculados on-the-fly. Fila ordenada por analysis_due_at (indexado). HU-147 (Fase 11) lê analysis_due_at no scheduler.

### Decisão final reusa o evento
`AnaliseTecnicaDecisionService::decide()` (todas CNAEs deferidas → deferida; alguma indeferida → indeferida, HU-086 RN-004): transação cria ViabilityDecision (flow analise_tecnica, decided_by, per_cnae/fundamentacao/rules_versions da ficha, TVL só no deferimento), transiciona, audita SÍNCRONO e — após commit — dispara `ResultadoEmitido`. Os 3 listeners da Fase 9 reusam → Regin/SEFAZ bloqueados honestos → Fase 13.

## Parametrização (HU-014), permissões, testes

**Parâmetros — grupo `analise`:** features.analise_tecnica (toggle); analise.sla.<etapa>.dias + analise.sla.semaforo.amarelo_percentual; analise.pendencia.prazo_resposta_dias; analise.precedentes.janela_meses + .max_itens; storage.tvl.disk; tvl.assinatura.modo + .imagem_path; tvl.download.assinatura_ttl_minutos. Constantes técnicas (autosave debounce, cache) em config. Reusa risco.mapa_encaminhamento.

**Permissões (aditivas):** analisar-processos, distribuir-processos, emitir-tvl, encaminhar-malha-fina, manter-setores. Reusa consultar-solicitacoes na consulta/detalhe. CA-04: 403 auditado.

**Testes (TDD):** StateMachine (novas transições + anti-regressão); AnaliseTecnicaDecisionService (defere/indefere reusa ViabilityDecision+ResultadoEmitido; auditoria síncrona via Event::fake; bloqueio CA-03); pré-análise (reuso resolver, divergência, degradação); precedentes @group postgis (ST_Intersects real) + fallback SQLite + LGPD; TVL PDF (Storage::fake, assert %PDF, só deferida, disk não-público, URL assinada, auditoria); HU-082 (filtros/paginação/busca global); HU-080/081 (caixa/lote/auditoria); HU-144 (ordenação/semáforo/contadores); HU-083/084 (ciclo pendência); HU-136 (malha fina qualquer status + lote).

## Waves (para o gsd-planner)

| Wave | Conteúdo |
|---|---|
| **1 — Fundação** | parâmetros grupo analise + permissões + fallback ‖ schema (sectors+pivot, alter viability_requests, analysis_records, analysis_divergences, analysis_pendencies, fine_mesh_referrals, standard_texts, tvl_documents) + enums + models/factories ‖ StateMachine (novas transições, TDD) |
| **2 — Bases** | HU-138 setores (CRUD console) ‖ AnalysisSlaService (reusa BusinessDeadlineCalculator) ‖ PrecedentRepository (contrato+postgis+fake) |
| **3 — Distribuição + pré-análise** | HU-079/080/081 (evento EncaminhadoParaAnalise, caixa do setor, assumir, lote) ‖ PreAnalisarProcesso (HU-140, reusa resolver) |
| **4 — Ficha** | HU-135 backend (criar/autosave/finalizar/revisões/diff) + divergências (HU-140) + textos-padrão (HU-085) + precedentes (HU-142) |
| **5 — Decisão humana** | HU-085/086/087/088/089 (AnaliseTecnicaDecisionService → ViabilityDecision/ResultadoEmitido) ‖ pendências (HU-083/084 + evento gancho EP11) ‖ malha fina (HU-136) |
| **6 — TVL PDF + consulta** | HU-132 (dompdf — após aprovar) ‖ HU-082 (filtros/busca global/índices) |
| **7 — UI console** | fila com SLA (HU-144), ficha SAPS (HU-135), consulta+detalhe+mini-mapa+timeline (HU-082), emissão TVL, distribuição |
| **8 — Fechamento** | seeds dev, comando (analise:decidir), golden/smoke, @group postgis precedentes, verificação integral + checkpoint humano |

## Bloqueios e pendências
- **Bloqueado → Fase 13:** transmissão Regin/SEFAZ na conclusão (HU-104/110, reusa ResultadoEmitido/contratos Unavailable). HU-083/084 convite Simplifica/Regin + multicanal → EP11.
- **→ Fase 15:** exportação plena (HU-131). **→ EP11:** comunicação multicanal/escalonamento HU-147.
- **Aprovação técnica:** barryvdh/laravel-dompdf (única dep nova; pré-aprovada PROJECT.md).
- **Pendências SEDUR (default parametrizável):** SLAs por etapa; regra semi-expresso/lista de gatilhos; modelo exato da ficha SAPS (campos/condicionantes/vagas); valor TLL monetário (taxa/DAM bloqueado → exibe referência ou pendente, nunca inventa); semântica/destino da malha fina; formato/assinatura do TVL (Anderson); zona Quadro 10 (precedentes/veredito degradam, analista decide); recurso administrativo (fora do escopo até confirmar).
