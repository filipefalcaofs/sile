# AI-SPEC — Phase 14: Inteligência Artificial

> Contrato de design de IA gerado pelo fluxo `/gsd-ai-integration-phase`. Consumido por `gsd-planner` e `gsd-eval-auditor`.
> Trava seleção de framework, orientação de implementação e estratégia de avaliação antes do planejamento.
>
> **Decisões já fixadas com o time (2026-06-15):** framework `laravel/ai`; multi-provider OpenAI + Anthropic + Google; credenciais e aval de LGPD disponíveis (funções entram com IA real); execução decomposta em ondas (0 → 3), começando pela configuração multi-provider administrável.

---

## 1. System Classification

**System Type:** Hybrid — Extraction (OCR/classificação documental) + Content Generation (resumos, minuta de parecer, explicação) + RAG/Conversational (assistentes).

**Description:**
Camada de IA **assistiva** do SILE. Acelera a análise documental e a comunicação no licenciamento de viabilidade: lê e classifica documentos, aponta inconsistências, resume processos, sugere minuta de parecer ao analista e explica resultados ao cidadão, além de assistentes conversacionais. Usuários: analistas da SEDUR (retaguarda) e cidadãos/contadores (portal). "Bom" = **acelera sem decidir** — toda saída é sugestão revisável, com fonte citada; a decisão de viabilidade continua no motor de regras determinístico (Fases 5/6/9/10) e/ou no servidor humano.

**Critical Failure Modes:**
1. **IA decidir a viabilidade** ou ter o parecer tratado como vinculante (viola HU-118 RN-004/RN-005 — a IA é apoio, sempre revisável).
2. **Vazamento/exposição indevida de dado pessoal** ao provedor externo (LGPD) — enviar PII desnecessária ou sem base legal.
3. **Alucinação de fundamentação legal** (inventar artigo, enquadramento LOUOS ou classificação de risco) — a base legal vem do motor/dados reais, não do modelo.
4. **Erro silencioso de OCR/extração** aceito como verdade (documento ilegível tratado como legível) sem sinal de confiança nem escalonamento.
5. **Custo/latência descontrolados** (loops de tool, ausência de budget/timeout, processamento de documentos enormes).

---

## 1b. Domain Context

**Industry Vertical:** Administração pública municipal — licenciamento urbano (viabilidade locacional de atividades econômicas), SEDUR/Salvador.

**User Population:** Analistas técnicos da SEDUR (retaguarda, decisão administrativa); cidadãos, contadores e procuradores (portal). Servidores são responsáveis pelo ato; cidadãos consomem orientação.

**Stakes Level:** High — a saída alimenta decisão administrativa com efeito jurídico (dever de motivação do ato) e trata dados pessoais de órgão público (LGPD).

**Output Consequence:** O resumo/parecer/inconsistências entram na ficha do analista; a explicação e o assistente vão ao cidadão. Se a saída for tratada como verdade sem revisão, pode enviesar a decisão ou desinformar o cidadão. Por isso toda saída é **sugestão marcada e auditada**, nunca decisão.

### What Domain Experts Evaluate Against

| Dimensão | Bom (especialista aceita) | Ruim (especialista sinaliza) | Stakes | Fonte |
|---|---|---|---|---|
| Fidelidade da extração documental | Texto/campos batem com o documento; ilegível é marcado | Inventa dado ausente; aceita ilegível como válido | Critical | Analista SEDUR / ground truth |
| Fundamentação legal | Cita LOUOS/Decreto 32.636/2020 corretos e existentes | Inventa artigo/quadro ou cita norma inaplicável | Critical | Analista sênior + base legal real |
| Aderência ao motor | Sugestão coerente com enquadramento/risco do motor; divergência justificada | Contradiz o motor sem motivo registrado | High | Motor (Fases 5/6) |
| Não-decisão | Apresenta como sugestão revisável; escala quando incerto | "Decide", afirma desfecho como final | Critical | HU-118 RN-004/005 |
| Linguagem cidadã | Clara, correta, sem juridiquês desnecessário; acessível | Confusa, técnica demais, ou promete o que não pode | Medium | Atendimento SEDUR / eMAG |
| Minimização de dados | Usa só o necessário ao contexto | Expõe CPF/dados sensíveis sem necessidade | Critical | DPO / LGPD |

### Known Failure Modes in This Domain

- **Alucinação de base legal** (artigo/quadro inexistente) — especialmente grave em ato administrativo.
- **OCR de documento de baixa qualidade** (foto de fachada/contrato escaneado torto) lido com erro e aceito.
- **Viés de confirmação**: a IA "concorda" com o motor mesmo quando deveria sinalizar divergência (perde valor como segunda opinião).
- **Exposição de PII** ao provedor externo além do necessário.
- **Inconsistência falsa-positiva**: apontar divergência documento × declaração que não existe, gerando retrabalho.

### Regulatory / Compliance Context

- **LGPD (Lei nº 13.709/2018)**: tratamento de dados pessoais por provedor externo exige base legal e minimização. **Aval de LGPD obtido** (decisão registrada nesta sessão); manter DPA com fornecedores e registro de tratamento. Painel LGPD (HU-102, Fase 12) monitora acessos a PII.
- **Dever de motivação do ato administrativo**: a decisão precisa de fundamentação rastreável — reforça que a IA não decide; o motor/humano fundamenta.
- **Acessibilidade (eMAG/WCAG)** para saídas ao cidadão (obrigação de órgão público).
- **Trilha de auditoria RN-002**: toda chamada de IA é auditada (provider, modelo, versão do prompt, custo, resultado).

### Domain Expert Roles for Evaluation

| Role | Responsibility |
|------|---------------|
| Analista sênior SEDUR | Rotular dataset de referência, calibrar rubrica de parecer/resumo, amostragem de produção |
| DPO / jurídico | Validar recorte LGPD, base legal, minimização de PII |
| Gestor SEDUR | Amostragem de produção, aceitar/descartar com base na divergência IA × decisão |

---

## 2. Framework Decision

**Selected Framework:** Laravel AI SDK (`laravel/ai`) — oficial, first-party.

**Version:** A fixar no `composer require laravel/ai` (release estável para Laravel 13; o projeto está em `laravel/framework ^13.8`, PHP 8.5). Pin exato no lockfile na Onda 0.

**Rationale:**
- **Laravel way / first-party** — respeita a constraint do projeto ("não trocar a stack") e minimiza superfície de dependência (uma dep oficial em vez de SDKs por provedor).
- **Multi-provider nativo** (OpenAI, Anthropic, Gemini, e outros) com troca por configuração e fallback — atende o requisito multi-provider das HUs do EP14.
- **Structured Output via `JsonSchema`** — essencial para classificação documental (HU-113), detecção de inconsistências (HU-115) e minuta de parecer estruturada (HU-118), eliminando parsing frágil.
- **`pgvector` nativo no PostgreSQL** (já usamos PostgreSQL/PostGIS) + `SimilaritySearch`/embeddings — base de RAG dos assistentes (HU-120/121) e dos precedentes.
- **Visão multimodal** (`Files\Image`) — cobre OCR (HU-112) sem serviço dedicado na 1ª onda.
- **Helpers de teste first-class** — casa com o TDD estrito do projeto (golden cases).

**Alternatives Considered:**

| Framework | Ruled Out Because |
|-----------|------------------|
| Prism PHP | Base comunitária madura (o SDK oficial usa Prism por baixo); preterido por não ser first-party para Laravel 13. Reavaliar como fallback se o SDK oficial mostrar imaturidade. |
| HTTP direto OpenAI-compatible (padrão SIGVISA) | Controle total e zero dep nova, mas reimplementa structured output, fallback multi-provider e vector store à mão — mais código e mais risco. |
| Neuron AI | Framework agêntico mais completo do PHP; reservado para a Onda 3 (assistentes) se a orquestração do SDK não bastar. Overkill para extração/geração. |

**Vendor Lock-In Accepted:** Partial — a abstração multi-provider reduz o lock a um provedor específico; a config administrável (Onda 0) permite trocar provider/modelo sem deploy. O lock é ao próprio SDK (mitigado por ser first-party Laravel).

---

## 3. Framework Quick Reference

> Extraído da documentação oficial do Laravel AI SDK (`laravel.com/docs/13.x/ai-sdk`) — destilado para este caso de uso.

### Installation
```bash
composer require laravel/ai
php artisan vendor:publish --tag=ai-migrations   # tabelas de conversation/vector quando usadas
php artisan migrate
# pgvector (PostgreSQL): habilitar a extensão em migration — Schema::ensureVectorExtensionExists();
```

### Core Imports
```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Laravel\Ai\Concerns\RemembersConversations;   // assistentes (HU-120/121)
use Laravel\Ai\Files;                              // Files\Image::fromStorage() — OCR/visão
use Laravel\Ai\Embeddings;                         // embeddings p/ RAG
use Illuminate\Contracts\JsonSchema\JsonSchema;    // schema de saída estruturada
```

### Entry Point Pattern
```php
// Um Agent por função de IA — sempre saída estruturada e revisável.
class ClassificacaoDocumentoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'Classifique o documento anexado em uma das categorias e indique se está legível. Não invente dados ausentes.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'categoria' => $schema->string()->enum(['fachada', 'contrato_locacao', 'concessao_uso', 'outro'])->required(),
            'legivel' => $schema->boolean()->required(),
            'confianca' => $schema->string()->enum(['baixa', 'media', 'alta'])->required(),
        ];
    }
}

$res = (new ClassificacaoDocumentoAgent)->prompt(
    'Classifique este documento.',
    attachments: [Files\Image::fromStorage($document->path)],
);
$res['categoria']; $res['legivel']; $res['confianca'];
```

### Key Abstractions
| Concept | What It Is | When You Use It |
|---------|-----------|-----------------|
| `Agent` + `Promptable` | Classe de função de IA com `instructions()`/`prompt()` | Toda função (resumo, parecer, classificação...) |
| `HasStructuredOutput` + `JsonSchema` | Saída validada pelo schema no provedor | Classificação, inconsistências, parecer estruturado |
| `Files\Image` (attachments) | Anexo multimodal (visão) | OCR/leitura de documento (HU-112/113/114) |
| `Embeddings` + coluna `vector` (pgvector) | Vetorização + busca por similaridade | RAG de precedentes/processos (assistentes) |
| `SimilaritySearch` / `FileSearch` (tools) | Ferramentas de busca para agentes | Assistente do analista/cidadão (HU-120/121) |
| `RemembersConversations` | Histórico de conversa persistido | Assistentes conversacionais |

### Common Pitfalls
1. **Config estática × runtime:** o SDK lê credenciais de `config/ai.php`/`.env`. A config administrável (banco) precisa de uma **ponte** que aplica `config(['ai.providers...'])` em runtime — sem isso a tela vira fachada.
2. **Custo de visão/contexto:** documentos grandes e imagens custam; aplicar budget, roteamento de modelo (mini p/ OCR/classificação) e cache.
3. **Schema obrigatório:** sem `HasStructuredOutput` a saída é texto livre — parsing frágil. Usar schema sempre que a saída for consumida por código.
4. **`pgvector`:** exige a extensão habilitada (temos PostgreSQL; habilitar via `ensureVectorExtensionExists()`), separada da PostGIS.
5. **`RemembersConversations` + `messages()`:** não definir ambos — o método tem precedência e quebra o histórico automático.

### Recommended Project Structure
```
app/
├── Ai/
│   └── Agents/                 # ResumoProcessoAgent, SugestaoParecerAgent, ...
├── Services/Ai/                # AiConfigResolver (ponte runtime), orquestração por função
├── Models/AiConfiguration.php  # config multi-provider administrável (Onda 0)
config/ai.php                   # defaults do SDK (sobrescritos em runtime pela config do banco)
```

---

## 4. Implementation Guidance

**Model Configuration:**
Config multi-provider **administrável** (Onda 0): model `AiConfiguration` (provider, model, `api_key` cast `encrypted`, base_url, temperature, max_tokens, timeout, active, is_default por capability). Ponte runtime `AiConfigServiceProvider`/`AiConfigResolver` aplica os valores do banco em `config('ai.*')` no boot (e override por request quando necessário). **Toggle por função** (`features.ia_ocr`, `features.ia_resumo`, `features.ia_parecer`, ...) — desligado degrada controlado (a função some/avisa), nunca falha silenciosa. Roteamento de modelo por custo (mini p/ extração; maior p/ parecer).

**Core Pattern:**
Um `Agent` por função, todos com **saída estruturada** e atrás de **toggle + contrato**. Chamadas executadas em **fila** (jobs — infra da Fase 3.1) por custo/latência; resultado persistido e **auditado (RN-002)** com provider/modelo/versão do prompt/tokens/custo. A saída sempre alimenta a ficha/portal como **sugestão marcada** (nunca decisão).

**Tool Use:**
`SimilaritySearch` sobre embeddings de processos/precedentes para os assistentes (RAG); `FileSearch` para documentos. Tools só de leitura do contexto real do processo.

**State Management:**
`RemembersConversations` para assistentes; embeddings em coluna `vector` (pgvector) para RAG; resultados de IA persistidos em tabela própria, auditados e versionados pelo prompt.

**Context Window Strategy:**
Chunking de documentos longos; resumo hierárquico; budget de tokens por função; minimização de PII no prompt (LGPD).

---

## 4b. AI Systems Best Practices

> Adaptado para PHP/Laravel — o equivalente ao "Pydantic" aqui é o `JsonSchema` do SDK.

### Structured Outputs com JsonSchema (equivalente PHP do Pydantic)
```php
// Detecção de inconsistências (HU-115) — saída validada pelo provedor.
public function schema(JsonSchema $schema): array
{
    return [
        'inconsistencias' => $schema->array(fn ($s) => $s->object(fn ($o) => [
            'campo' => $o->string()->required(),
            'declarado' => $o->string()->required(),
            'documento' => $o->string()->required(),
            'severidade' => $o->string()->enum(['baixa', 'media', 'alta'])->required(),
        ]))->required(),
        'fonte' => $schema->string()->required(), // documento citado — sem fonte, não vale
    ];
}
```
Em falha de validação ou baixa confiança: **degrada para análise humana** (FA-01/FA-02 das HUs), nunca aceita saída malformada.

### Async-First Design
Toda chamada de IA roda em **job na fila** (não bloquear request HTTP) — reusa a infra assíncrona da Fase 3.1 (retry/backoff/timeout parametrizados). Streaming apenas nos assistentes (UX conversacional).

### Prompt Engineering Discipline
`instructions()` (system) separado do conteúdo do usuário; few-shot com casos reais validados pela SEDUR; **versão do prompt registrada** na auditoria; **minimização de PII** (só o necessário ao contexto vai ao provedor).

### Context Window Management
RAG com chunking para precedentes; resumo para documentos longos; budget por função.

### Cost and Latency Budget
Estimativa de custo por chamada; **cache de embeddings**; roteamento de modelo por subtarefa (mini p/ OCR/classificação, modelo maior p/ parecer); toggle por função; budget mensal com alerta.

---

## 5. Evaluation Strategy

### Dimensions

| Dimension | Rubric (Pass/Fail ou 1-5) | Measurement Approach | Priority |
|-----------|--------------------------|---------------------|----------|
| Fidelidade da extração (OCR/HU-112) | Pass/Fail vs ground truth | Code (golden) + Human | Critical |
| Acerto da classificação documental (HU-113) | F1 vs rótulos | Code (golden) | High |
| Detecção de ilegibilidade (HU-114) | Pass/Fail | Code + Human | High |
| Precisão/recall de inconsistências (HU-115) | vs casos rotulados | Code + Human | High |
| Qualidade do resumo (HU-116/117) | 1-5 (fidelidade, completude, sem alucinação) | LLM judge calibrado + Human | High |
| Qualidade da minuta de parecer (HU-118) | 1-5 (fundamentação correta, não-decisão, aderência LOUOS) | Human (analista sênior) | Critical |
| Clareza da explicação ao cidadão (HU-119) | 1-5 (clara, correta, acessível) | LLM judge + Human | Medium |
| Segurança do assistente (HU-120/121) | Pass/Fail (não inventa, não vaza PII, escala quando incerto) | Human + Code | Critical |

### Eval Tooling

**Primary Tool:** Golden cases em **PHPUnit** (datasets rotulados pela SEDUR), no mesmo padrão de regressão de domínio das Fases 5/6; **LLM-as-judge** para dimensões subjetivas (resumo/parecer/explicação) com calibração humana periódica.

**Setup:**
```bash
# Golden cases por função, com #[DataProvider] (entrada → resultado/rubrica esperada).
# Provider em modo fake/gravado nos testes automatizados (sem custo/rede no CI).
```

**CI/CD Integration:**
```bash
php artisan test --group ia        # roda os golden cases de IA no CI a cada mudança de prompt/modelo
```

### Reference Dataset

**Size:** ≥ 20 casos reais **anonimizados** por função, para começar (documentos de fachada/contrato; processos reais de diferentes desfechos).

**Composition:** caminhos críticos (documento legível típico), casos de borda (ilegível, multipágina, documento errado), e modos de falha conhecidos (inconsistência real vs falsa).

**Labeling:** analista sênior SEDUR (rubrica de parecer/resumo) + DPO (recorte LGPD). Rótulos versionados junto aos golden cases.

---

## 6. Guardrails

### Online (Real-Time)

| Guardrail | Trigger | Intervention |
|-----------|---------|--------------|
| Minimização/LGPD | Antes de enviar ao provedor | Strip/Block de PII desnecessária |
| Não-vinculante | Toda saída de IA | Flag na UI ("sugestão — revise") |
| Limiar de confiança (OCR/classificação) | Confiança < limiar parametrizado | Escala para humano |
| Citação de fonte obrigatória (parecer/explicação/inconsistência) | Saída sem fonte rastreável | Block/Flag |
| Budget/timeout por chamada | Custo/tempo acima do teto | Block |

### Offline (Flywheel)

| Metric | Sampling Strategy | Action on Degradation |
|--------|------------------|----------------------|
| Divergência IA × decisão do analista | Amostra de processos decididos (reusa conceito HU-145) | Recalibrar prompt/modelo; toggle off se cair |
| Taxa de aceitação da sugestão | Amostra por função | Ajustar instructions/few-shot |
| Falsos positivos de inconsistência | Revisão humana periódica | Ajustar schema/prompt |

---

## 7. Production Monitoring

**Tracing Tool:** Baseline = **auditoria interna RN-002 + logs estruturados** (provider, modelo, versão do prompt, tokens, custo, latência, resultado) — o projeto já tem observabilidade mínima e trilha. *Override consciente do default Arize Phoenix:* avaliar Phoenix/Langfuse como tracing dedicado se o volume justificar; não é pré-requisito para a Onda 0.

**Key Metrics to Track:**
- Taxa de aceitação da sugestão pelo analista (por função).
- Divergência IA × decisão final.
- Custo por processo e custo mensal por provedor.
- Latência p95 por função.
- Taxa de escalonamento por baixa confiança.

**Alert Thresholds:** custo acima do budget mensal; latência p95 alta; queda na aceitação; taxa de erro do provedor; uso de PII fora do esperado.

**Smart Sampling Strategy:** priorizar para revisão humana as saídas de **baixa confiança**, **alta divergência IA × motor/analista** e **casos de alto risco** — alimenta o flywheel offline.

---

## Decomposição em ondas (execução)

| Onda | Escopo | HUs | Gate |
|---|---|---|---|
| **0 — Fundação** | Config multi-provider administrável (tela), `AiConfiguration`, ponte runtime, contratos, teste de conexão real, toggles | HU-014 (aplicada à IA) | Não depende de credencial p/ construir |
| **1 — Documentos** | OCR, classificação, ilegibilidade, inconsistências | HU-112, 113, 114, 115 | Visão multimodal; LGPD |
| **2 — Síntese** | Resumo da solicitação, resumo p/ analista, sugerir parecer, explicar ao cidadão | HU-116, 117, 118, 119 | Structured output; não-decisão |
| **3 — Assistentes** | Assistente do cidadão e do analista | HU-120, 121 | RAG (pgvector) + RemembersConversations |

---

## Checklist

- [x] System type classified (Hybrid: Extraction + Generation + RAG)
- [x] Critical failure modes identified (≥ 3)
- [x] Domain context researched (vertical, stakes, expert criteria, failure modes)
- [x] Regulatory/compliance context identified (LGPD, motivação do ato, eMAG, RN-002)
- [x] Domain expert roles defined (analista sênior, DPO, gestor)
- [x] Framework selected with rationale documented (laravel/ai)
- [x] Alternatives considered and ruled out (Prism, HTTP direto, Neuron AI)
- [x] Framework quick reference written (install, imports, pattern, pitfalls)
- [x] AI systems best practices written (JsonSchema, async/fila, prompt discipline, context, custo)
- [x] Evaluation dimensions grounded in domain rubric ingredients
- [x] Each eval dimension has a concrete rubric
- [x] Eval tooling selected (PHPUnit golden cases + LLM-judge; Phoenix opcional notado)
- [x] Reference dataset spec written (≥ 20 por função, composição + rotulação)
- [x] CI/CD eval integration specified (`php artisan test --group ia`)
- [x] Online guardrails defined (LGPD, não-vinculante, confiança, citação, budget)
- [x] Production monitoring configured (auditoria RN-002 + métricas + sampling)

---
*AI-SPEC criado em 2026-06-15. Próximo passo: `/gsd-plan-phase 14` (a Onda 0 — config multi-provider — é a primeira fatia a planejar/executar). Bloqueios externos: nenhum declarado (credenciais + LGPD confirmados); `pgvector` a habilitar na Onda 3.*
