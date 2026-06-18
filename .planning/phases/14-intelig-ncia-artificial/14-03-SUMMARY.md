---
phase: 14-intelig-ncia-artificial
plan: 03
subsystem: ai+backend+ui
tags: [hu-116, hu-117, hu-118, hu-119, sintese, nao-decisao, acessibilidade, anti-fachada]
requirements: [HU-116, HU-117, HU-118, HU-119]
verification: passed
guardiao: APROVADO
status: complete
---

# 14-03 — Onda 2 da Fase 14 (Síntese)

## O que foi entregue (geração de texto, como SUGESTÃO revisável)
Reusa a fundação da Onda 1 (`RunAiAgentJob`/`AiSuggestion`/`AiFeatureGate`/guardrails/auditoria). `capability='text'`, sem anexos.

- **HU-117 Resumo do processo** (ficha do analista): `ResumoProcessoAgent`/Job/Service (toggle `ia_resumo`); seção no topo da ficha via `Inertia::optional` (read-only). Sintetiza motor/per_cnae/inconsistências/pendências; não afirma desfecho.
- **HU-118 Sugerir minuta de parecer** (ficha) — **NÃO-DECISÃO (Failure Mode #1)**: `SugestaoParecerAgent`/Job/Service (toggle `ia_parecer`); schema `{minuta,fundamentacao,confianca,fonte}`. Service NÃO despacha sem `engine_snapshot` (sem motor não há fundamentação real); rota `POST ficha/sugerir-parecer` recusa em revisão finalizada (RN-003); botão "Sugerir minuta" + poll + "Aplicar ao parecer" (copia para o textarea EDITÁVEL client-side). NUNCA grava em `AnalysisRecord`/`ViabilityDecision` (provado: `ViabilityDecision::count()===0` + parecer null).
- **HU-116 Resumo da solicitação** (portal, pré-protocolo): `ResumoSolicitacaoAgent`/Job/Service (toggle `ia_resumo`); card no wizard (`etapa-revisao.tsx`) via `WhenVisible`; input minimizado; nunca afirma desfecho.
- **HU-119 Explicar ao cidadão** (portal/protocolo) — **acessível**: `ExplicacaoCidadaoAgent`/Job/Service (toggle `ia_explicacao`); só despacha com `ViabilityDecision`; fiel à decisão/HU-099; card com `aria-live`, status por texto (não só cor), skeleton `sr-only`, ressalva "o documento oficial prevalece".

## Verificação fresca (guardião APROVADO)
- `--group ia` 32/32 (150 asserções); filtros 19/19; suíte global `--exclude-group postgis` **1554/1554** (sem regressão); tsc/build/pint verdes.
- Auditoria RN-002 sem api_key/PII; `personalData` nos 4 jobs; Fases 8/10 read-only para IA.
- Staging seletivo: commits tocaram só arquivos de IA; trabalho de e-mail/nav/Settings do usuário e `.cursor/` preservados.

## Commits
`ec0678c`/`72d556c` (HU-117) · `306902e`/`c396e8e` (HU-118) · `65ce029`/`59f5ea5` (HU-117 ficha) · `2c2be30` (HU-116) · `ac4393a` (HU-119).

## Pendências honestas (gates de validação/calibração — não bloqueiam)
- Smoke real de geração de TEXTO contra provedor de homologação (evidência fresca de chamada real) — a integração já foi validada na fundação (Onda 0/1); falta o smoke específico das funções de síntese.
- Datasets/rubrica SEDUR para calibração de resumo/parecer/explicação.
- `ia_resumo` é toggle compartilhado HU-116/117 (granularidade — decisão de parametrização SEDUR).
