# Justificativa em todo indeferimento e reconhecimento das camadas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** `docs/superpowers/specs/2026-09-26-justificativa-indeferimento-e-camadas-design.md`

**Goal:** Nenhum indeferimento sem justificativa legível (textos padrão do catálogo e do documento SEDUR), exibida em todos os canais de consulta; logradouros de Salvador inteira; zona fictícia fora de produção; camadas incidentes do imóvel (logradouros, bairro, zona, ABM trechos, ZEM, ZPAM, ZUE, loteamentos) identificadas, gravadas e exibidas.

**Architecture:** `JustificativaIndeferimentoBuilder` monta o `reason` do expresso a partir do `consulta_array` e do `DecisionTextCatalog`. Invariante no model `ViabilityDecision`. `CamadasIncidentesService` produz um inventário por camada a partir do ponto do imóvel, gravado em `viability_requests.camadas_incidentes` e reaproveitado pelo `TerritorialRuleService`. Comandos `geo:importar-wfs` e `geo:encerrar-camada` para a carga de logradouros e a retirada da zona fictícia.

**Tech Stack:** Laravel 13, PHPUnit 12, PostGIS, Inertia v3, React 19.

## Global Constraints

- Textos de indeferimento vêm do `DecisionTextCatalog` (editáveis); nunca string solta no serviço.
- WFS mockado com `Http::fake` nos testes; nenhuma chamada real na suíte.
- Camada indisponível é registrada como indisponível; nunca omitida, nunca inventada.
- Sem parâmetro novo no `ParameterSeeder`; raio de logradouros próximos em `config/viabiliza.php`.
- pt-BR em textos e testes; `vendor/bin/pint --dirty --format agent`; `npm run build` após TSX.
- Operações em produção (importação WFS, encerrar zona fictícia) só com pedido explícito do usuário.

---

### Task 1: Invariante — indeferimento exige `reason`

**Files:** `app/Models/ViabilityDecision.php`; `tests/Feature/Decisao/ViabilityDecisionJustificativaTest.php`

- [ ] Teste RED: criar `ViabilityDecision` indeferida com `reason` nulo/vazio lança `DomainException` ("Indeferimento sem justificativa"); deferida sem `reason` segue permitida.
- [ ] GREEN: evento `saving` no model.
- [ ] Rodar as suítes `tests/Feature/Expresso tests/Feature/Analise` e listar os caminhos que quebrarem (esperado: Quadro 10/11A, análise técnica) — são as tasks 2 a 4.

### Task 2: Justificativa do expresso (Quadro 10 / 11A)

**Files:** `app/Services/Expresso/JustificativaIndeferimentoBuilder.php`; `app/Services/Expresso/FluxoExpressoService.php` (`emitir()`); `tests/Feature/Expresso/JustificativaIndeferimentoTest.php`

- [ ] Ler no `consulta_array` de cada CNAE `nao_permitido` qual quadro vetou (conferir as chaves reais em `ConsultaViabilidadeResult::toArray()` / `EnquadramentoResult`: permissão do Quadro 10 e consolidado do 11A com a classe viária).
- [ ] RED (CA-J1/J2/J3): Quadro 10 → texto `justificativa.conclusao.nao_permitido` com `:zona`; 11A → `nao_permitido_via` com `:classe_via`; dois CNAEs → dois parágrafos, cada um prefixado por "CNAE 0000-0/00 — descrição:".
- [ ] GREEN: builder + `emitir()` grava `reason` quando `Indeferida`.
- [ ] Teste de RT-01 (CA-J4) continua verde.

### Task 3: Sem BAP e convite expirado no catálogo

**Files:** `DecisionTextCatalog`, `DecisionTextSeeder`, `IndeferirSemBapService`, `IndeferirPorPrazoConviteService`, testes existentes dos dois serviços.

- [ ] RED (CA-J5): `reason` igual ao template do catálogo (`justificativa.indeferimento.sem_bap`, `justificativa.indeferimento.convite_expirado`).
- [ ] GREEN com a redação atual como padrão; descrição no seeder indica "redação oficial pendente SEDUR".
- [ ] Ajustar a contagem de grupos em `DecisionTextCrudTest` se mudar.

### Task 4: Análise técnica exige parecer para indeferir

**Files:** `ConcluirAnaliseRequest` (e o request da decisão da ficha, se separado), `AnaliseTecnicaDecisionService`, `ConclusaoAnaliseService`, testes da análise.

- [ ] RED (CA-J6): indeferir sem parecer → erro de validação "Informe o parecer com o motivo do indeferimento (visível ao requerente)"; com parecer → `decision.reason` = parecer.
- [ ] GREEN.

### Task 5: Justificativa em todos os canais

**Files:** `ProcessoController` + `resources/js/pages/gestao/processos/show.tsx`; `ConsultaProtocoloController` + `resources/js/pages/portal/.../protocolo.tsx`; `IndeferimentoPdfService` + `documentos/indeferimento.blade.php`; `HttpReginParecerNotifier`; testes de cada um.

- [ ] RED (CA-J8): Inertia assert `decisao.motivo` no detalhe da gestão e no protocolo do portal (indeferida); PDF contém o `reason`; `Http::fake` do REGIN prova `JUSTIFICATIVA_ANALISE` = `reason` + fundamentação (≤ 10.000 caracteres).
- [ ] GREEN; card "Motivo do indeferimento" nas duas telas; consulta pública sem motivo (teste existente continua verde).
- [ ] `npm run build`.

### Task 6: `geo:importar-wfs` (logradouros da cidade)

**Files:** `app/Console/Commands/ImportarCamadaWfsCommand.php`; `app/Services/Geo/GeoServerWfsPaginador.php`; `tests/Feature/Geo/ImportarCamadaWfsCommandTest.php`

- [ ] RED (CA-C1): `Http::fake` com 3 páginas (WFS 2.0 `count`/`startIndex`/`sortBy=CODLOG`, `srsName=EPSG:4326`) → 1 nova versão vigente de `via` com a soma das feições; versão anterior substituída; auditoria com contagem; página com erro aborta sem publicar versão parcial.
- [ ] GREEN usando `GeoJsonLayerImporter`.
- [ ] Documentar o comando no `docs/integracoes/geoserver-sedur.md` (seção de carga).

### Task 7: `geo:encerrar-camada` e trava da zona fictícia

**Files:** `app/Console/Commands/EncerrarCamadaGeoCommand.php`; `database/seeders/ZonaFicticiaDevSeeder.php`; testes.

- [ ] RED (CA-C2): comando fecha a camada (status substituída, `valid_to`), audita; com a fictícia encerrada, `TerritoryService::identify` busca a zona no GeoServer (fake). Seeder recusa `app()->environment('production')`.
- [ ] GREEN.

### Task 8: Camadas incidentes do imóvel

**Files:** migração `add_camadas_incidentes_to_viability_requests_table`; `app/Services/Geo/CamadasIncidentesService.php`; `GeoServerWfsCamadaClient` (consulta sem filtro devolvendo propriedades); `SpatialRepository::featuresWithin()` + Postgis + fake; `TerritorioProcessoService`/`ProtocolarSolicitacaoService`; `TerritorialRuleService` (reusar o inventário); testes.

- [ ] RED (CA-C3/C4/C5): protocolo grava `camadas_incidentes` com `logradouro_tributario`, `logradouros_proximos[]` (CODLOG, nome, distância), `bairro`, `zona`, `camadas[]` (código da regra, título, camada, status `dentro|fora|indisponivel`, `atributos`, `incide`); ABM trecho 5 incide, trecho 3 não; WFS 500 → `indisponivel` e regra encaminha à análise.
- [ ] GREEN; a decisão expressa refaz o inventário e o `TerritorialRuleService` usa o mesmo resultado (sem segunda consulta WFS por camada).

### Task 9: Exibição das camadas

**Files:** `AnalysisRecordController::localizacaoDoImovel` + `ficha-analise/show.tsx`; `ProcessoController` + `processos/show.tsx`; testes Inertia.

- [ ] RED (CA-C6): props `camadas` nas duas telas.
- [ ] GREEN: lista "Camadas do imóvel" (camada → elemento; indisponível explícito); `npm run build`.

### Task 10: Verificação e registro

- [ ] `vendor/bin/pint --dirty --format agent`; suítes `Expresso Louos Regin Geo Realty Solicitacao Viabilidade Decisao Analise Risco`.
- [ ] `STATE.md`: decisões e pendências SEDUR (redação sem BAP/convite; base de lotes).
- [ ] Após deploy e com pedido do usuário: rodar em produção `geo:importar-wfs via logradouro:VM_LOGRADOURO` e `geo:encerrar-camada zona zona-ficticia-dev`, conferir contagem e uma identificação no Comércio (212) e na borda (trecho 5).
