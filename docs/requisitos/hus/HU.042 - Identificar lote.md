# HU.042 – IDENTIFICAR LOTE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

A identificação do lote cadastral do ponto não ocorre. A camada local de lotes está pendente de fonte, sem feições, e o sistema não consulta geometria nesse estado. A dimensão volta indisponível, com o motivo de base de lotes pendente, e nenhuma consulta espacial de lote é feita. Não há número oficial de lote, quadra ou inscrição nessa identificação, e o processo não grava identificador de lote. O que pode ser gravado no território do processo são zona, bairro oficial e número do logradouro.

Não há papel que desbloqueie o lote pela tela. Se uma camada de lote vigente, com feições, for carregada, a identificação por contenção do ponto passa a valer. Hoje o dado carregado não é esse. A consulta de uma inscrição específica, quando ligada, pode devolver a geometria daquela inscrição no endereçamento; ela não preenche a dimensão de lote da identificação por ponto e não cobre a cidade. Desligada, não inventa geometria.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico)
- [História de Usuário: Identificar lote](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** a SEDUR **quero** informar que o lote do ponto não está disponível**, de modo que** ninguém valide a localização contra um lote inventado

## História de Usuário: Identificar lote

**Como** analista **quero** saber em qual lote o ponto do imóvel cai**, de modo que** eu reconheça que essa identificação está pendente da base oficial

## Pré-requisitos

- Identificação do ponto por quem consulta o território
- Camada de lote vigente, com feições, no lugar do registro pendente de fonte; hoje essa base não está carregada

## Dependências

**Passadas**

- HU-039 — a identificação do ponto, da qual a dimensão de lote faz parte

**Futuras**

- HU-045 — a comparação do polígono com o lote oficial também fica indisponível enquanto a camada estiver pendente

---

## Interface de Usuário

### Tela 1 — Lote na consulta territorial

**Caminho no menu:** Território > Consulta territorial

*[Print da tela](screenshots/gestao-territorio.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Base de lotes pendente**
**Dado que** a camada de lote vigente está pendente de fonte ou não existe
**Quando** um ponto é identificado
**Então** a dimensão de lote volta indisponível, com o motivo de base pendente, e nenhuma consulta espacial de lote é feita

**Cenário 2: Consulta da inscrição desligada**
**Dado que** a consulta da inscrição no lote específico está desligada
**Quando** o endereçamento procura o lote da inscrição
**Então** o sistema informa que a consulta está desligada e não inventa geometria

**Cenário 3: O processo não grava lote**
**Dado que** o território do processo é materializado
**Quando** as dimensões identificadas são gravadas
**Então** não há identificador de lote; permanecem zona, bairro oficial e número do logradouro, quando identificados

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
