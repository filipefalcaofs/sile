# HU.148 – REVOGAR PROCURAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O outorgante revoga a própria procuração na hora. O registro não é apagado: ficam a data e quem revogou, e a lista continua mostrando a procuração como inativa. A atuação aberta na sessão do procurador cai na próxima ação dele no portal.

O procurador também pode encerrar a atuação da própria sessão sem revogar o vínculo. A procuração continua ativa e pode ser usada de novo. Não há revogação pelo console, revogação em lote, restauração de procuração revogada nem aviso por e-mail ao procurador. Não há carência nem aprovação de um servidor: a revogação vale na hora.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Revogar procuração](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** que o outorgante revogue a procuração na hora**, de modo que** o procurador deixe de atuar em nome dele

## História de Usuário: Revogar procuração

**Como** outorgante **quero** revogar uma procuração que concedi e, quando eu for o procurador em atuação, encerrar só essa atuação**, de modo que** eu retire o acesso em meu nome ou volte a agir somente no meu

## Pré-requisitos

- Cidadão autenticado no portal, com e-mail confirmado e termo aceito
- Para revogar: a pessoa é o outorgante daquela procuração
- Para encerrar a atuação: o procurador está atuando em nome de alguém

## Dependências

**Passadas**

- HU-147 — procuração já concedida

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Procurações

**Caminho no menu:** Serviços > Procurações

*[Print da tela](screenshots/portal-procuracoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Outorgante revoga**
**Dado que** a procuração foi concedida pelo usuário autenticado
**Quando** ele revoga
**Então** a data e o autor da revogação são gravados, o registro permanece e a mensagem é "Procuração revogada."

**Cenário 2: Procurador não revoga**
**Dado que** quem está autenticado é o procurador ou outra pessoa que não outorgou
**Quando** tenta revogar a procuração
**Então** o sistema recusa e a procuração continua sem revogação

**Cenário 3: Efeito na sessão do procurador**
**Dado que** o procurador estava atuando com a procuração que acabou de ser revogada
**Quando** ele faz uma nova ação no portal
**Então** o sistema encerra a atuação e informa que a representação foi encerrada porque a procuração não está mais ativa

**Cenário 4: Encerrar a atuação sem revogar**
**Dado que** o procurador está em atuação e a procuração ainda está ativa
**Quando** encerra a atuação na própria sessão
**Então** ele volta a agir só em seu nome, o encerramento fica registrado e a procuração continua ativa

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
