# Alteração de atividade por comparação com a SEFAZ e zona pela centralidade linear

Origem: documento "Requisitos para Homologação – Alteração de Atividade Econômica e Zoneamento" (21/10/2026) e HU.182 v1.1. Dois requisitos independentes, entregues na ordem RF-02, depois RF-01.

## Objetivo

- **RF-02:** a zona do imóvel passa a considerar a Zona de Centralidade Linear (ZCLMu e ZCLMe) antes da maior porção.
- **RF-01:** a Alteração de Atividade Econômica compara os CNAEs do pedido com os da viabilidade vinculada ao CNPJ na SEFAZ e trata inclusões, exclusões e códigos inválidos.

## Não objetivos

- Não mexer na regra da maior porção (art. 18, § 2º, Lei 9.148/2016) quando nenhuma linha passa por cima do lote.
- Não implementar distância ou lindeiro como critério de centralidade linear.
- Não ligar a SEFAZ real: a consulta nasce atrás de uma interface, sem simulador; o provider real entra trocando só o binding.

## RF-02: zona pela centralidade linear

Regra (HU.182 RN-01 a RN-07):
1. Se a linha da ZCLMu ou da ZCLMe passa por cima do polígono do lote, em qualquer parte, a zona é essa. O percentual em outras zonas não conta.
2. Sem linha por cima, vale a maior porção, como hoje. Empate vai para a análise (RN-06).
3. As duas linhas por cima do mesmo lote vão para a análise técnica com os dois nomes no motivo (RN-07).
4. Sem polígono, só ponto: comportamento atual mantido. Fica em aberto com a SEDUR (HU.182, ponto 5).

Onde entra: `ZonaProcessoService::materializar` (hoje só chama `ZonaMaiorPorcaoResolver`). A interseção linha × polígono usa as camadas `louos_zona_uso_zclmu` e `louos_zona_uso_zclme` já configuradas em `config/viabiliza.php`. `ZonaFonte` ganha um caso para a centralidade linear, para o mapa e a ficha dizerem por que a zona foi escolhida. O empate e o conflito ZCLMu/ZCLMe reaproveitam o encaminhamento à análise que já existe para o empate.

Aceite: cenários Z1 a Z6 da HU.182 como testes. Massa: polígonos desenhados à mão que cruzem a linha da ZCLMe e as duas linhas, porque o mapa real quase não tem lotes cruzados.

## RF-01: comparação com a viabilidade da SEFAZ

Escopo: serviço `alteracao-atividade`. Comparação só pelo código CNAE, no formato `4711-3/02`.

Regras:
- **R1, incluída:** CNAE no pedido e fora da viabilidade. Cobra-se a taxa da atividade mais cara entre as incluídas. Cada incluída segue o fluxo da sua zona e via (deferimento expresso, indeferimento expresso ou análise).
- **R2, excluída:** CNAE na viabilidade e fora do pedido. Mostra código e descrição, sem taxa, fluxo expresso.
- **R3, código inválido:** código fora da lista oficial de CNAEs do sistema. Mostra código e descrição quando houver, vai para o semi-expresso, o analista corrige na ficha, e o produto de viabilidade sai conforme a ficha.
- Inclusão e exclusão no mesmo pedido: prevalece o fluxo da inclusão. A exclusão não gera taxa.
- Deferimento gera viabilidade nova com o mesmo número da existente e inclui só as atividades solicitadas.
- CNPJ sem viabilidade vinculada: todas as atividades são tratadas como incluídas.
- SEFAZ indisponível: tenta por 24 horas. Esgotado o prazo, indefere por estabilidade sistêmica e orienta nova solicitação.
- Registrar em log o resultado da consulta e da comparação.

Arquitetura:
- Interface `SefazViabilidadeConsultaGateway` com `consultarPorCnpj(string $cnpj): ?ViabilidadeVinculada` (lista de CNAEs e número da viabilidade). Hoje só existe o provider indisponível, que lança `SefazConsultaIndisponivelException`; o provider real entra quando a SEFAZ informar o endpoint
- Serviço `ComparacaoCnaesViabilidadeService` que devolve incluídas, excluídas e inválidas, sem efeitos colaterais. Fica testado isolado.
- `FluxoExpressoService::decidirSobLock` chama a comparação antes do enquadramento, só para `alteracao-atividade`. O resultado alimenta o enquadramento (que atividades passam pelo motor), a taxa (`TllCalculoService`) e o motivo de encaminhamento.
- A indisponibilidade da SEFAZ usa um job com tentativas espaçadas dentro de 24 horas e, ao esgotar, o indeferimento pelo caminho existente de indeferimento automático.

## Minhas escolhas (não confirmadas com você)

1. **Consulta atrás de interface, sem simulador (decisão de 24/10).** Não há endpoint da SEFAZ para viabilidade por CNPJ no código nem no documento.
2. **Portal mantém as intenções incluir/excluir.** O RF-01 vale para o pedido que chega sem intenção por CNAE, ou seja, o evento 244 da JUCEB. Isso substitui, só para o serviço de alteração, a decisão de 19/10 de reenquadrar todos os CNAEs sem comparar. Confirmar.
3. **Viabilidade vigente mais recente** quando houver mais de uma para o CNPJ.
4. **Código inválido fora do cálculo da taxa** até o analista corrigir; depois da correção a comparação e a taxa são refeitas.
5. **Frequência das tentativas à SEFAZ:** a cada 30 minutos dentro das 24 horas.
6. **Exclusão pura e perda da condição de sede** seguem o que já existe no código (RN-AA-03 a RN-AA-07).
7. **Nome do número da viabilidade:** manter o mesmo número significa uma revisão da viabilidade existente; a anterior fica no histórico.

## Em aberto, depende de terceiros

- Endpoint e formato da resposta da SEFAZ (RF-01).
- SEDUR: imóvel só com ponto e confirmação da regra "linha por cima" (HU.182, pontos 1, 3 e 5).
- Reenquadrar todos os CNAEs (decisão de 19/10) × comparar com a SEFAZ (RF-01), conforme a escolha 2.
