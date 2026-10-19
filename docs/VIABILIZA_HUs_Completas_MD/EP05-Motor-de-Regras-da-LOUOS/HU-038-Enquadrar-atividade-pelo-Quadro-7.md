# HU-038 — Enquadrar atividade pelo Quadro 7

## Épica
**EP05 — Motor de Regras da LOUOS**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Classificar a atividade (grupo, subgrupo e código de uso) antes de consultar zona e via. O código não aplica o Quadro 7. A dimensão se chama enquadramento e sai do ramo da planilha de tratamento vigente.

## História de Usuário
**Como** sistema,  
**quero** resolver o ramo da planilha para o CNAE, a área, as respostas e o tipo de imóvel,  
**para** saber o grupo de uso que o Quadro 10 e o Quadro 11A vão consultar.

## Atores e permissões
- O motor roda dentro da consulta de viabilidade e do fluxo do processo. Não há tela “Quadro 7”.
- A planilha é o domínio `risco_tratamento`, publicação sensível (quatro olhos) em Motor de regras, permissão `manter-louos`.
- Consulta do hub: `consultar-louos` ou `consultar-cnaes`.

## Fluxo evidenciado
1. `LouosEnquadramentoService::enquadrar` normaliza o CNAE para dígitos e chama `resolverEnquadramento`.
2. `TratamentoRamoResolver` recebe CNAE no formato `0000-0/00`, respostas, área utilizada, tipo de imóvel, data, override de versão e categoria da empresa.
3. Ramo resolvido e que não bloqueia recebe o motivo de enquadramento (CNAE, área, grupo, subgrupo e código LOUOS). Sem ramo, o status não é `identificado` e nenhum grupo é inventado.
4. Linha marcada como CNLU traz o grupo, mas `cnlu` verdadeiro. O consolidado só indefere se o Quadro 10 proibir. Nos outros casos fica pendente, com o texto de enquadramento CNLU.
5. Ramo com `bloqueia` verdadeiro torna o consolidado `nao_permitido`, sem condicionantes, com o motivo da planilha.
6. Não existe domínio `louos_quadro7`, model de faixa do Quadro 7 nem chamada de quadro 7 em `enquadrar`. O resultado expõe a chave `enquadramento`, não `quadro7`.

## Regras de negócio evidenciadas
- A versão usada é a vigente, a vigente na data, ou a versão do sandbox (`versoesOverride`).
- Sem enquadramento identificado, o consolidado é `pendente`, salvo o bloqueio explícito da planilha e o caso CNLU com Quadro 10 proibido.
- O motivo de enquadramento não afirma que o uso é permitido na zona. Quem permite ou proíbe é o Quadro 10.

## Validações
- CNAE é reduzido a dígitos antes da formatação `0000-0/00`. Comprimento diferente de 7 permanece sem essa máscara na classificação de risco; no enquadramento a formatação só aplica a máscara com 7 dígitos.
- Pergunta da planilha sem resposta, no expresso, pode indeferir o processo quando o consolidado ainda não é `nao_permitido` e a configuração `expresso.indeferir_pergunta_sem_resposta` está ligada (padrão verdadeiro).

## Critérios de aceite
**Dado** que a planilha vigente resolve um ramo para o CNAE, a área e as respostas,  
**Quando** o motor enquadra,  
**Então** a dimensão `enquadramento` fica identificada com grupo e subgrupo, e a auditoria grava a versão `risco_tratamento`.

**Dado** que nenhum ramo é resolvido,  
**Quando** o motor enquadra,  
**Então** o consolidado fica `pendente` e nenhum grupo de uso é criado.

**Dado** que se procura tabela, enum ou serviço do Quadro 7,  
**Quando** o código do motor é lido,  
**Então** o Quadro 7 não é consultado.

## Divergências da redação anterior
A redação anterior atribuía o enquadramento ao Quadro 7. O motor usa a planilha de tratamento (`risco_tratamento`). O Quadro 7 saiu do código.

## Evidências
- `LouosEnquadramentoService::resolverEnquadramento`
- `TratamentoRamoResolver`
- `RuleDomain::RiscoTratamento`
- `EnquadramentoResult` (dimensão `enquadramento`)

## Não evidenciado
- Faixas de área do Quadro 7, domínio `louos_quadro7` ou importação desse quadro.
- Decisão de permitido ou proibido só com o ramo da planilha, sem o Quadro 10, exceto quando o ramo bloqueia o prosseguimento.
