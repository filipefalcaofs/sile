# Phase 6: Classificação de Risco - Context

**Gathered:** 2026-06-13
**Status:** Ready for planning
**Source:** Agents analista-negocio + arquiteto-tecnico (política agentes-sile.mdc — autonomia) + spec `docs/superpowers/specs/2026-06-13-classificacao-de-risco-design.md`

<domain>
## Phase Boundary

Classificar qualquer CNAE por risco municipal (Decreto 32.636/2020) e sanitário (planilha VISA) como dimensões SEPARADAS, com condicionantes como perguntas que reclassificam o risco, e produzir o ENCAMINHAMENTO (decisão de roteamento auditada: elegível a expresso / vai à análise). Mantenedores de risco e condicionantes como dados versionados. Regras como dados versionados, nunca código.

Fora do escopo: o processo formal do fluxo expresso (Fases 8/9), a fila de análise técnica (Fase 10), o sandbox HU-143 (Fase 5 — mas a infra de rascunho nasce aqui), PDF/TVL (Fases 9/10). O motor de risco produz a decisão de roteamento e a fundamentação; quem consome é o EP07+.
</domain>

<decisions>
## Implementation Decisions

### Dado oficial real (confirmado no CSV)
- `docs/dados-oficiais/decreto-32636-2020-risco-municipal-unificado-cnae.csv` — colunas `cnae,descricao,condicionantes,risco_municipal_unificado`; 1.331 linhas: **767 BAIXO A + 328 BAIXO B + 236 ALTO**. `condicionantes` separadas por `|`.
- Dimensão sanitária: `docs/dados-oficiais/planilha-unificada-cnae-30-04-26.csv` (VISA) — tabela/domínio SEPARADO.
- Seed real (converter CSV→seed como na Fase 2 CNAE). O motor processa dado real; carga oficial substitui quando a SEDUR atualizar.

### Modelagem — regras como dados versionados (arquiteto-tecnico, opção c)
- Cabeçalho genérico `rule_versions` (espelha geo_layers): `domain` (enum RuleDomain), `version`, `status` (enum RuleVersionStatus: rascunho|vigente|substituida), `valid_from`, `valid_to`, `source`, `rules_version`, `published_at`, `published_by` (4 olhos), `unique(domain,version)`. Model `RuleVersion` com HasAuditoria + scopes `vigente(domain)`/`naData(domain,date)`/`versao(domain,version)`.
- `RuleVersionService` (`openDraft`/`publish`) espelha `GeoLayerService::openVersion` (fecha vigente anterior → substituida + valid_to; promove rascunho → vigente; audita).
- Tabelas tipadas por domínio (FK rule_version_id): `risk_classifications` (cnae_code, risco_municipal enum baixo_a|baixo_b|alto, observacao); `risk_condicionantes` (escopo, pergunta, tipo_resposta, regra_reclassificacao jsonb — híbrido pontual aceitável); dimensão sanitária separada.
- Rejeitado: reusar geo_layers (geométrico); jsonb genérico para tudo (perde integridade — shape fixo por lei).

### Motor (arquiteto-tecnico — espelha TerritoryService)
- `RiscoClassificationService`: DTOs readonly (`RiscoInput`, `RiscoResult`), resolução de versão 3 modos, `rules_version` na decisão. RiscoResult: risco municipal + sanitário SEPARADOS, encaminhamento, fundamentação, `versoes()`, `toArray()` snake_case. Auditoria via AuditService (log 'risco', event 'classificacao', rulesVersion).

### Encaminhamento parametrizável (analista-negocio — regra FIRME)
- Decreto só tem baixo_a/baixo_b/alto — NÃO existe "médio" (é diretriz operacional). Enum do decreto + mapa configurável `risk_level → fluxo` (default {baixo_a: expresso, baixo_b: expresso, alto: analise}) — parâmetro HU-014.
- Gatilhos CNAE (semi-expresso) como tabela parametrizada (motivo auditado); 3 conhecidos (enquadramento ausente, ZEIS especial, dados do processo).
- Exceção por localização ZEIS usa a restrição ambiental já carregada na Fase 4 (234 features).

### Parâmetros novos (catálogo HU-014, hoje 29)
- `risco.mapa_encaminhamento` (ou 3 toggles) — mapa risk_level→fluxo (default baixo_a/baixo_b=expresso, alto=analise).
- `risco.dimensao_tvl` — qual dimensão prevalece no TVL (default `municipal`).
- (constantes técnicas como cache_ttl em config/sile.php, fora do catálogo).

### Golden cases (arquiteto-tecnico)
- Seeds em `database/data/risco/`; casos entrada→esperado em `tests/Fixtures/golden/risco/*.json`; feature test `#[DataProvider]`. Tabulares → SQLite (sem PostGIS). Critério de regressão de domínio.

### Claude's Discretion
- Nomes exatos de tabelas/serviços/migrations; estrutura do mapa de encaminhamento (jsonb vs colunas); parsing das condicionantes do CSV; organização dos mantenedores na UI.
</decisions>

<canonical_refs>
## Canonical References

### Spec + advisories
- `docs/superpowers/specs/2026-06-13-classificacao-de-risco-design.md` — desenho aprovado.
- Transcrição dos agents (decisões): analista-negocio (sequenciamento + escopo + mapa CA→teste) e arquiteto-tecnico (modelagem rule_versions, motor, golden cases, degradação) — sessão 2026-06-13.

### HUs (CAs BDD)
- `docs/SILE_HUs_Completas_MD/EP06-Classificacao-de-Risco/HU-047..HU-053.md`.
- HU-019 (Manter condicionantes) e HU-020 (Manter classificação de risco) — EP02-Administracao.
- `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seção 4C (números do decreto) e seções 7.6.x (pendências de risco/gatilhos).

### Padrões a reusar (Fase 4 — versionamento)
- `app/Models/GeoLayer.php` (scopes vigente/naData), `app/Services/Geo/GeoLayerService.php` (openVersion — fecha anterior sem apagar), `app/Services/Geo/TerritoryService.php` + `TerritoryResult.php` (DTO readonly, status por dimensão, versoes(), auditoria) — o motor de risco ESPELHA esse padrão.
- `app/Enums/GeoLayerStatus.php` — modelo do enum de status (estender com `rascunho`).
- `database/seeders/CnaeSeeder.php` + `app/Services/CnaeImportService.php` — padrão de seed a partir de CSV oficial (database/data/).
- `app/Support/Settings.php` + `database/seeders/ParameterSeeder.php` (catálogo 29) — parâmetros novos.
- `app/Support/Audit/AuditService.php` + `app/Concerns/HasAuditoria.php` — auditoria (RN-002).
- `app/Models/Cnae.php` (code normalizado) — FK lógica de risk_classifications.cnae_code.

### Testes
- phpunit.xml (SQLite :memory: — risco é tabular, roda aqui sem PostGIS). Golden cases via `#[DataProvider]` (PHPUnit). Cada CA BDD → feature test.
</canonical_refs>

<specifics>
## Specific Ideas
- A infra `rule_versions` nasce nesta fase e a Fase 5 (motor LOUOS) herda — projetar genérica desde já.
- Condicionante-pergunta: golden case real da planilha VISA (produto não artesanal reclassifica).
- Encaminhamento é DECISÃO DE ROTEAMENTO auditada (não o processo expresso — Fases 8/9).
</specifics>

<deferred>
## Deferred Ideas
- HU-143 sandbox (simular impacto antes de publicar) — Fase 5 (infra rascunho/4-olhos nasce aqui).
- Lista completa de gatilhos CNAE e regra semi-expresso definitiva — pendente SEDUR (tabela parametrizada destrava).
- Prevalência da dimensão no TVL — parâmetro risco.dimensao_tvl até a SEDUR confirmar.
- Quadro 11↔11B e zona — Fase 5 (não afeta a 6).
</deferred>

---

*Phase: 06-classificacao-de-risco*
*Context gathered: 2026-06-13 via agents analista-negocio + arquiteto-tecnico*
