# HU-051 — Aplicar exceções de risco

## Épica
**EP06 — Classificação de Risco**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Tirar o encaminhamento do mapa de risco quando um gatilho ativo incide ou quando o tipo de imóvel exige análise. A reclassificação por condicionante-pergunta não é executada.

## História de Usuário
**Como** sistema,  
**quero** aplicar as exceções cadastradas que mandam o caso à análise,  
**para** não manter no expresso um contexto que o gatilho marca como semi-expresso.

## Atores e permissões
- Manutenção dos gatilhos: `manter-gatilhos-risco`. A tela lista, edita motivo e liga ou desliga. Não cria nem apaga código: o código é o enum `TipoGatilho`.
- O motor só aciona gatilho que esteja ativo na tabela `risk_triggers` e cujo código venha no contexto, ou que ele mesmo acrescente.

## Fluxo evidenciado
1. `applyGatilhos` lê os gatilhos ativos e cruza com `gatilhosContexto`.
2. Se o tipo de imóvel dirige regra, ou se o valor enviado não é reconhecido (`Desconhecido`), o código `dados_do_processo` entra no contexto. Tipo ausente (nada foi enviado) não aciona.
3. Cada gatilho casado devolve código e motivo. Se a lista não é vazia, o fluxo vira `analise` e o motivo do encaminhamento passa a ser o do primeiro gatilho.
4. O seed grava três gatilhos, ativos na criação, sem recolocar `ativo` num re-seed: `enquadramento_ausente`, `zeis_especial` e `dados_do_processo`.
5. O enum tem ainda `regra_territorial`, `localizacao_divergente` e `zona_empate`. O expresso grava esses códigos ao encaminhar por regra de lugar, divergência de zona ou empate de porções. Isso não passa por `applyGatilhos`.
6. `ConsultaViabilidadeService` monta `RiscoInput` sem lista de gatilhos. A consulta prévia não envia `zeis_especial`, mesmo que a camada de restrição tenha feição.
7. O model `RiskCondicionante` descreve `regra_reclassificacao` com `resposta_gatilho` e `reclassifica_para`. `classifySanitario` não lê esse model. Devolve `reclassificado` falso, `nivel_final` igual ao original e perguntas vazias. O comentário do serviço registra que a validação de condicionantes da VISA foi retirada.
8. As frases de condicionante da linha municipal entram na fundamentação. Não mudam o nível.

## Regras de negócio evidenciadas
- Gatilho acionado vence o mapa, inclusive quando o mapa diria expresso.
- Desligar o gatilho na tela muda o roteamento. O registro não é excluído.
- ZEIS como gatilho de risco só ocorre se algum chamador colocar `zeis_especial` no contexto e o gatilho estiver ativo. A identificação territorial não faz isso.
- Tipo de imóvel desconhecido vai à análise. Tipo omitido não.

## Validações
- Código de gatilho fora do enum não é cadastrável por esta tela.
- Contexto vazio e tipo de imóvel que não exige análise produzem lista vazia de gatilhos.

## Critérios de aceite
**Dado** que `dados_do_processo` está ativo e o tipo de imóvel dirige regra ou é desconhecido,  
**Quando** o motor classifica,  
**Então** o fluxo é `analise` e o gatilho aparece em `gatilhos_acionados`.

**Dado** que o classificador recebe a dimensão sanitária de um CNAE,  
**Quando** o resultado sanitário é lido,  
**Então** `reclassificado` é falso e não há nível alterado por resposta.

**Dado** que a consulta prévia identifica restrição no ponto,  
**Quando** o risco é classificado por essa consulta,  
**Então** `zeis_especial` não é enviado no contexto.

## Divergências da redação anterior
A redação anterior tratava exceção de risco como reclassificação genérica. O código aplica gatilho que força análise e não reclassifica o nível pela condicionante.

## Evidências
- `RiscoClassificationService::applyGatilhos` e `classifySanitario`
- `TipoGatilho`, `RiskTrigger`, `RiskTriggerSeeder`, `RiskTriggerController`
- `RiskCondicionante` (contrato não usado pelo classificador)
- `ConsultaViabilidadeService::riscoInput`
- `TipoImovel` e `TipoImovelReconhecimento::Desconhecido`

## Não evidenciado
- Resposta de condicionante que mude o nível para outro grau.
- Inclusão automática de `zeis_especial` a partir da camada de restrição.
- Criação de gatilho novo pela tela, fora do enum.
