# S8 — Exclusão de atividade expressa e deferida

Origem: Lisa 23/10 16:29.

## Regra informada
Todo processo de exclusão de atividade é expresso e deferido.

## Como está
O deferimento automático da exclusão existe (`FluxoExpressoService::deferirExclusao`), mas só dispara quando o portal marca a intenção "excluir" ou quando a consulta à SEFAZ (desligada, sem provedor) compara o pedido. Processo da JUCEB (evento 244) sem intenção é enquadrado inteiro.

## Mudança
Sem intenção por CNAE e sem a comparação da SEFAZ, o processo de alteração de atividade (evento 244) é comparado com o último processo deferido da mesma empresa: se todos os CNAEs pedidos já estavam nele e algum saiu, é exclusão e vai para `deferirExclusao` com os CNAEs que saíram. Chave `viabiliza.expresso.exclusao_pelo_processo_anterior` (padrão ligada).
