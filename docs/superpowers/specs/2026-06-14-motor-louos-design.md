# Fase 5 — Motor de Regras da LOUOS

**Data:** 2026-06-14
**Status:** Aprovado (via agents analista-negocio + arquiteto-tecnico — política agentes-sile.mdc)
**Fase:** 5 (EP05 + mantenedores dos Quadros)
**Requisitos:** HU-015, HU-016, HU-017, HU-018, HU-038, HU-039, HU-040, HU-041, HU-042, HU-043, HU-044, HU-045, HU-046, HU-143

## Objetivo

O motor aplica os Quadros da LOUOS (Lei 9.148/2016) — 7 (enquadramento por área), 10 (permissão por zona), 11/11A (condições pela via) — como **regras-dados versionadas**, consolidando o resultado (permitido / permitido com condições / não permitido / pendente) com **fundamentação legal e versão das regras** registradas. Mantenedores dos Quadros e sandbox de simulação (HU-143).

## Decisão de escopo honesto (analista-negocio + bloqueio da Fase 4)

- **Quadro 7 (HU-016/038) — ENTREGÁVEL agora**: enquadra atividade por CNAE + área informada → grupo/subgrupo de uso. Depende só de CNAE (carregado) + área (entrada). Seed derivado da Lei 9.148/2016 (modelo "Enquadramento TVL" do SAPS legado: código LOUOS → classificação + faixas de área + flag risco).
- **Quadro 10 (HU-015/039) — MODELADO, DEGRADA sem zona**: a permissão por zona consome a ZONA urbanística, que a Fase 4 retorna `indisponivel` (pendente SEDUR). O Quadro 10 é modelado como dado versionado; sem zona real, o motor NÃO inventa permissão — retorna `quadro10.status = indisponivel` e o consolidado vira `pendente` (degradação comunicada). Quando a SEDUR liberar SIGIS/CA 2000, muda a carga, não a lógica.
- **Quadro 11/11A (HU-017/018/040/041) — PARCIAL**: a geometria viária foi entregue na Fase 4 (800 features), mas o ATRIBUTO de classificação viária LOUOS e a correspondência "Quadro 11"↔11B pendem confirmação SEDUR (STATE Blockers). Modelado e versionado; aplica quando o atributo existir.
- **Consolidação (HU-044), fundamentação (HU-045), versionamento (HU-046) — ENTREGÁVEL**: orquestrador + base legal + versão; HU-046 HERDA a infra `rule_versions` da Fase 6.
- **Condicionantes urbanísticas/vagas (HU-042), restrições especiais (HU-043) — ENTREGÁVEL/PARCIAL**: vagas parametrizado; restrições usam a camada ambiental (ZEIS) da Fase 4.
- **Sandbox HU-143 — ENTREGÁVEL**: simula uma versão `rascunho` (status do rule_versions, Fase 6) contra cenários reais sem afetar a vigente; publicação 4 olhos.

## Arquitetura (arquiteto-tecnico)

- **Quadros como domínios do `rule_versions`** (infra da Fase 6): tabelas tipadas por domínio referenciando `rule_version_id`:
  - `louos_quadro7_faixas` (grupo, subgrupo, area_min, area_max, observacao; faixas não-sobrepostas validadas).
  - `louos_quadro10_permissoes` (zona, grupo_uso, subgrupo, permissao enum permitido|permitido_condicionado|proibido, condicionante_ref, base_legal).
  - `louos_quadro11_condicoes_via` (+ 11a) (classe_via, condicoes).
  - Enum `RuleDomain` estendido: louos_quadro7|louos_quadro10|louos_quadro11|louos_quadro11a (já previsto no 06-01).
- **`LouosEnquadramentoService`** espelha o `TerritoryService`/`RiscoClassificationService`: DTOs readonly (`EnquadramentoInput` {area, cnaes, TerritoryResult}, `EnquadramentoResult` com cada quadro como dimensão {status: identificado|nao_encontrado|indisponivel, ...dados, motivo, versao_regra} + consolidado {resultado, fundamentacao[], motivo} + versoes() + toArray()). Resolução de versão 3 modos (vigente/naData/versao p/ sandbox). Auditoria 'louos'/'enquadramento' com rules_version (RN-002).
- **Golden cases** (critério 7 do ROADMAP): seeds em database/data/louos/, casos entrada→esperado em tests/Fixtures/golden/louos/*.json, feature test #[DataProvider]; casos que dependem de zona marcados `requer_zona` (esperam `pendente` enquanto a base não chega). Tabular → SQLite.
- **Mantenedores HU-015..018** no console (padrão CNAEs/risco): CRUD sobre rascunhos + publicação 4 olhos (RuleVersionService.publish, reusado da Fase 6).

## Degradação sem zona (sem fachada)

Propaga `input.territory.zona.status === 'indisponivel'` → Quadro 10 `indisponivel` (não consulta louos_quadro10_permissoes, não inventa) → consolidado `pendente` com motivo "Enquadramento por área (Quadro 7) realizado; permissão por zona pendente da base oficial". Quadro 7 roda normal. Teste anti-fachada: nunca `permitido`/`nao_permitido` sem zona.

## Dependências

Nenhuma nova (PHP puro + dados + PHPUnit). HU-046 reusa `rule_versions`/`RuleVersionService` da Fase 6.

## Critério de pronto (ROADMAP Fase 5)

1. Admin mantém Quadros 7/10/11/11A como dados versionados, com vigência e histórico auditado.
2. Motor enquadra pela área (Quadro 7) usando CNAE + área.
3. Motor verifica permissão na zona (Quadro 10) e condições pela via (11/11A) — degradando comunicado onde a base é pendente.
4. Condicionantes e restrições aplicadas; resultado consolidado em parecer único.
5. Toda execução registra fundamentação legal + versão das regras.
6. Gestor simula impacto de alteração antes de publicar (HU-143 sandbox; 4 olhos).
7. Golden cases do motor verdes no CI a cada mudança de regra.

## Fora de escopo / bloqueado (pendente SEDUR)

- Quadro 10 sobre zona real (SIGIS/CA 2000 pendente) — degrada para pendente.
- Atributo viário LOUOS e "Quadro 11"↔11B — pendente confirmação SEDUR.
- Planilhas oficiais dos Quadros — seeds derivados da Lei 9.148/2016 até a entrega; carga oficial substitui sem mudar a lógica.
