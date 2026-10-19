# HU-065 — Informar CNAEs complementares

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Permitir zero ou mais CNAEs complementares ativos no rascunho, até o limite parametrizado, sem repetir a atividade principal.

## História de Usuário
**Como** requerente dono do rascunho,  
**quero** informar atividades complementares,  
**para** que a simulação e a decisão considerem o conjunto de CNAEs do pedido.

## Atores e permissões
- Dono efetivo, status rascunho.
- Mesma rota da atividade principal: `PUT portal/solicitacoes/{solicitacao}/atividades`.

## Fluxo evidenciado
1. O campo `complementares` é uma lista opcional de ids.
2. O limite lido na validação e exibido no wizard é `solicitacao.cnaes_complementares.max` (padrão 99).
3. Cada complementar entra no mesmo `sync` do principal, com `is_primary` falso e a mesma regra de intenção da HU-064.
4. A lista substitui o conjunto anterior. Complementar omitido deixa de estar no pedido.
5. A simulação é invalidada e a auditoria registra os códigos complementares resultantes.

## Regras de negócio evidenciadas
- Complementares são opcionais.
- Cada um precisa ser CNAE ativo, inteiro e sem duplicata na lista.
- A quantidade não pode passar do parâmetro.
- A atividade principal não pode aparecer na lista de complementares.
- As restrições de escritório virtual da HU-064 valem para o conjunto principal mais complementares.

## Validações
- `complementares`: nulo ou array, tamanho máximo do parâmetro.
- Cada item: inteiro, distinto, existente e ativo.
- Mensagens de limite excedido, CNAE inativo ou inexistente, duplicata e principal repetida entre os complementares.

## Critérios de aceite
- Dado um rascunho com atividade principal ativa, quando o dono envia complementares ativos dentro do limite e diferentes da principal, então o pivot guarda um principal e os demais como secundários.
- Dado lista vazia ou omitida, quando salva, então permanece só a principal enviada.
- Dado quantidade acima do máximo, CNAE inativo, id repetido ou a principal dentro dos complementares, quando envia, então a validação recusa e o pivot não muda.

## Divergências da redação anterior
Não há quantidade fixa escrita no código além do parâmetro, cujo padrão é 99. Complementar não é obrigatório. Não existe tela separada da atividade principal.

## Evidências
- `App\Http\Requests\Portal\UpdateSolicitacaoAtividadesRequest` (`complementares`)
- `App\Http\Controllers\Portal\SolicitacaoAtividadeController`
- `App\Http\Controllers\Portal\SolicitacaoController::cnaesComplementaresMax`
- Parâmetro `solicitacao.cnaes_complementares.max`

## Não evidenciado
- Obrigatoriedade de ao menos um CNAE complementar.
- Limite legal fixo diferente do parâmetro.
- Gravação de complementar sem atividade principal no mesmo pedido.
