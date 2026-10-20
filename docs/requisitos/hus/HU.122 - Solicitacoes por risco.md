# HU.122 – SOLICITAÇÕES POR RISCO
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

O gestor vê o volume de solicitações protocoladas pelo nível de risco. Quando há versão vigente de risco municipal, o nível é o da atividade principal nessa versão. Médio risco continua Médio risco: o nome da classificação vigente não é trocado por outro. Sem essa classificação, a tela usa a categoria de análise já gravada no processo. Sem as duas, o nível aparece como não classificado. Cada linha informa a origem: classificação do decreto, categoria já gravada ou indefinida. Sem versão vigente, a origem “classificação do decreto” não aparece. A contagem é de processos distintos. Nenhum nível é inventado na hora da consulta. Período invertido não agrega. A atividade principal é a marcada como principal no processo.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Solicitações por risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** ver o volume por nível de risco**, de modo que** eu distinga a classificação do decreto da categoria já gravada no processo

## História de Usuário: Solicitações por risco

**Como** gestor **quero** que Médio risco permaneça Médio risco**, de modo que** a série não troque o nome da classificação vigente

## Pré-requisitos

- Atribuição de consultar relatórios
- Versão vigente de risco municipal, para a origem ser a classificação do decreto

## Dependências

**Passadas**

- Solicitações protocoladas e classificação de risco vigente, quando houver

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Volume por risco

**Caminho no menu:** Indicadores > Indicadores de viabilidade

*[Print da tela](screenshots/gestao-indicadores.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Classificação vigente**
**Dado que** existe versão vigente de risco municipal para a atividade principal
**Quando** o gestor consulta
**Então** a linha usa esse nível, Médio risco continua Médio risco, e a origem é a classificação do decreto

**Cenário 2: Só a categoria do processo**
**Dado que** não há classificação vigente e o processo tem categoria de análise gravada
**Quando** o gestor consulta
**Então** a linha usa essa categoria e informa que a origem é derivada do processo

**Cenário 3: Sem classificação e sem categoria**
**Dado que** o processo não tem classificação nem categoria
**Quando** a série é montada
**Então** o nível aparece como não classificado e a origem fica indefinida

**Cenário 4: Sem versão vigente**
**Dado que** não há versão vigente de risco municipal
**Quando** o gestor consulta
**Então** a tela não consulta a classificação para fabricar risco

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
