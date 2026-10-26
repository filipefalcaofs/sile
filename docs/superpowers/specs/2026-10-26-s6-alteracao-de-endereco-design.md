# S6 — Revisão de Endereço → Alteração de Endereço

Origem: documento de 23/10, item 3.

## Pedido
"Revisão de Endereço" passa a "Alteração de Endereço" quando muda codlog, logradouro, número de porta/métrico ou bairro. "Revisão de endereço" deixa de existir; fica só "Revisão Alteração de Espaço".

## Mudanças
- Migration nos nomes do De x Para (`servico_nomenclaturas`): toda linha "Revisão de Endereço" vira "Alteração de Endereço"; "Revisão de Endereço - Alteração de Espaço" vira "Revisão Alteração de Espaço". Nome editado na tela não é tocado.
- `CondicaoServicoDetector::peloEndereco` também compara o logradouro (`address_street`).
