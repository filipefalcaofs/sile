# Fase 6 — Classificação de Risco

**Data:** 2026-06-13
**Status:** Aprovado (via agents analista-negocio + arquiteto-tecnico — política de autonomia agentes-sile.mdc)
**Fase:** 6 (EP06)
**Requisitos:** HU-019, HU-020, HU-047, HU-048, HU-049, HU-050, HU-051, HU-052, HU-053

## Objetivo

Classificar qualquer CNAE por risco municipal (Decreto 32.636/2020) e sanitário (planilha VISA) como **dimensões separadas**, com condicionantes operacionalizadas como **perguntas que reclassificam o risco**, determinando o encaminhamento (baixo → fluxo expresso; alto → análise humana; gatilhos derrubam para análise). Regras como **dados versionados**, nunca código.

## Por que esta fase agora (recomendação do analista-negocio)

Única das duas próximas (5 ‖ 6) com **dado oficial 100% disponível** e **sem depender da zona bloqueada** (Fase 4). Entrega valor de ponta a ponta hoje. A infra de **versionamento de regras** e os **golden cases** nascem aqui e a Fase 5 herda.

## Dado oficial real (confirmado no CSV)

`docs/dados-oficiais/decreto-32636-2020-risco-municipal-unificado-cnae.csv`:
- Colunas: `cnae,descricao,condicionantes,risco_municipal_unificado`.
- **1.331 linhas: 767 BAIXO A + 328 BAIXO B + 236 ALTO** (conferido). Cobertura 1:1 com as subclasses CNAE 2.3 já carregadas (Fase 2).
- `condicionantes`: condições gerais separadas por `|` (ex.: "escritório da empresa | não residencial | área ≤ 1.250m²" — Decreto 38.673/2024).
- Dimensão sanitária (VISA): `docs/dados-oficiais/planilha-unificada-cnae-30-04-26.csv` — **dimensão separada**.

## Decisões travadas

### Modelagem (arquiteto-tecnico — opção (c))
- Cabeçalho de versão genérico **`rule_versions`** (espelha `geo_layers`: domain, version, status, valid_from/valid_to, source, rules_version) + status **`rascunho`** (habilita o sandbox HU-143 da Fase 5) e `published_by` (4 olhos). Scopes `vigente(domain)`/`naData(domain,date)`/`versao(domain,version)`.
- Tabelas tipadas por domínio referenciando `rule_versions`:
  - `risk_classifications` (cnae_code, risco_municipal enum `baixo_a|baixo_b|alto`, observacao).
  - `risk_condicionantes` (escopo, pergunta, tipo_resposta, `regra_reclassificacao` jsonb — híbrido pontual aceitável: shape variável do mecanismo "DI").
  - Dimensão sanitária como tabela/domínio separado (nunca misturar com municipal).
- `RuleVersion` reusa o padrão de versionamento do `GeoLayerService` (`openDraft`/`publish` fecha a vigente anterior sem apagar, audita).
- **Rejeitado:** reusar `geo_layers` (é geométrico) e jsonb genérico para tudo (perde integridade; o shape é fixo por lei).

### Motor (arquiteto-tecnico)
- `RiscoClassificationService` (e, na Fase 5, `LouosEnquadramentoService`) espelhando `TerritoryService`/`TerritoryResult`: DTOs `readonly`, resolução de versão em 3 modos, `rules_version` gravada na decisão (RN-002). Contrato: CNAE(+respostas de condicionante) → `RiscoResult` (risco municipal + sanitário separados, encaminhamento, fundamentação, versões).

### Encaminhamento parametrizável (analista-negocio — regra FIRME)
- O Decreto **só tem baixo_a, baixo_b, alto — NÃO existe "médio"**. "Médio → expresso" é diretriz operacional, não do decreto.
- Enum do decreto + **mapa configurável `risk_level → fluxo`** (default `{baixo_a: expresso, baixo_b: expresso, alto: analise}`) — parâmetro HU-014, troca sem deploy.
- **Gatilhos CNAE** (semi-expresso) como **tabela parametrizada**: cada gatilho acionado derruba para análise com motivo auditado.
- Condicionante-pergunta: resposta reclassifica o risco (mecanismo "DI"); golden case real da VISA (ex.: CNAE 1031-7/00 produto não artesanal → reclassifica).

### Golden cases (arquiteto-tecnico)
- Seeds oficiais em `database/data/risco/`; casos entrada→esperado em `tests/Fixtures/golden/risco/*.json`; feature test com `#[DataProvider]`. Tabulares → rodam em SQLite (suíte rápida), sem PostGIS. Critério de regressão de domínio (ROADMAP Fase 6, critério 6).

## Escopo entregável (HUs) — analista-negocio

| HU | Entrega |
|---|---|
| HU-020 | Manter classificação de risco + seed do Decreto (767/328/236), multidimensional e versionado |
| HU-019 | Manter condicionantes como pergunta com efeito de reclassificação (RN-004/005) |
| HU-047 | Classificar CNAE por risco — duas dimensões separadas (RN-007/009/010) |
| HU-048 | Baixo risco + condicionantes atendidas = elegível a expresso (obrigação legal RN-007/008) |
| HU-049 | Encaminhamento parametrizável (baixo_b≈"médio") + gatilhos CNAE (semi-expresso) |
| HU-050 | Alto risco → análise humana (RN-006) |
| HU-051 | Exceções por localização — ZEIS viável (restrição já entregue na Fase 4, 234 features) |
| HU-052 / HU-053 | Consultar tabela vigente / Atualizar versionado e auditado |

Anti-fachada: nesta fase o "encaminhamento ao expresso" é a **decisão de roteamento** (resultado classificado + auditado), não o processo expresso completo (Fases 8/9/10).

## Bloqueado / pendente SEDUR (parametrizável — não bloqueia iniciar)

- Mapeamento "médio" ↔ baixo_b (HU-049 RN-008) — mapa `risk_level→fluxo` configurável.
- Lista completa de gatilhos CNAE / semi-expresso (HU-049 RN-007) — tabela parametrizada (3 conhecidos: enquadramento ausente, ZEIS especial, dados do processo).
- Prevalência municipal × sanitário no TVL (HU-047 RN-009) — duas dimensões persistidas + parâmetro `risco.dimensao_tvl` (default municipal).
- Semântica "Regra"/"Crítica" das condicionantes (HU-019 RN-004) — campos parametrizados.

## Decomposição (sub-entregas → planos)

1. **Fundação de regras versionadas**: `rule_versions` + RuleVersion + RuleVersionService (openDraft/publish/4-olhos) + enums (RuleDomain, RuleVersionStatus) — infra compartilhada (Fase 5 herda).
2. **Classificação municipal + seed oficial**: `risk_classifications` + seed real do CSV do Decreto (767/328/236) + RiscoClassificationService (dimensão municipal) + parâmetros (mapa risk_level→fluxo, dimensao_tvl).
3. **Dimensão sanitária + condicionante-pergunta**: tabela sanitária separada (seed VISA) + `risk_condicionantes` + reclassificação por resposta.
4. **Encaminhamento + gatilhos + exceções**: roteamento parametrizável (expresso/análise), tabela de gatilhos (semi-expresso), exceção ZEIS (usa restrição da Fase 4).
5. **Mantenedores (HU-019/020/052/053)**: CRUD na gestão (console, padrão CNAEs) sobre rascunhos + publicação 4 olhos + consulta vigente; golden cases.
6. **Fechamento**: verificação integral + golden cases verdes + evidência (classificação real sobre o CSV oficial).

## Critério de pronto (ROADMAP Fase 6)

1. Admin mantém condicionantes e risco como dados versionados, seed oficial do Decreto (767/328/236).
2. Classifica qualquer CNAE, mantendo municipal × sanitário separados.
3. Condicionante-pergunta reclassifica o risco conforme a resposta.
4. Baixo/médio(baixo_b) produzem encaminhamento expresso quando elegíveis; alto e gatilhos → análise.
5. Tabela vigente consultável e atualizável com auditoria.
6. Golden cases de classificação (incl. reclassificação por condicionante) na suíte de regressão.

## Dependências

Nenhuma nova (PHP puro + dados + PHPUnit) — confirmado pelo arquiteto-tecnico.

## Fora de escopo (YAGNI / outras fases)

Processo formal do fluxo expresso (Fases 8/9), fila de análise (Fase 10), HU-143 sandbox (Fase 5 — mas a infra `rascunho` nasce aqui), PDF/TVL (Fases 9/10 — exigiria dompdf, escalar).
