# HU.023 – MANTER ZONAS DO MOTOR
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O mantenedor cadastra as zonas urbanísticas que o Quadro 10 precisa citar para ser publicado. A lista devolve todas as zonas, ordenadas por código, sem paginação. Cada item mostra código, nome, macrozona e situação. Incluir grava código, nome, macrozona e situação. Zona nova nasce ativa quando a situação não vem. Macrozona em branco fica sem valor. Na edição mudam nome, macrozona e, se vier, a situação. O código enviado é ignorado. Sem informar a situação, ela permanece. Não há exclusão nem desenho da zona nesta tela. Desativar não apaga o histórico: a zona continua nos quadros vigentes que já a citam. Publicação nova de rascunho do Quadro 10 que cite zona inativa ou inexistente é bloqueada, e a mensagem da tela diz isso. Trocar o código é desativar a zona e cadastrar outra.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter zonas do motor](#história-de-usuário-manter-zonas-do-motor)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Zonas](#tela-1--zonas)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** mantenedor da LOUOS **quero** manter as zonas que o Quadro 10 cita**, de modo que** um código inexistente ou inativo seja bloqueado na publicação nova.

## História de Usuário: Manter zonas do motor

**Como** mantenedor autorizado a manter a LOUOS **quero** cadastrar a zona e desativá-la quando deixar de valer**, de modo que** o quadro vigente que já a cita permaneça e o código não mude.

## Pré-requisitos

- Mantenedor autorizado a manter a LOUOS
- Código ainda não usado, com até 50 caracteres, no cadastro

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- A publicação do rascunho do Quadro 10 exige que toda zona citada exista e esteja ativa

---

## Interface de Usuário

### Tela 1 — Zonas

**Caminho no menu:** Regras > Zonas

*[Print da tela](screenshots/gestao-zonas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro**
**Dado que** o mantenedor está autorizado e informa um código ainda não usado
**Quando** cadastra nome e macrozona
**Então** a zona nasce ativa se a situação não for informada

**Cenário 2: Código imutável**
**Dado que** a zona já está gravada
**Quando** a edição envia outro código
**Então** o código armazenado permanece

**Cenário 3: Desativar**
**Dado que** a zona ativa é citada num quadro vigente
**Quando** o mantenedor desativa
**Então** a zona fica inativa, o quadro vigente não é alterado e a mensagem informa que publicação nova do Quadro 10 que a cite será bloqueada

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
