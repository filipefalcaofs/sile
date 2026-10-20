# HU.024 – MANTER VIAS DO MOTOR
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O mantenedor cadastra as vias que o Quadro 11A precisa citar para ser publicado. A lista devolve todas, ordenadas por código, sem paginação. Cada item mostra código, nome e situação. Incluir grava código, nome e situação. Via nova nasce ativa quando a situação não vem. Na edição mudam o nome e, se vier, a situação. O código enviado é ignorado. Sem informar a situação, ela permanece. Não há exclusão. Quadros vigentes que já citam a via permanecem. Publicação nova de rascunho do Quadro 11A que cite via inativa ou inexistente é bloqueada, e a mensagem da tela diz isso. Esta tela guarda código e nome da via do Quadro 11A. Não cadastra logradouro geográfico.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter vias do motor](#história-de-usuário-manter-vias-do-motor)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Vias](#tela-1--vias)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** mantenedor da LOUOS **quero** manter as vias que o Quadro 11A cita**, de modo que** a publicação nova recuse via inexistente ou inativa.

## História de Usuário: Manter vias do motor

**Como** mantenedor autorizado a manter a LOUOS **quero** cadastrar a via e desativá-la quando deixar de valer**, de modo que** o quadro vigente permaneça e o código não mude depois do cadastro.

## Pré-requisitos

- Mantenedor autorizado a manter a LOUOS
- Código ainda não usado, com até 50 caracteres, no cadastro

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- A publicação do rascunho do Quadro 11A exige via existente e ativa

---

## Interface de Usuário

### Tela 1 — Vias

**Caminho no menu:** Regras > Vias

*[Print da tela](screenshots/gestao-vias.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro**
**Dado que** o mantenedor está autorizado e informa um código ainda não usado
**Quando** cadastra o nome
**Então** a via nasce ativa se a situação não for informada

**Cenário 2: Código imutável**
**Dado que** a via já está gravada
**Quando** a edição envia outro código
**Então** o código armazenado permanece

**Cenário 3: Desativar**
**Dado que** a via está ativa
**Quando** o mantenedor desativa
**Então** a via fica inativa, os quadros vigentes que já a citam permanecem e a mensagem informa que publicação nova do Quadro 11A que a cite será bloqueada

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
