# Phase 15: Relatórios e Indicadores - Context

**Gathered:** 2026-06-15
**Status:** Ready for planning
**Source:** Agents analista-negocio + arquiteto-tecnico (política agentes-sile.mdc) + 5 decisões do usuário (XLSX=openspout, incluir HU-137, captura estruturada de quedas, painel público fora, gráficos=Apache ECharts)

<domain>
## Phase Boundary

CAMADA DE LEITURA/ANÁLISE GERENCIAL sobre os dados reais já registrados pelas Fases 1–12 — nenhuma dependência externa (EP15 é a fase autonomamente executável). Entrega: dashboard executivo (HU-122), relatórios por período (HU-123), zona (HU-124), CNAE (HU-125), risco (HU-126), taxa de deferimento (HU-127) e indeferimento (HU-128), tempo médio POR ETAPA (HU-129), produtividade por analista (HU-130), exportação transversal CSV/XLSX/PDF (HU-131 — entregável central, dívida diferida pelas Fases 8–12) e quedas por gatilho com taxa de resposta expressa (HU-145). Inclui o cadastro de feriados (HU-137) como habilitador do prazo correto da HU-129.

Princípio raiz (entrega-funcional): **número sempre real sobre o dado já no banco — nenhum indicador inventa valor**. Onde a fonte real não existe, o recorte degrada honesto ("indisponível/pendente") ou fica bloqueado e registrado, nunca simulado. O teste do CA-03 (anti-fachada: "não inventa número quando falta dado") é o mais importante da fase.

Fora do escopo / deferido: painel público de transparência (decisão política — aval SEDUR, toggle futuro); recorte por zona urbanística oficial (HU-031 bloqueada — degrada para bairro até a base GIS/Fase 13); materialização de indicadores em tabela-resumo (GROUP BY + cache de TTL curto basta no volume de Salvador).
</domain>

<decisions>
## Implementation Decisions

### Camada de agregação (HU-122 a HU-130, HU-145)
- Serviços route-free por domínio em `app/Services/Relatorios/`, espelhando `ProcessoQueryService` (Builder reutilizável + `when()` + `whereLike(caseSensitive:false)` + eager-load anti-N+1; paginação/export no controller). NÃO criar mega-service nem materialized view.
  - `IndicadoresViabilidadeService` — HU-123/124/125/126/127/128 (GROUP BY/count sobre `viability_requests` + `viability_decisions`).
  - `TempoAnaliseService` — HU-129 (tempo por etapa; reusa `BusinessDeadlineCalculator`).
  - `ProdutividadeAnalistaService` — HU-130 (por `decided_by_user_id`/`analysis_records`/`assigned_user_id`).
  - `ExpressoQuedaService` — HU-145 (taxa de resposta expressa + ranking de motivos + drill-down).
  - Value Object compartilhado `ReportFilters` (período/setor/zona/CNAE/categoria/analista) extraído dos helpers do `ProcessoQueryService` (`valor/inteiro/data/categoria`, `CATEGORIAS`, `GRUPOS_STATUS`) — reusado pelos serviços de relatório E pelo drill-down (RN-002 HU-145 cai direto no `ProcessoQueryService::filtered`).
- Agregação via SQL (count/avg/group by sobre colunas já indexadas: `protocoled_at`, `decided_at`, `status`, `sector_id`, `assigned_user_id`, `analysis_category`), nunca loop PHP. Cache do dashboard via `Cache::remember` com TTL técnico (`sile.relatorios.cache_ttl_segundos`=300 em config, fora do catálogo). Números sempre reais.

### HU-129 — tempo por etapa + feriados (HU-137 incluída)
- Fonte: `viability_request_transitions` (from/to/created_at) — duração da etapa = diff entre transições consecutivas. Etapas: preenchimento (`created_at`→`protocoled_at`), espera/encaminhamento (`protocolada`→`em_analise`/decisão direta=expresso), análise (`em_analise`→decisão, descontando `em_pendencia`), pendência (somatório `em_pendencia`→`em_analise`). Complemento: `analysis_stage`/`analysis_stage_started_at`/`analysis_due_at` (HU-144).
- Relatórios SAPS (RN-006): Tempo de Emissão de TVL = `protocoled_at`→`viability_decisions.decided_at` onde `tvl_product_number IS NOT NULL`; Sedes de Escritório Virtual = filtro `is_virtual_office=true`.
- **HU-137 (feriados) INCLUÍDA nesta fase** (decisão do usuário): cadastro versionado/auditado/parametrizável (mecanismo interno) + extensão do `BusinessDeadlineCalculator` (seam pronto — hoje conta horas-calendário) para pular fins de semana + feriados. A LISTA OFICIAL de feriados municipais é pendência SEDUR → até lá, degrada honesto (dias úteis sem feriados, com ressalva visível). Nunca feriado inventado.

### HU-131 — exportação transversal (componente único)
- Contrato compartilhado em `app/Services/Relatorios/Export/`: `ReportDefinition` (readonly: titulo, colunas, Builder filtrado, filtrosAplicados, logName, event, personalData, arquivoBase) + interface `ReportFormatExporter` (1 driver/formato) + `ReportExporter` (orquestra sync streaming OU Job assíncrono acima do limiar).
- Drivers: `CsvExporter` (streaming nativo — consolida os DOIS CSVs existentes de `AuditoriaController::export` e `ProcessoController::exportarCsv`); `PdfExporter` (dompdf já instalado + Blade genérico de relatório com rodapé "Total de registros: N" + data/hora + filtros — RN-010/CA-07); `XlsxExporter` via **openspout/openspout** (DEPENDÊNCIA NOVA APROVADA pelo usuário — streaming, baixa memória, encaixe no Job assíncrono; preferido a maatwebsite/excel e phpspreadsheet).
- RN-005 (conjunto filtrado): `ReportDefinition` carrega o MESMO Builder da tela — zero filtro duplicado. RN-006 (async): `ReportExporter` compara `count()` com `relatorios.export.assincrono_limiar_linhas` → acima despacha `GerarExportacaoJob` (padrão Fase 3.1: tries/timeout/backoff/fila de `config('sile.relatorios.job.*')`), grava em disco não-público parametrizado + notifica via EP11 (NotificationDispatcher) + download por URL assinada (espelha TVL HU-132). RN-007 (LGPD): projeção minimizada igual à tela (`cpf_masked`; `personalData:true` herda meta-auditoria HU-101). RN-008 (auditoria): `ReportExporter` audita toda exportação (usuário/tela/filtros/formato/volume) via `AuditService`.
- Frontend: `resources/js/components/ui/data-table/export-menu.tsx` (irmão de `table-toolbar`/`per-page-select`) que monta dropdown CSV/XLSX/PDF apontando para `{url do index}?formato={fmt}&...paramsAtuais` do `useServerTable`. Qualquer tela com `useServerTable`+`DataTable` ganha export adicionando `<ExportMenu>` + branch `?formato=` (~3 linhas) no controller delegando ao `ReportExporter`.
- Retrofit incremental sem regressão: 1º migrar os CSVs de `AuditoriaController`+`ProcessoController` (mantendo colunas/arquivo, preservando `personalData`/HU-101 — testes atuais como rede anti-regressão); depois listagens Fases 1–2 (CNAEs/usuários/perfis/parâmetros/acessos), 1 controller por vez; usuários exporta `cpf_masked` salvo permissão de PII. Telas novas do EP15 nascem com export.

### HU-122 dashboard + gráficos (Apache ECharts) + HU-145 série temporal
- KPIs operacionais reais estendendo `Gestao\DashboardController`/`KpiCard` (Fase 2.4, padrão "KPI gated por permissão, sem delta inventado"): volume no período, distribuição por status/risco/categoria, taxas, tempo médio, taxa de resposta expressa, fila/SLA. Sem série histórica persistida → comparativos ("+X%") degradam honesto (sem delta) até haver janela.
- **Gráficos via Apache ECharts** (decisão do usuário — sobrescreve a recomendação inicial de SVG/CSS próprio; DEPENDÊNCIA npm NOVA APROVADA). Integração: wrapper SSR-safe (renderizar só no cliente — mesmo padrão do react-leaflet v5 da Fase 4, Inertia v3 tem SSR), import tree-shakeable (`echarts/core` + componentes/renderer usados, não o bundle cheio), tema claro/escuro integrado ao DS TailAdmin (console escuro × portal claro). Wrapper próprio em `resources/js/components/ui/chart/` para encapsular ECharts e padronizar tema/responsividade.
- HU-145: taxa de resposta expressa = `viability_decisions.flow='expresso'` + `decided_by_user_id IS NULL` ÷ elegíveis (transições protocolada→deferida/indeferida vs →em_analise). Série temporal + meta parametrizável (RN-004). Drill-down via `analysis_divergences` (analista×motor) + `ProcessoQueryService::filtered`.

### HU-145 — captura estruturada do motivo/gatilho de queda (ajuste retroativo)
- **Decisão do usuário: adicionar captura estruturada AGORA** (não MVP grosso). Hoje a queda grava só 3 categorias grossas em `transitions.reason`/auditoria, e o gatilho específico (`TipoGatilho`: enquadramento_ausente/zeis_especial/dados_do_processo) fica aninhado em `analysis_records.engine_snapshot` (cobertura parcial — null nos casos degradados). Persistir o motivo/gatilho estruturado por CNAE no MOMENTO da queda: tabela/coluna dedicada (`viability_request_id`, `cnae`, `tipo_gatilho`, `dimensao`, `motivo`) gravada em `FluxoExpressoService::encaminharAnalise` (Fases 9/10). Ajuste ADITIVO com ANTI-REGRESSÃO das Fases 9/10. Atende RN-001 (motivo estruturado, nunca texto livre) sem fachada.

### HU-130 — produtividade (modo conservador por sensibilidade RH/LGPD)
- Default agregado/anonimizado; visão NOMINAL atrás de permissão dedicada (`relatorios.produtividade.nominal`); analista vê só o próprio recorte, gestor vê todos. Toda consulta auditada (RN-002). Política definitiva (quem vê nominal) = confirmação SEDUR (registrada; default conservador não bloqueia).

### Parâmetros HU-014 / permissões / Claude's Discretion
- Parâmetros novos (catálogo `ParameterSeeder` + espelho `config/sile.php`): `relatorios.export.assincrono_limiar_linhas`(int), `relatorios.export.formatos_habilitados`(json — `["csv","xlsx","pdf"]`), `relatorios.export.retencao_dias`(int — pruning no scheduler), `relatorios.expresso.meta_taxa`(decimal) e `relatorios.expresso.janela_dias`(int — HU-145 RN-004). HU-129: reusar `analise.sla.*`; criar `relatorios.tempo.etapas`(json) só se preciso declarar quais transições compõem cada etapa (confirmar SEDUR). Constantes técnicas FORA do catálogo (config/sile.php): `relatorios.export.max_linhas`, `.chunk`(200), `.pdf.paper/orientation`, `.disk`(não-público com guarda anti-public), `cache_ttl_segundos`(300), `job.{tries,timeout,backoff,fila}`. Page size = whitelist/constante (PER_PAGE_OPTIONS), não parâmetro.
- Permissões aditivas: `consultar-relatorios` (gestor/admin), `relatorios.produtividade.nominal` (restrita), `relatorios.exportar` (se separar do consultar). Dono único do `ParameterSeeder`/config/permissões/seeder-tests (wave 1; atualizar contagem 85→85+N e 27→27+N).
- Discrição do planner/executor: nomes exatos de migrations/colunas/services/DTOs; shape do `ReportDefinition`/payloads; rotas/UI; quais transições compõem cada etapa (default parametrizado); layout exato dos relatórios SAPS.
</decisions>

<canonical_refs>
## Canonical References

### Agents (2026-06-15)
- analista-negocio (id 1b144604): 8/11 indicadores 100% calculáveis hoje; HU-124→bairro (zona bloqueada); HU-129 tempo por etapa via transitions + feriados HU-137; HU-145 captura parcial (gap interno); pendências SEDUR (painel público, produtividade, metas, feriados, gatilhos).
- arquiteto-tecnico (id 0cc29899): serviços route-free espelhando ProcessoQueryService; export único (ReportDefinition/ReportExporter + drivers); `?formato=` via useServerTable; baseline 85 parâmetros/27 permissões, suíte ~1315 SQLite + 29 postgis.

### HUs
- `docs/SILE_HUs_Completas_MD/EP15-Relatórios-e-Indicadores/HU-122..HU-131.md`, `HU-145`. HU-137 (feriados) — cadastro a criar.

### Reuso (NÃO recriar — consumir/estender)
- Agregação/filtros: `app/Services/Analise/ProcessoQueryService.php` (filtered/when/whereLike/categorias/visaoSetor), `app/Services/Analise/AnalysisSlaService.php` (cálculo puro parametrizado). Tempo: `app/Services/Expresso/BusinessDeadlineCalculator.php` (seam feriados HU-137). Dados: `viability_requests`/`viability_decisions`(flow/outcome/decided_by_user_id/tvl_product_number/is_virtual_office)/`viability_request_transitions`/`viability_request_cnaes`/`analysis_records`/`analysis_divergences`/`risk_classifications`/`risk_sanitary_classifications`/`sectors`.
- Export: CSV streaming em `app/Http/Controllers/Gestao/AuditoriaController.php::export` e `ProcessoController.php::exportarCsv`; PDF em `app/Services/Analise/TvlPdfService.php` (dompdf + disk não-público + URL assinada). Async: Job/fila Fase 3.1 (ex.: `DecidirFluxoExpressoJob`), Storage + `temporarySignedRoute` (routes/gestao.php TVL). Notificação: EP11 `NotificationDispatcher`.
- Dashboard/UI: `app/Http/Controllers/Gestao/DashboardController.php` + `KpiCard`/`ProgressBar` (Fase 2.4); `resources/js/components/ui/data-table/*` (`useServerTable`, `table-toolbar`, `per-page-select`); mapa SSR-safe react-leaflet (Fase 4) como referência de wrapper client-only para o ECharts.
- Parâmetros/permissões: `database/seeders/ParameterSeeder.php` + `config/sile.php`; `RolesAndPermissionsSeeder`; scheduler `routes/console.php` (pruning retenção, padrão Fase 3.1).

### Dependências novas (APROVADAS pelo usuário — exigem composer/npm require)
- `openspout/openspout` (composer) — driver XLSX do export.
- Apache ECharts (npm — `echarts`, com wrapper próprio; avaliar import por core/tree-shaking) — gráficos do dashboard/série temporal.

### Testes
- phpunit.xml SQLite; baseline 1315 SQLite + 29 @group postgis (verificar via `composer test`, 2 processos — SQLite + @group postgis separados, lição da Fase 10). Cada CA BDD → feature test; CA-03 anti-fachada é prioritário. Estrutura sugerida: tests/Feature/Relatorios/.

### Waves sugeridas (parallelization:true; donos únicos espelhando Fases 10–12)
- W1 fundação (donos únicos): 15-01 parâmetros/config/permissões + 15-02 export base (ReportDefinition/ReportExporter/CsvExporter/PdfExporter/Job skeleton/binding AppServiceProvider) ‖.
- W2 serviços route-free ‖: 15-03 IndicadoresViabilidadeService+ReportFilters; 15-04 TempoAnaliseService+HU-137 feriados+ProdutividadeAnalistaService; 15-05 ExpressoQuedaService + captura estruturada de queda (ajuste Fases 9/10).
- W3 HTTP+rotas (dono único routes/gestao.php): 15-06 RelatorioController + `?formato=` + download assinado + XlsxExporter (openspout).
- W4 retrofit (sem novas rotas): 15-07 migra CSVs Auditoria/Processo; 15-08 retrofit Fases 1–2 (1 controller/plano).
- W5 UI: 15-09 dashboard + charts ECharts (`ui/chart/`) + páginas dos relatórios + `<ExportMenu>`; 15-10 nav/Cmd+K (dono gestao-layout).
- W6 fechamento: 15-11 seeds dev (fluxo real, driver-aware) + comando de evidência (ex.: `relatorios:exportar`) + pruning retenção no scheduler + golden/smoke + verificação integral 2 processos + guardião-entrega.
</canonical_refs>

<specifics>
## Specific Ideas
- HU-131 é a dívida transversal que as Fases 8–12 vinham diferindo; o componente único é o coração da fase — telas novas e antigas herdam export sem reimplementar.
- HU-129 resolve a distorção do legado (19 dias reportados vs 42h reais): medir POR ETAPA expõe onde o tempo é consumido (ex.: espera do BAP, que nem é trabalho da SEDUR); a regra de prazo correta (dias úteis/feriados) elimina o +48h/fim de semana indevido.
- HU-145 fecha o ciclo de melhoria contínua do expresso: medir (este relatório) → priorizar → parametrizar (HU-143 sandbox) → medir de novo. A captura estruturada da queda é pré-requisito anti-fachada.
- Gráficos com Apache ECharts por decisão explícita do usuário (única lib de chart do projeto); manter SSR-safe e tema do DS sob controle.
- Toda geração/exportação de relatório é auditável (meta-auditoria) — relevante para LGPD em órgão público (quem viu PII de quem).
</specifics>

<deferred>
## Deferred Ideas
- Painel público de transparência (decisão política — pergunta 13 do ROADMAP): fora do escopo agora; aval SEDUR; futuro toggle `features.painel_publico` OFF com agregados anônimos.
- Recorte por zona urbanística oficial (HU-124): liga quando a base GIS/Quadro 10 entrar (HU-031/HU-107, Fase 13); até lá, bairro.
- Materialização de indicadores em tabela-resumo: só se a performance real exigir (GROUP BY + cache basta no volume de Salvador).
- Lib de chart com interatividade rica além do necessário: ECharts já cobre; não expandir escopo visual.

## Pendências SEDUR/DPO (escalam, não bloqueiam — degradam honesto)
- Lista oficial de feriados municipais (HU-137) — mecanismo de cadastro entregue; lista via export do SAPS.
- Lista completa de gatilhos CNAE (HU-049/HU-145) — `risk_triggers` administrável; novos gatilhos entram como dado.
- Base de zona LOUOS / Quadro 10 (HU-031/HU-107) — destrava recorte por zona e reduz quedas "sem zona".
- Metas/janela da taxa de resposta expressa (HU-145) — parâmetro "meta não definida" até a SEDUR fixar.
- Política de produtividade nominal (HU-130) — quem vê ranking nominal (default conservador anonimizado).
- Layout/colunas oficiais dos relatórios SAPS (Tempo de Emissão de TVL, Sedes de Escritório Virtual).
- LGPD por coluna sensível na exportação (RN-007) — quais colunas exigem permissão específica → DPO.
</deferred>

---

*Phase: 15-relatorios-e-indicadores*
*Context gathered: 2026-06-15 via agents analista-negocio + arquiteto-tecnico + 5 decisões do usuário*
