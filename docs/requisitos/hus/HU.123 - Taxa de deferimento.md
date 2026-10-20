# HU.123 – TAXA DE DEFERIMENTO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

A taxa de deferimento é a parcela de decisões deferidas sobre as decisões do recorte, com uma casa decimal. O denominador é a decisão, não o protocolo ainda sem decisão. Sem decisões, a taxa não aparece e os dois contadores ficam em zero: ausência de decisão não vira zero por cento. O indeferimento não entra no numerador. Período invertido não consulta e devolve o mesmo formato sem taxa. Não há meta de deferimento nesta série. A consulta é registrada junto com os outros indicadores da tela.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Taxa de deferimento](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** a taxa de deferimento do período**, de modo que** eu veja o resultado das decisões e não um percentual sem decisão

## História de Usuário: Taxa de deferimento

**Como** gestor **quero** o percentual de deferidas sobre as decisões do recorte**, de modo que** protocolo em aberto não entre na conta

## Pré-requisitos

- Atribuição de consultar relatórios

## Dependências

**Passadas**

- Decisões já registradas no recorte

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Taxa de deferimento

**Caminho no menu:** Indicadores > Indicadores de viabilidade

*[Print da tela](screenshots/gestao-indicadores.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Há decisões**
**Dado que** o recorte tem decisões
**Quando** o gestor consulta
**Então** a taxa fecha com os contadores de deferidas e do total, com uma casa decimal

**Cenário 2: Sem decisões**
**Dado que** o recorte não tem decisão
**Quando** o gestor consulta
**Então** a taxa não aparece e o total fica em zero

**Cenário 3: Período invertido**
**Dado que** a data inicial é posterior à final
**Quando** o gestor consulta
**Então** a taxa não é calculada

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
