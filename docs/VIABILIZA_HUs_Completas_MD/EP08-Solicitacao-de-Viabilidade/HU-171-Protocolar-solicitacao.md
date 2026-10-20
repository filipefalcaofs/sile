# HU-171 — Protocolar solicitação

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Transformar o rascunho em solicitação protocolada, com número único, data de protocolo, trilha de transição e comprovante em PDF para o requerente.

## História de Usuário
**Como** requerente dono do rascunho,  
**quero** protocolar a solicitação,  
**para** receber um número oficial e acompanhar o processo.

## Atores e permissões
- Protocolar: dono efetivo e status rascunho (`ViabilityRequestPolicy::protocol`).
- Rota `POST portal/solicitacoes/{solicitacao}/protocolar`, com autenticação do portal e middleware `portal.solicitacao`.
- Comprovante: dono efetivo (`view`), `GET portal/solicitacoes/{solicitacao}/comprovante`. Sem protocolo, a resposta é 422.

## Fluxo evidenciado
1. O pedido pode trazer `proceed_despite`. O serviço só grava `applicant_proceeded_despite` quando esse sinal é verdadeiro e a simulação consolidada é `nao_permitido`.
2. Antes de consumir número, recusa status diferente de rascunho, dados mínimos ausentes e documentos obrigatórios faltantes (origem diferente de Regin).
3. Em uma transação, `ProtocolNumberGenerator` gera o número, grava `protocol_number` e `protocoled_at` e a máquina de estados vai de rascunho para `protocolada`, com rótulo público e auditoria da transição.
4. Fora da transação, `completarTerritorio` tenta localização pela inscrição, zona, bairro, CODLOG, logradouro tributário e polígono de endereçamento. Falha de SEFAZ ou GIS não desfaz o protocolo.
5. Depois do commit, dispara `SolicitacaoProtocolada`. O listener `AvaliarFluxoExpresso` enfileira `DecidirFluxoExpressoJob`.
6. O portal redireciona à lista com o número gerado. Erro de documento ou de dados mínimos volta como flash, sem número.
7. Na consulta autenticada, o dono baixa o comprovante. O PDF é gerado na hora, não fica armazenado, traz protocolo, empresa, endereço, área, CNAEs e código `CMP-{protocolo}-{hmac}`. Cada emissão é auditada.

## Regras de negócio evidenciadas
- O número tem a forma `{prefixo}-{ano}-{sequência}`, prefixo `solicitacao.protocolo.prefixo` (padrão `VIA`) e padding `solicitacao.protocolo.padding` (padrão 6). O contador anual usa `lockForUpdate`. O único de `protocol_number` é a defesa final.
- Dados mínimos do portal: empresa, polígono, área maior que zero e CNAE principal. Origem Regin dispensa o polígono nessa trava e dispensa a trava documental.
- A simulação desfavorável não impede o protocolo. A ciência só é registrada no caso `nao_permitido`.
- O snapshot da simulação não é reprocessado no protocolo.
- Transição inválida lança `InvalidStatusTransitionException`.

## Validações
- `proceed_despite` opcional, booleano.
- Bloqueios de domínio, com mensagem ao usuário: documentação incompleta e solicitação incompleta (lista de campos).
- Comprovante inexistente: solicitação sem `protocol_number` ou sem `protocoled_at`.

## Critérios de aceite
- Dado rascunho do dono com empresa, polígono, área positiva, CNAE principal e documentos obrigatórios cobertos, quando protocola, então o status passa a `protocolada`, nasce número no formato configurado e a lista confirma o número.
- Dado campo mínimo ou documento obrigatório faltante (origem não Regin), quando protocola, então não há número novo e o erro é exibido.
- Dado simulação `nao_permitido` e `proceed_despite` verdadeiro, quando protocola, então `applicant_proceeded_despite` fica verdadeiro. Sem essa tendência, o sinal não é gravado.
- Dado solicitação já protocolada, quando o dono pede o comprovante, então recebe PDF com o código de verificação. Dado rascunho, quando pede o comprovante, então a resposta é 422.
- Dado terceiro, quando protocola ou baixa o comprovante, então a policy nega.

## Divergências da redação anterior
O protocolo não espera pagamento nem DAM. Em seguida o sistema enfileira a decisão do fluxo expresso. O comprovante é PDF do protocolo, gerado sob demanda, distinto do TVL.

## Evidências
- `App\Http\Controllers\Portal\ProtocoloController`
- `App\Services\Solicitacao\ProtocolarSolicitacaoService`
- `App\Services\Solicitacao\ProtocolNumberGenerator`
- `App\Services\Solicitacao\ViabilityRequestStateMachine`
- `App\Http\Controllers\Portal\ComprovanteProtocoloController`
- `App\Services\Solicitacao\ComprovanteProtocoloService`
- `App\Listeners\AvaliarFluxoExpresso`
- `resources/js/pages/portal/solicitacoes/protocolo.tsx` (ação "Baixar comprovante (PDF)")

## Não evidenciado
- Pagamento ou DAM como condição do protocolo.
- Armazenamento permanente do PDF do comprovante.
- Reprocessamento do motor de viabilidade dentro do `ProtocolarSolicitacaoService`.
