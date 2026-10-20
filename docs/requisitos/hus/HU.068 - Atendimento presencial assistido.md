# HU.068 – ATENDIMENTO PRESENCIAL ASSISTIDO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O atendente abre, no console, um atendimento em nome de um cidadão que já tem conta. O sistema não cria conta no balcão: sem cadastro, o atendimento não começa e a mensagem orienta o cidadão a criar a conta. O atendente não inicia atendimento para si mesmo. Enquanto o atendimento está aberto, a estação mostra o nome, o CPF mascarado, o horário de expiração, as empresas do cidadão e os tipos de serviço ativos. O vínculo expira no prazo configurado, com padrão de 30 minutos. Com atendimento ativo, o atendente abre um rascunho de solicitação de origem portal: o cidadão fica como requerente, o atendente como autor, e a empresa precisa ter vínculo ativo com o cidadão atendido. O rascunho não protocola, não anexa documento e não responde pendência nesta tela. Encerrar registra o fim e tira o atendimento da estação. Fora do prazo, ou sem atendimento aberto, a abertura da solicitação é recusada.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico-ep08--solicitação-de-viabilidade)
- [História de Usuário: Atendimento presencial assistido](#história-de-usuário-atendimento-presencial-assistido)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Atendimento presencial](#tela-1--atendimento-presencial)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** atendente da SEDUR **quero** iniciar o pedido de quem está no balcão**, de modo que** o rascunho fique em nome do cidadão que já tem conta no portal.

## História de Usuário: Atendimento presencial assistido

**Como** atendente autorizado ao atendimento presencial **quero** localizar o cidadão pelo CPF e abrir a solicitação em nome dele**, de modo que** o pedido do balcão nasça com o cidadão como requerente e o atendente como autor.

## Pré-requisitos

- Atendente autorizado ao atendimento presencial
- Cidadão já cadastrado, com CPF diferente do atendente
- Empresa com vínculo ativo do cidadão atendido, para abrir o rascunho
- Tipo de serviço ativo

## Dependências

**Passadas**

- O cidadão já possui conta no portal

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Atendimento presencial

**Caminho no menu:** Operação > Atendimento presencial

*[Print da tela](screenshots/gestao-atendimento.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Iniciar atendimento**
**Dado que** o atendente está autorizado e informa o CPF de outro usuário já cadastrado
**Quando** inicia o atendimento
**Então** a estação mostra o atendimento com prazo de expiração e o início fica registrado

**Cenário 2: Abrir rascunho**
**Dado que** o atendimento está ativo, o tipo de serviço está ativo e a empresa tem vínculo do cidadão
**Quando** o atendente abre a solicitação
**Então** nasce um rascunho de origem portal, com o cidadão como requerente, o atendente como autor e o atendimento vinculado, e a estação permanece aberta

**Cenário 3: CPF sem cadastro ou do próprio atendente**
**Dado que** o CPF não tem conta ou é o do próprio atendente
**Quando** o atendente tenta iniciar
**Então** o atendimento não começa

**Cenário 4: Atendimento expirado ou empresa sem vínculo**
**Dado que** o atendimento expirou ou a empresa não tem vínculo ativo do cidadão
**Quando** o atendente tenta abrir a solicitação
**Então** o rascunho não é criado

**Cenário 5: Encerrar**
**Dado que** há atendimento em curso
**Quando** o atendente encerra
**Então** o fim é registrado e a estação deixa de mostrar esse atendimento

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
