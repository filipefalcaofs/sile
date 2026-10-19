# HU-043 — Aplicar restrições especiais

## Épica
**EP05 — Motor de Regras da LOUOS**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Registrar restrição territorial incidente como condicionante do parecer e aplicar as regras de lugar versionadas. A observação (c) das regras territoriais não está implementada.

## História de Usuário
**Como** sistema,  
**quero** aplicar a restrição do lugar que o território e as regras RT indicarem,  
**para** não tratar como uso livre um imóvel em área com regra especial.

## Atores e permissões
- A leitura da camada de restrição ocorre na identificação territorial (HU-035).
- A publicação das regras RT usa o domínio `territorial`, `manter-louos`, quatro olhos.
- O expresso avalia `TerritorialRuleService` antes do enquadramento decidir o fluxo.

## Fluxo evidenciado
1. `avaliarRestricoes` lê `territory.restricoes`. Status diferente de `identificado` produz lista vazia. Não transforma indisponível em restrição.
2. Cada item identificado vira condicionante `restricao`, com o nome da feição e o motivo “Restrição territorial incidente (ex.: ZEIS)”. Essa condicionante rebaixa para `permitido_com_condicoes`. Não torna o uso não permitido e não classifica o risco.
3. O seed da camada de restrição é o snapshot `geosalvador-pddu2016-zeis`. O nome ZEIS no motivo é exemplo do código, não um filtro que ignore outras feições da mesma camada: toda feição intersectada entra.
4. As regras RT-01 a RT-04 e as allow-lists RT-05-A e RT-05-B estão em `TerritorialRuleService` e no seed, descritas na HU-035. No enquadramento, RT-05-A e RT-05-B alteram o Quadro 10 (HU-039): fora da lista, proibido; sem dado para verificar, indisponível.
5. RT-01 indefere. Poligonal incidente encaminha à análise. Allow-list de endereço não defere.
6. O seed registra que RT-05 (c) não é carregada. Não há avaliador para essa letra.
7. O gatilho `zeis_especial` existe no catálogo de gatilhos de risco, mas `ConsultaViabilidadeService` não envia gatilhos de contexto ao classificar. A feição ZEIS desta HU não aciona esse gatilho.

## Regras de negócio evidenciadas
- Restrição da camada e regra territorial são mecanismos diferentes. A primeira vira condicionante do parecer. A segunda pode indeferir ou encaminhar antes do consolidado de risco.
- Camada de restrição indisponível não gera condicionante e não simula ZEIS.
- `ViaAllowlistZona` não é avaliada no loop de `avaliar`. Só `observacaoQuadro10Permite`.

## Validações
- Lista de restrições só é lida quando o status da dimensão é `identificado`.
- Allow-list da observação sem CODLOG e sem bairro não libera nem proíbe: a dimensão do Quadro 10 fica indisponível.

## Critérios de aceite
**Dado** que a camada de restrição identifica uma ou mais feições no ponto,  
**Quando** o parecer chega à coleta de condicionantes,  
**Então** cada feição vira condicionante `restricao` e pode rebaixar o resultado para `permitido_com_condicoes`.

**Dado** que a camada de restrição está indisponível ou não encontra feição,  
**Quando** as restrições são avaliadas,  
**Então** nenhuma condicionante de restrição é criada.

**Dado** que a linha do Quadro 10 tem observação (a) ou (b),  
**Quando** o CODLOG ou o bairro não está na regra RT-05 correspondente,  
**Então** a permissão aplicada é proibida.

**Dado** que se procura a regra RT-05 (c),  
**Quando** o seed e o avaliador são lidos,  
**Então** ela não é carregada nem executada.

## Divergências da redação anterior
A redação anterior juntava restrição especial num único passo. O código separa feição da camada, regras RT e a ausência da observação (c).

## Evidências
- `LouosEnquadramentoService::avaliarRestricoes` e `observacaoQuadro10`
- `TerritoryService::identifyIntersecting`
- `TerritorialRuleService`, `TerritorialRuleSeeder`
- `TipoGatilho::ZeisEspecial` (catálogo; não ligado por esta identificação)

## Não evidenciado
- RT-05 (c).
- Encaminhamento automático ao gatilho `zeis_especial` a partir da feição de restrição.
- Regra urbanística adicional de ZEIS além de registrar a feição como condicionante e do texto do gatilho, que o classificador só aplica se o código do gatilho vier no contexto.
