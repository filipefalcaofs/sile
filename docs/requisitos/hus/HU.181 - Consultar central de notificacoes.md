# HU.181 – CONSULTAR CENTRAL DE NOTIFICAÇÕES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 20/10/2026 | Criação da HU para função já existente no sistema e contada, que nenhuma HU descrevia | 2.2 | Sudoeste Informática |

---

## Visão Geral

O usuário vê no sininho quantos avisos ainda não leu e abre a central de notificações para ler a lista, separada em não lidas e lidas. Pode marcar um aviso como lido ou todos de uma vez. A central existe no portal e na gestão e mostra sempre só os avisos do próprio usuário.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico)
- [História de Usuário: Consultar central de notificações](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** a SEDUR **quero** que cada usuário tenha uma central com os avisos do sistema**, de modo que** nenhum resultado, pendência ou alerta passe despercebido

## História de Usuário: Consultar central de notificações

**Como** usuário do portal ou da gestão **quero** ver meus avisos e marcá-los como lidos**, de modo que** eu acompanhe o que o sistema me comunica e limpe o sininho

## Pré-requisitos

- Usuário autenticado
- Central de notificações habilitada

## Dependências

**Passadas**

- HU-064 — aviso do resultado da solicitação
- HU-088 — aviso de pendência
- HU-090 — aviso de vencimento
- HU-011 — alerta de exercício sem tabela TLL

---

## Interface de Usuário

### Tela 1 — Notificações do portal

**Caminho no menu:** Sininho no topo do portal > Notificações (`/portal/notificacoes`)

*[Print da tela](screenshots/portal-notificacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Ler e marcar como lida**
**Dado que** há um cidadão com um aviso não lido
**Quando** abre Notificações e clica em Marcar como lida
**Então** o aviso passa à seção dos lidos e o sininho diminui em um

**Cenário 2: Marcar todas como lidas**
**Dado que** há um cidadão com vários avisos não lidos
**Quando** clica em Marcar todas como lidas
**Então** todos os avisos ficam lidos e o sininho zera

**Cenário 3: Sem avisos**
**Dado que** há um cidadão sem avisos
**Quando** abre Notificações
**Então** vê «Nenhuma notificação»


### Tela 2 — Notificações da gestão

**Caminho no menu:** Sininho no topo da gestão > Notificações (`/gestao/notificacoes`)

*[Print da tela](screenshots/gestao-notificacoes.png)*

#### Comportamento Esperado Tela 2

**Cenário 1: Ler e marcar como lida na gestão**
**Dado que** há um servidor com um aviso não lido
**Quando** abre Notificações e clica em Marcar como lida
**Então** o aviso passa à seção dos lidos e o sininho diminui em um

**Cenário 2: Aviso de outro usuário**
**Dado que** existe o identificador de um aviso de outro usuário
**Quando** o servidor tenta marcá-lo como lido
**Então** a resposta é 404 e o aviso não muda

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
