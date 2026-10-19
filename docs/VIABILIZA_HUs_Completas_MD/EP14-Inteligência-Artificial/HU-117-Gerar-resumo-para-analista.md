# HU-117 — Gerar resumo para analista

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Produzir uma síntese do processo na ficha de análise, no mesmo pedido do card, como sugestão que não altera a ficha nem a decisão.

## História de Usuário
**Como** analista, **quero** um resumo do processo na ficha, **para** ler o caso mais rápido. O resumo não conclui a análise.

## Atores e permissões
- Ficha de análise (`AnalysisRecordController`), permissão de analisar processos da rota da ficha.
- Mesmo portão `features.ia_resumo` e provedor de texto.
- Há também ação que chama `processar` de novo (botão de gerar resumo).

## Fluxo evidenciado
1. A prop adiada `sugestoesIa` chama `ResumoProcessoService::processar` e, em seguida, a sugestão de parecer.
2. O job roda em `dispatchSync`: a ficha precisa do texto no mesmo request. Fila assíncrona deixava o card sem resposta.
3. `RunAiAgentJob` confere o portão e a idempotência por hash. Sugestão já equivalente não chama o provedor outra vez.
4. O resultado é `ai_suggestions` do tipo `resumo_processo`. A ficha finalizada não é reescrita por essa leitura.
5. Portão fechado: não chama o provedor e não simula resumo.

## Regras de negócio evidenciadas
- Sugestão. Não grava `ViabilityDecision` nem muda campos da ficha.
- Compartilha o interruptor com o resumo do cidadão; o evento de auditoria da função continua o do resumo do processo.

## Validações
- Sem provedor de texto a função não roda.
- Ficha sem conteúdo novo repetido não gera outra chamada idêntica.

## Critérios de aceite
- Abrir o card com a função ligada grava ou reutiliza o resumo e não conclui o processo.
- Com a função desligada, o card não exibe texto gerado na hora.
- Recarregar a ficha não empilha jobs assíncronos à espera.

## Divergências da redação anterior
O texto não distinguia sugestão de decisão nem o interruptor compartilhado com o resumo do cidadão.

## Evidências
- `app/Services/Ai/ResumoProcessoService.php`
- `app/Jobs/Ai/ResumoProcessoJob.php`
- `app/Ai/Agents/ResumoProcessoAgent.php`
- `app/Http/Controllers/Gestao/AnalysisRecordController.php`

## Não evidenciado
- Resumo que preencha o parecer oficial ou deferir o processo.
