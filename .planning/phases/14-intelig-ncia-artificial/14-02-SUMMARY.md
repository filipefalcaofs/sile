---
phase: 14-intelig-ncia-artificial
plan: 02
subsystem: ai+backend+ui
tags: [hu-112, hu-113, hu-114, hu-115, laravel-ai, structured-output, fila, auditoria, anti-fachada, guardrails, lgpd]
requirements: [HU-112, HU-113, HU-114, HU-115]
verification: passed
guardiao: APROVADO
status: complete
---

# 14-02 — Onda 1 da Fase 14 (Documentos) + fundação de execução de IA

## O que foi entregue
Funções documentais de IA como **sugestão revisável** (nunca decisão — AI-SPEC Failure Mode #1), em fila, atrás de toggle, auditadas, testadas com o fake do SDK.

- **Fundação de execução (compartilhada pelas Ondas 1-3):** `AiSuggestion` (model/migration/factory + enums `AiSuggestionType`/`AiSuggestionStatus` — status NUNCA `decidida`), `AiFeatureGate` (toggle `features.ia_*` + config ativa), `AiCallAuditor` (RN-002, logName `ia`), `AiCostEstimator` (custo null sem preço — nunca inventado), bloco `config('sile.ai.*')` (job/limiar/preço).
- **`RunAiAgentJob` (Job base):** re-check do toggle no handle (OFF ⇒ no-op AUDITADO `desativado`, sem chamar o provedor), idempotência por `input_hash`, guardrails (confiança < `ai.limiar_confianca` ⇒ `escalada_humano`; `fonte` obrigatória; hook `shouldEscalate` p/ ilegível), persiste `AiSuggestion` só no sucesso, auditoria RN-002 sem segredo; `failed()` audita `falha` sem criar sugestão.
- **HU-112 OCR + HU-114 ilegibilidade:** `LeituraDocumentoAgent` (visão multimodal, schema texto/legivel/confianca/motivo/fonte) + Job + `LeituraDocumentoService`. Ilegível/baixa confiança ⇒ escala.
- **HU-113 classificação:** `ClassificacaoDocumentoAgent` (categoria/confianca/compativel/fonte) + Job + Service; confronta com `DocumentRequirement` — alerta, nunca bloqueia o protocolo (RN-005).
- **HU-115 inconsistências:** `InconsistenciasAgent` (array de `{campo,declarado,documento,severidade}` + `fonte` obrigatória) + Job + Service; minimiza PII; SINALIZA (não decide; alimenta a ficha e, como sinal, a malha fina). geo/Receita (HU-037/105) degradam honesto (bloqueadas SEDUR/Fase 13).
- **Integração read-only na ficha (Fase 10):** prop `sugestoesIa` via `Inertia::optional` em `AnalysisRecordController@show` + card "Alertas de IA (sugestão — revise)" (`WhenVisible`+skeleton) em `gestao/ficha-analise/show.tsx`. NÃO toca `AnalysisRecord` (RN-003).

## Validação anti-fachada (gate de homologação — REAL)
Com o provedor real cadastrado (SimplificaIA / openai / gpt-5.4-mini), uma chamada de geração **REAL** via a ponte runtime retornou "Integração de IA do SILE funcionando." (tokens 37/14). A integração tela→`AiConfiguration`→ponte→SDK `laravel/ai`→OpenAI funciona de verdade. As 3 funções da Onda 1 usam o mesmo caminho (Agent→prompt); o real call validado foi de texto (visão usa o mesmo caminho).

## Verificação fresca (guardião APROVADO)
- `--group ia` 13/13 (64 asserções); filtro Inconsistencias/RunAiAgentJob/Leitura/Classificacao 17/17.
- suíte global `--exclude-group postgis` **1535/1535** (sem regressão); `tsc`/`build` exit 0; `composer audit` limpo.
- Staging seletivo: os 8 commits tocaram só arquivos de IA; trabalho do usuário (e-mail/nav) e `.cursor/` preservados.

## Commits
`7f18841` (fundação) · `873be76` (Job base) · `c6cc825` (OCR/ilegibilidade) · `1f6f536` (classificação) · `1cbd9a1`/`6d2a187` (inconsistências) · `fd24760`/`0b99a63` (card na ficha).

## Decisões / desvios
- `RunAiAgentJob` ganhou hook `shouldEscalate(array): bool` (default false, retrocompatível) para a HU-114 escalar quando `legivel=false` sem confiar no auto-relato de confiança.
- Schema array-de-objeto do SDK suportado nativamente (`$schema->array()->items($schema->object([...]))`).
- Provider/model da sugestão vêm da `AiConfiguration` ativa (a resposta fake não traz meta).

## Pendências honestas (não bloqueiam a Onda 1 — gates de validação/calibração)
- **Smoke de VISÃO real** (OCR/classificação/inconsistências) com documento/foto reais contra o provedor — evidência de chamada `vision` registrada.
- **Datasets rotulados SEDUR** (analista sênior + DPO) p/ golden cases/calibração + DPA/base legal LGPD.
- **geo/Receita (HU-037/105)** bloqueadas SEDUR/Fase 13 (a inconsistência degrada honesto).
- **Agrupamento dos toggles `ia_*`** na UI (ergonomia) — `ia_resumo` cobre 116/117; `ia_assistente` cobre 120/121.
