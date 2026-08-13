# Fase 7 — Consulta Prévia de Viabilidade

**Data:** 2026-06-14
**Status:** Aprovado (via agents analista-negocio + arquiteto-tecnico — política agentes-sile.mdc)
**Fase:** 7 (EP07)
**Requisitos:** HU-054, HU-055, HU-056, HU-057, HU-058, HU-059, HU-060

## Objetivo

Cidadão consulta a viabilidade de uma atividade em um endereço/CNAE sem criar processo formal — PRIMEIRA entrega que executa o fluxo de decisão de ponta a ponta, orquestrando os motores reais já prontos: território (Fase 4), enquadramento LOUOS (Fase 5) e classificação de risco (Fase 6).

## Decisões (agents)

### Orquestração (arquiteto-tecnico) — sem recomputar veredito
- `ConsultaViabilidadeService` em `app/Services/Viabilidade/` ORQUESTRA e AUDITA; NÃO tem lógica de decisão própria. Pipeline: (endereço → Geocoder → lat/lng) → `TerritoryService::identify` → `LouosEnquadramentoService::enquadrar(EnquadramentoInput::paraConsulta(...))` → `RiscoClassificationService::classify(RiscoInput::paraCnae(...))` → `ConsultaViabilidadeResult` readonly (snake_case toArray, versoes() de TODAS as regras). A degradação honesta (zona indisponível → viabilidade `pendente`) já vem do motor LOUOS — apenas PROPAGADA (evita duplicar a HU-044).
- DTOs `ConsultaViabilidadeInput`/`ConsultaViabilidadeResult` espelham RiscoResult/EnquadramentoResult/TerritoryResult.

### Comportamento honesto (analista-negocio) — confirmado no código
- SEM zona (bloqueada SEDUR): **risco** (municipal+sanitário+encaminhamento), **enquadramento Quadro 7** (área), **restrições ambientais/bairro/via** rodam 100% REAIS; o **veredito locacional fica `ResultadoViabilidade::Pendente`** ("Pendente de análise técnica", motivo "zona pendente SEDUR"). A UI NUNCA mostra Permitido/Não permitido sem zona.

### 3 entradas honestas
- **Endereço (HU-054)**: geocodifica real (Nominatim) → território → motores. Entregável.
- **CNAE (HU-056)**: risco real + (com área) enquadramento Quadro 7; sem território. Entregável.
- **Inscrição imobiliária (HU-055)**: BLOQUEADA na resolução do ponto (depende de lote/Cadastro — HU-033/HU-106 pendente SEDUR). Atrás do contrato `PropertyRegistryLookup` (interface + provider indisponível; binding trocado na Fase 13) — degrada com aviso "pendente SEDUR", sugere consulta por endereço, NUNCA inventa ponto. Teste com fake provando que a lógica roda quando a base chegar.

### Histórico (HU-060) — autenticado
- Tabela imutável `viability_queries` (input + snapshot `result` jsonb + `rules_versions` jsonb + user_id), gravada no controller SÓ quando autenticado (consulta anônima não entra em histórico pessoal, mas é auditada). Usuário vê só o próprio histórico (precedente "Minhas empresas"). O service permanece reutilizável (HU-141 simulação na solicitação, Fase 8).

### Pública x autenticada
- HU-054/056/057/058/059 → PÚBLICAS (sob /portal/*), com throttle parametrizado (HU-014, padrão Fase 3.1) e auditoria RN-002 (causer sistema quando anônimo, com IP/origem). HU-060 → AUTENTICADA.

### UI
- Portal do cidadão (light); reusa `MapaSection`/`MapImovel` (Fase 4). Resultado mostra: risco (badges municipal/sanitário + encaminhamento), enquadramento Quadro 7, restrições/bairro/via, e o veredito locacional (pendente com motivo, sem mentir). Histórico autenticado.

### Parametrização / deps
- Toggle `features.consulta_viabilidade` + throttle `seguranca.throttle.consulta_viabilidade.por_minuto`. ZERO dependência nova (tudo já existe).

## Critério de pronto (ROADMAP Fase 7)

1. Cidadão consulta por endereço, por inscrição (degradada/contrato) ou por CNAE, sem processo formal.
2. Simulação retorna enquadramento, risco e restrições com fundamentação legal — motores reais das Fases 5 e 6.
3. Usuário autenticado consulta o histórico das próprias consultas.

## Decomposição (sub-entregas → planos)

1. Fundação: contrato `PropertyRegistryLookup` (inscrição, bloqueado) + tabela `viability_queries` + DTOs `ConsultaViabilidadeInput/Result` + parâmetros (toggle/throttle).
2. `ConsultaViabilidadeService` (orquestra Geocoder→Território→LOUOS→Risco; propaga degradação; auditoria RN-002) + entrada por endereço (HU-054) e CNAE (HU-056).
3. Simulações (HU-057 enquadramento / HU-058 risco / HU-059 restrições) — views do mesmo resultado; inscrição (HU-055) bloqueada com contrato.
4. Histórico autenticado (HU-060) + persistência no controller.
5. UI do portal (consulta pública + mapa reusado + resultado honesto + histórico) + endpoints/rotas com throttle.
6. Fechamento: golden cases de consulta + comando/evidência real + verificação integral.

## Fora de escopo / bloqueado

- Veredito locacional permitido/não permitido (Quadro 10/zona — pendente SEDUR; degrada pendente).
- Resolução por inscrição imobiliária (lote/Cadastro — pendente SEDUR; contrato pronto).
- Processo formal de solicitação (Fase 8), fluxo expresso (Fase 9).
