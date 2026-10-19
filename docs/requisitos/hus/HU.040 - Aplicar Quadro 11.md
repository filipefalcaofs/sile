# HU.040 – APLICAR QUADRO 11
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O motor não aplica um Quadro 11 distinto do Quadro 11A. Não há versão, tela nem parecer próprios do Quadro 11. A condição pela via implementada é a do Quadro 11A.

Quando o parecer é montado, as dimensões devolvidas são o enquadramento, o Quadro 10 e o Quadro 11A. Não existe dimensão Quadro 11. Os efeitos de via vedada, análise da CNLU e condição de instalação estão descritos na aplicação do Quadro 11A. Não há diferença de efeito entre um Quadro 11 e o Quadro 11A, porque só o 11A produz classe da via e condições.

---

## Sumário

- [Épico: EP05 — Motor de Regras da LOUOS](#épico)
- [História de Usuário: Aplicar Quadro 11](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP05 — Motor de Regras da LOUOS

**Como** a SEDUR **quero** deixar claro que o Quadro 11 não é aplicado à parte**, de modo que** a condição pela via seja lida no Quadro 11A

## História de Usuário: Aplicar Quadro 11

**Como** analista **quero** ver as condições do Quadro 11 no parecer**, de modo que** eu reconheça que essa dimensão não existe e a via está no Quadro 11A

## Pré-requisitos

- Parecer de viabilidade ou processo já enquadrado pelo motor
- Os quadros aplicados são o Quadro 10 e o Quadro 11A

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- HU-041 — a condição pela via é a do Quadro 11A

---

## Interface de Usuário

### Tela 1 — Parecer da LOUOS

**Caminho no menu:** O resultado aparece em Consultar viabilidade e no processo. A manutenção fica em Regras > Motor de regras

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Dimensões do parecer**
**Dado que** o motor enquadra uma atividade
**Quando** as dimensões devolvidas são lidas
**Então** existem enquadramento, Quadro 10 e Quadro 11A, e não existe dimensão Quadro 11

**Cenário 2: Quadro 11 não versionado**
**Dado que** se procura uma versão própria do Quadro 11
**Quando** o Motor de regras é consultado
**Então** essa versão não existe

**Cenário 3: Efeito da via no Quadro 11A**
**Dado que** a via tem classe oficial e condição no quadro vigente
**Quando** o parecer consolida a via
**Então** o efeito é o do Quadro 11A, e não o de um quadro paralelo

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
