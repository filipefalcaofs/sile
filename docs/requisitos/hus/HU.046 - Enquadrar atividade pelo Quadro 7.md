# HU.046 – ENQUADRAR ATIVIDADE PELO QUADRO 7
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O motor não aplica o Quadro 7. Não há tela com esse nome. A atividade é classificada pelo ramo da planilha de tratamento vigente: grupo, subgrupo e identificação de uso, a partir do CNAE, da área, das respostas, do tipo de imóvel e, quando houver, da categoria da empresa. A versão usada é a vigente, a vigente numa data ou a versão em simulação.

Sem ramo, o resultado fica pendente e nenhum grupo é inventado. Linha de CNLU traz o grupo, mas o parecer só indefere se o Quadro 10 proibir; nos outros casos fica pendente, com o texto de enquadramento CNLU. Ramo que bloqueia o prosseguimento torna o resultado não permitido, sem condicionantes, com o motivo da planilha. O ramo não afirma que o uso é permitido na zona: quem permite ou proíbe é o Quadro 10. Na resposta automática, pergunta da planilha sem resposta pode indeferir o processo quando o resultado ainda não é não permitido e essa conduta está ligada.

---

## Sumário

- [Épico: EP05 — Motor de Regras da LOUOS](#épico)
- [História de Usuário: Enquadrar atividade pelo Quadro 7](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP05 — Motor de Regras da LOUOS

**Como** a SEDUR **quero** resolver o ramo da planilha de tratamento**, de modo que** o Quadro 10 e o Quadro 11A consultem o grupo de uso sem um Quadro 7 à parte

## História de Usuário: Enquadrar atividade pelo Quadro 7

**Como** analista **quero** ver o grupo de uso resolvido para o CNAE, a área e as respostas**, de modo que** eu saiba qual atividade o parecer vai consultar na zona e na via

## Pré-requisitos

- Consulta de viabilidade ou processo em análise, com CNAE e área
- Versão vigente da planilha de tratamento, ou a versão de uma data ou de uma simulação
- A subclasse do CNAE é lida com sete dígitos

## Dependências

**Passadas**

- HU-027 — a planilha de tratamento publicada
- HU-034 — o CNAE da tabela oficial

**Futuras**

- HU-047 e HU-048 — Quadro 10 e Quadro 11A usam o grupo resolvido
- HU-051 — o resultado único combina este enquadramento com os quadros

---

## Interface de Usuário

### Tela 1 — Enquadramento no parecer

**Caminho no menu:** O resultado aparece em Consultar viabilidade e no processo. A planilha fica em Regras > Motor de regras > Enquadramento

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Ramo resolvido**
**Dado que** a planilha vigente resolve um ramo para o CNAE, a área e as respostas
**Quando** o motor enquadra
**Então** o enquadramento fica identificado, com grupo e subgrupo, e a versão da planilha fica registrada

**Cenário 2: Nenhum ramo**
**Dado que** nenhum ramo é resolvido
**Quando** o motor enquadra
**Então** o resultado fica pendente e nenhum grupo de uso é criado

**Cenário 3: Quadro 7 não é consultado**
**Dado que** se procura um Quadro 7 aplicado pelo motor
**Quando** o parecer é produzido
**Então** o Quadro 7 não é consultado; a fonte é a planilha de tratamento

**Cenário 4: Ramo que bloqueia**
**Dado que** o ramo da planilha bloqueia o prosseguimento
**Quando** o motor enquadra
**Então** o resultado é não permitido, sem condicionantes, com o motivo da planilha

**Cenário 5: Pergunta sem resposta na resposta automática**
**Dado que** a conduta de indeferir pergunta sem resposta está ligada e o parecer ainda não é não permitido
**Quando** a resposta automática encontra pergunta da planilha sem resposta
**Então** o processo pode ser indeferido

---

## Aprovação do Requisito

A Sudoeste Informática esclarece que o desenvolvimento das funcionalidades descritas neste documento foi realizado de acordo com os requisitos e especificações fornecidos pelo cliente. Nossa equipe seguiu rigorosamente as instruções recebidas, garantindo que o sistema esteja alinhado com o que foi solicitado.

Para evitar possíveis retrabalhos ou ajustes futuros, sugerimos que o cliente revise atentamente os requisitos apresentados, verificando se eles atendem plenamente às suas necessidades e expectativas, bem como se estão em conformidade com eventuais normas aplicáveis ao seu negócio.

Reafirmamos nosso compromisso de entregar um software de qualidade que agregue valor às operações do cliente, e a validação cuidadosa dos requisitos é um passo importante para garantir o sucesso do projeto.

| Data | Setor | Nome | Assinatura |
|------|-------|------|------------|
|      |       |      |            |
|      |       |      |            |
|      |       |      |            |
