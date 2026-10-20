# HU.135 – PAINEL POR BAIRRO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O gestor vê as solicitações protocoladas agrupadas pelo bairro e pela zona urbanística já gravada no processo, com as decisões e a taxa de deferimento. O bairro usado é o oficial identificado no imóvel e, na falta dele, o digitado. O resumo conta o total, os bairros distintos e quantos ficaram sem bairro. A zona é só a já identificada no processo. Quem não tem zona entra no resumo de sem zona e não recebe uma zona inventada. Bairro sem decisão fica com taxa sem valor, não zero por cento. A população é a protocolada. A exportação traz as solicitações filtradas, não o agrupamento. Não há mapa nesta tela e a zona não é consultada de novo no servidor de mapas ao abrir o painel. A consulta fica registrada. O filtro comum inclui o bairro.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Painel por bairro](#história-de-usuário-painel-por-bairro)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Painel por bairro](#tela-1--painel-por-bairro)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** ver onde a atividade protocolada se concentra**, de modo que** bairro e zona venham do que foi identificado no imóvel.

## História de Usuário: Painel por bairro

**Como** gestor autorizado a consultar relatórios **quero** ler o resumo, os bairros e as zonas do período**, de modo que** processo sem zona não ganhe zona inventada e bairro sem decisão não mostre taxa zero.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Solicitações já protocoladas

## Dependências

**Passadas**

- A zona exibida é a já gravada na identificação do imóvel

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Painel por bairro

**Caminho no menu:** Relatórios > Painel por bairro

*[Print da tela](screenshots/gestao-bairro.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Resumo, bairros e zonas**
**Dado que** há solicitações protocoladas no recorte
**Quando** o gestor abre o painel
**Então** a tela separa o resumo, o agrupamento por bairro e o agrupamento por zona, com decisões e taxa de deferimento

**Cenário 2: Sem zona**
**Dado que** o processo não tem zona identificada
**Quando** o agrupamento é montado
**Então** ele entra em sem zona e não recebe código de zona

**Cenário 3: Bairro sem decisão**
**Dado que** o bairro não tem decisão no recorte
**Quando** a taxa é calculada
**Então** a taxa de deferimento fica sem valor

**Cenário 4: Consulta registrada**
**Dado que** o gestor abre o painel
**Quando** a consulta é feita
**Então** a abertura fica registrada

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
