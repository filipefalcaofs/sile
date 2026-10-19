# HU-116 — Gerar resumo da solicitação

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Gerar um resumo de conferência dos dados declarados, antes do protocolo, como sugestão que não afirma desfecho.

## História de Usuário
**Como** requerente, **quero** ler um resumo do que declarei na revisão da solicitação, **para** conferir o preenchimento. O resumo não protocola nem decide viabilidade.

## Atores e permissões
- Portal autenticado, na montagem da solicitação (`SolicitacaoController`).
- Portão `features.ia_resumo` (compartilhado com o resumo do processo) e provedor de texto ativo.
- A prop é adiada: o card pede `sugestoesResumo` sob demanda.

## Fluxo evidenciado
1. Ao resolver a prop, `ResumoSolicitacaoService::processar` roda.
2. Portão fechado ou sem provedor: não despacha, não chama o modelo e não simula texto. O card lê o que já existir no ledger.
3. Portão aberto: o job gera sugestão do tipo `resumo_solicitacao`. Entrada repetida não reprocessa (idempotência por hash).
4. O texto é conferência dos dados declarados. Não grava decisão.

## Regras de negócio evidenciadas
- Não é afirmação de deferimento ou indeferimento.
- O mesmo interruptor liga ou desliga este resumo e o resumo do analista.

## Validações
- Sem provedor de texto, `AiFeatureGate` devolve o motivo de indisponibilidade e a função não roda.
- O job confere o portão de novo no handle.

## Critérios de aceite
- Na etapa de revisão, o card pode pedir o resumo sem carregar isso no primeiro acesso.
- Com a função desligada, a tela não mostra resumo inventado.
- O resumo gerado não altera o status da solicitação.

## Divergências da redação anterior
O texto genérico não dizia que a função é sugestão, compartilhada com o resumo do analista, e que fica inerte sem provedor.

## Evidências
- `app/Services/Ai/ResumoSolicitacaoService.php`
- `app/Jobs/Ai/ResumoSolicitacaoJob.php`
- `app/Ai/Agents/ResumoSolicitacaoAgent.php`
- `app/Http/Controllers/Portal/SolicitacaoController.php`

## Não evidenciado
- Resumo que aprove o protocolo ou substitua a declaração do requerente.
