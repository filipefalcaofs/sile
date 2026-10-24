# Validação do ponto da inscrição

## Contexto

O processo BAP2601886456 (inscrição 1078739, id 1441) tem o CODLOG 2075 (Av. Heitor Dias), mas o lote exibido pertence à Av. Barros Reis (CODLOG 761). O ponto devolvido pelo Cadastro Imobiliário (-12,965543; -38,478635) cai no terreno da Hyundai, na Barros Reis, a centenas de metros da Heitor Dias.

Hoje o ponto da SEFAZ é aceito sem conferência. Como o endereço 2075 + nº 98 não achou lote no endereçamento SEDUR, o sistema usou o lote do ponto. A zona (ZCMe 1/02) saiu do ponto e a via (VA II) saiu do CODLOG: o enquadramento combinou duas ruas diferentes.

A geocodificação por endereço (Nominatim da Prefeitura) já existe, mas só roda quando a SEFAZ não devolve coordenada.

## Objetivo

O ponto devolvido pela SEFAZ só é usado para achar lote, zona e via se combinar com o endereço da inscrição. Quando não combinar, o sistema procura um ponto melhor em outras fontes; se nenhuma confirmar, o processo vai para análise com alerta.

## Comportamento

1. A conferência roda no `LocalizacaoInscricaoService`, depois que a SEFAZ devolve o ponto e antes de ele ser usado.
2. O ponto "combina" quando está a no máximo **150 m** (configurável) da rua da inscrição na camada de vias da SEDUR, pelo CODLOG de Localização (cai no tributário se não houver).
3. Ponto que combina segue como hoje.
4. Ponto que não combina dispara a busca de um ponto alternativo, nesta ordem:
   1. Endereçamento SEDUR (`VM_ENDERECAMENTO`): lote por CODLOG + número; usa um ponto do lote.
   2. SEMAP/ArcGIS: lote pela inscrição; ignorado enquanto o token não existir ou a consulta falhar.
   3. Nominatim da Prefeitura: ponto pelo endereço.
5. O primeiro ponto alternativo que combinar com a rua da inscrição (mesmo limite) substitui o da SEFAZ. Lote, zona e via passam a ser calculados a partir dele. A origem do ponto fica registrada (`PropertyLocationSource`).
6. Se nenhuma fonte combinar, ou todas falharem, o processo mantém o ponto da SEFAZ marcado como **não confirmado**, segue para análise com alerta na ficha e **não sai decisão automática**.
7. A analista só vê aviso no caso 6. A troca do caso 5 aparece apenas na auditoria.
8. Falha de uma fonte (indisponível, fora de Salvador, sem resultado) só passa para a próxima; nunca bloqueia o processo.
9. O sistema guarda o ponto original da SEFAZ, a fonte do ponto final, as distâncias medidas e o motivo.

## Fora do escopo

- Corrigir os processos já protocolados (levantamento e correção ficam para depois, como foi feito com o CODLOG).
- Mudar a legenda do mapa da ficha além do alerta do caso 6.
- Corrigir a coordenada na SEFAZ.

## Decisões minhas (a conferir)

- Ordem das fontes e limite de 150 m.
- Sem endereço de Localização nem tributário utilizável, ou sem rua na camada de vias, a conferência não consegue rodar: o ponto da SEFAZ segue como hoje e a auditoria registra "não verificada".
- No caso 6 o ponto fica como está; apenas a decisão automática é impedida e o alerta aparece.

## Critério de aceite

O caso do processo 1441 (ponto na Barros Reis, CODLOG 2075) não pode seguir como hoje: ou troca o ponto por um confirmado, ou cai no alerta.
