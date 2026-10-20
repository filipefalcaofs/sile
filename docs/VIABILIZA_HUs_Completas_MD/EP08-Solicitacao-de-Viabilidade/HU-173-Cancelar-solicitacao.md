# HU-173 — Cancelar solicitação

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Permitir que o requerente cancele a própria solicitação, com motivo, enquanto o status estiver entre os estados canceláveis, registrando a transição e bloqueando o restante com aviso auditado.

## História de Usuário
**Como** requerente dono da solicitação,  
**quero** cancelá-la informando o motivo,  
**para** interromper um pedido que não deve seguir.

## Atores e permissões
- Dono efetivo e status rascunho ou protocolada (`ViabilityRequestPolicy::cancel`).
- Rota `DELETE portal/solicitacoes/{solicitacao}`, autenticada, sem o middleware `portal.solicitacao`.
- A lista só marca `cancelable` quando o status está em `solicitacao.cancelamento.estados_cancelaveis`.

## Fluxo evidenciado
1. O pedido exige `reason`.
2. O serviço lê os estados canceláveis do parâmetro (padrão `rascunho` e `protocolada`).
3. Fora dessa lista, audita `cancelamento-bloqueado` com resultado `bloqueado` e lança `CancelamentoNaoPermitidoException`. O controller devolve flash de erro e não altera o status.
4. Dentro da lista, em uma transação, grava `cancelled_at`, `cancelled_reason` e `cancelled_by_user_id` e transiciona para `cancelada`, com o motivo e o rótulo público "Cancelada a pedido do requerente".
5. A máquina de estados só aceita cancelar a partir de rascunho ou protocolada. A policy repete esse limite, mesmo que o parâmetro seja alterado para outro valor: status fora de rascunho e protocolada não passa da policy.

## Regras de negócio evidenciadas
- O motivo é obrigatório e vai para a transição.
- O padrão administrável é rascunho e protocolada.
- Cancelamento fora do estado permitido não muda a solicitação e fica auditado.
- Status decidido (deferida, indeferida) não é cancelável pela policy nem pela transição de saída dessas situações para `cancelada`.

## Validações
- `reason` obrigatório, texto, no máximo 1000 caracteres.
- Mensagens de motivo obrigatório e de tamanho máximo.

## Critérios de aceite
- Dado o dono de um rascunho ou de uma solicitação protocolada, com motivo preenchido, quando cancela, então o status fica `cancelada`, o motivo e o autor ficam gravados e a lista confirma o cancelamento.
- Dado motivo vazio ou com mais de 1000 caracteres, quando envia, então a validação recusa.
- Dado status fora dos estados canceláveis, quando o serviço é chamado, então nada é cancelado, a auditoria registra bloqueio e o usuário recebe o erro.
- Dado outro usuário, ou status que a policy não considera cancelável, quando chama a rota, então a autorização nega antes do serviço.

## Divergências da redação anterior
Não há cancelamento pelo analista neste controller, nem estorno de DAM. O requerente cancela rascunho ou protocolada, com motivo. A policy não acompanha sozinha uma ampliação do parâmetro para outros status: ela continua restrita a rascunho e protocolada.

## Evidências
- `App\Http\Controllers\Portal\CancelamentoSolicitacaoController`
- `App\Services\Solicitacao\CancelarSolicitacaoService`
- `App\Http\Requests\Portal\CancelarSolicitacaoRequest`
- `App\Policies\ViabilityRequestPolicy::cancel`
- `App\Services\Solicitacao\ViabilityRequestStateMachine`
- Parâmetro `solicitacao.cancelamento.estados_cancelaveis`

## Não evidenciado
- Cancelamento após deferimento ou indeferimento por este fluxo.
- Cancelamento sem motivo.
- Efeito financeiro, baixa de DAM ou comunicação à Junta neste serviço.
