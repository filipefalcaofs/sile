# Phase 5: Motor de Regras da LOUOS - Context

**Gathered:** 2026-06-14
**Status:** Ready for planning
**Source:** Agents analista-negocio + arquiteto-tecnico (sessão 2026-06-13/14, política agentes-sile.mdc) + spec `docs/superpowers/specs/2026-06-14-motor-louos-design.md`

<domain>
## Phase Boundary

O motor aplica os Quadros da LOUOS (Lei 9.148/2016) como regras-dados versionadas: Quadro 7 (enquadramento por área → grupo/subgrupo de uso), Quadro 10 (permissão da atividade na zona), Quadros 11/11A (condições de instalação pela via). Consolida o resultado (permitido / permitido com condições / não permitido / pendente) com fundamentação legal e versão das regras registradas. Inclui mantenedores dos Quadros (HU-015..018), condicionantes urbanísticas/vagas (HU-042), restrições especiais (HU-043), e o sandbox de simulação de parametrização (HU-143).

Fora do escopo: a base GIS de zona (SIGIS/CA 2000 — Fase 13, pendente SEDUR); o consumo do motor pela consulta prévia (Fase 7) e solicitação (Fase 8). O motor produz o parecer fundamentado; quem consome é o EP07+.
</domain>

<decisions>
## Implementation Decisions

> **Correção de mapeamento de HUs (2026-06-14, planejamento):** os arquivos oficiais das HUs definem **HU-015 = Manter Quadro 7**, **HU-016 = Manter Quadro 10**, **HU-017 = Manter Quadro 11**, **HU-018 = Manter Quadro 11A**. A prosa abaixo (e o spec) cita os mantenedores invertidos (ex.: "Quadro 7 (HU-016)"); o mapeamento correto é o dos arquivos de HU e é o que os PLAN.md usam. Motor: HU-038=Q7, HU-039=Q10, HU-040=Q11, HU-041=Q11A.

### Escopo honesto (analista-negocio + bloqueio Fase 4)
- **Quadro 7 (HU-016/038)**: ENTREGÁVEL — enquadra por CNAE (carregado) + área (entrada). Seed derivado da Lei 9.148/2016 (modelo "Enquadramento TVL" do SAPS: código LOUOS → classificação + faixas de área + flag risco).
- **Quadro 10 (HU-015/039)**: MODELADO, DEGRADA — consome zona (Fase 4 retorna indisponivel/pendente SEDUR). Sem zona real, NÃO inventa permissão: quadro10.status=indisponivel → consolidado pendente. Liga quando a SEDUR entregar SIGIS/CA 2000.
- **Quadro 11/11A (HU-017/018/040/041)**: PARCIAL — geometria viária entregue (Fase 4, 800 features), mas atributo viário LOUOS e "Quadro 11"↔11B pendem SEDUR. Modelado/versionado; aplica quando o atributo existir.
- **HU-044/045/046**: ENTREGÁVEL — consolidação, fundamentação legal, versionamento (HU-046 HERDA rule_versions da Fase 6).
- **HU-042/043**: vagas parametrizado; restrições usam camada ambiental ZEIS (Fase 4).
- **HU-143 sandbox**: ENTREGÁVEL — simula versão rascunho (status do rule_versions, Fase 6) contra cenários reais sem afetar a vigente; publicação 4 olhos.

### Arquitetura (arquiteto-tecnico)
- Quadros como domínios do `rule_versions` (Fase 6): tabelas tipadas `louos_quadro7_faixas` (grupo/subgrupo/area_min/area_max; faixas não-sobrepostas), `louos_quadro10_permissoes` (zona/grupo_uso/subgrupo/permissao enum/condicionante_ref/base_legal), `louos_quadro11_condicoes_via` (+11a). Enum RuleDomain estendido com louos_quadro7|10|11|11a.
- `LouosEnquadramentoService` espelha TerritoryService/RiscoClassificationService: DTOs readonly EnquadramentoInput {area, cnaes, TerritoryResult} / EnquadramentoResult (cada quadro = dimensão {status identificado|nao_encontrado|indisponivel, dados, motivo, versao_regra} + consolidado {resultado, fundamentacao[], motivo} + versoes() + toArray() snake_case). Resolução 3 modos (vigente/naData/versao p/ sandbox). Auditoria 'louos'/'enquadramento' com rules_version (RN-002).
- Mantenedores HU-015..018 no console (padrão CNAEs/risco da Fase 6): CRUD sobre rascunhos + publicação 4 olhos (RuleVersionService.publish reusado).
- Golden cases: database/data/louos/, tests/Fixtures/golden/louos/*.json, #[DataProvider]; casos requer_zona esperam pendente até a base chegar. Tabular → SQLite.

### Degradação sem zona (sem fachada)
- input.territory.zona.status === 'indisponivel' → Quadro 10 indisponivel (não consulta a tabela, não inventa) → consolidado pendente (motivo explícito). Quadro 7 roda normal. Teste anti-fachada: nunca permitido/nao_permitido sem zona.

### Claude's Discretion
- Nomes exatos de tabelas/migrations; parsing do seed do Quadro 7; estrutura dos mantenedores; se HU-143 sandbox vira plano próprio ou parte do versionamento.

### Dependências
Nenhuma nova (confirmado pelo arquiteto-tecnico). HU-046 reusa rule_versions/RuleVersionService da Fase 6.
</decisions>

<canonical_refs>
## Canonical References

### Spec + advisories
- `docs/superpowers/specs/2026-06-14-motor-louos-design.md`.
- Agents (sessão 2026-06-13/14): analista-negocio (escopo Quadro 7 buildável, Quadro 10 degrada) e arquiteto-tecnico (rule_versions domains, LouosEnquadramentoService, golden cases, degradação).

### HUs
- `docs/SILE_HUs_Completas_MD/EP05-Motor-de-Regras-da-LOUOS/HU-038..HU-046.md`.
- HU-015 (Quadro 7), HU-016, HU-017, HU-018 (mantenedores) — EP02-Administracao.
- HU-143 (Simular impacto de parametrização) — EP02-Administracao.
- `docs/ANALISE-HUs-REUNIAO-SEDUR.md` (Quadro 11↔11B pendente; modelo enquadramento TVL).
- `docs/legado-saps/14-enquadramento-tvl-louos-faixas-area.jpg` (modelo real do Quadro 7).

### Padrões a reusar (Fase 6 — versionamento; Fase 4 — motor)
- `app/Models/RuleVersion.php` + `app/Services/Rules/RuleVersionService.php` (Fase 6 — openDraft/publish 4-olhos; scopes vigente/naData/versao). HU-046 reusa.
- `app/Enums/RuleDomain.php`, `RuleVersionStatus.php` (Fase 6 — estender RuleDomain com louos_*).
- `app/Services/Risco/RiscoClassificationService.php` + DTOs RiscoInput/RiscoResult (Fase 6) e `app/Services/Geo/TerritoryService.php`/`TerritoryResult.php` (Fase 4) — PADRÃO do motor a espelhar (status por dimensão, degradação, auditoria, toArray).
- `app/Services/Geo/TerritoryResult.php` — a zona vem daqui (status indisponivel quando bloqueada).
- `database/seeders/RiscoMunicipalSeeder.php` + `app/Services/CnaeImportService.php` — padrão de seed de CSV/dados oficiais.
- `app/Support/Settings.php` + `database/seeders/ParameterSeeder.php` (catálogo 31) — parâmetros (ex.: vagas).
- `app/Support/Audit/AuditService.php` — auditoria RN-002.
- Golden cases: `tests/Feature/Risco/RiscoGoldenCaseTest.php` (Fase 6) — padrão #[DataProvider] a espelhar.

### Testes
- phpunit.xml (SQLite :memory: — Quadros são tabulares, rodam aqui sem PostGIS). Suíte canônica: `--exclude-group postgis` (baseline atual 519). Casos que dependem de zona (geometria) marcados requer_zona → esperam pendente.
</canonical_refs>

<specifics>
## Specific Ideas
- A infra rule_versions (Fase 6) é reusada — NÃO recriar; só estender RuleDomain e adicionar as tabelas louos_*.
- O motor espelha RiscoClassificationService/TerritoryService (mesmo vocabulário de status/degradação) — consistência.
- Golden cases do Quadro 7 (área) rodam em SQLite; casos de zona esperam pendente (honesto).
</specifics>

<deferred>
## Deferred Ideas
- Quadro 10 sobre zona real — Fase 13 (SIGIS/CA 2000 pendente SEDUR).
- Atributo viário LOUOS e Quadro 11↔11B — pendente confirmação SEDUR.
- Consumo do motor (consulta prévia/solicitação) — Fases 7/8.
- Planilhas oficiais dos Quadros — substituem os seeds derivados da Lei quando a SEDUR entregar.
</deferred>

---

*Phase: 05-motor-louos*
*Context gathered: 2026-06-14 via agents analista-negocio + arquiteto-tecnico*
