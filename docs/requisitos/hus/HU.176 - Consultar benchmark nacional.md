# HU.176 – CONSULTAR BENCHMARK NACIONAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O gestor vê a posição de Salvador na régua federal de tempo de viabilidade, entre as capitais, somente quando a competência tem as 27 unidades da federação. Sem essa competência, a posição nacional não aparece, o total é zero, a lista de capitais fica vazia, a cobertura é informada como incompleta e as unidades ausentes do mês anterior são mostradas. A série histórica de Salvador ainda é enviada. Com a competência completa, a tela mostra a posição nacional, o ranking das capitais e a série de Salvador. O número vem da estatística pública da REDESIM já ingerida. Esta tela não dispara a carga. Com a importação desligada, a carga falha com motivo e não inventa planilha. Ranking parcial não é apresentado como nacional. O tempo federal não substitui o tempo medido dentro do Viabiliza. Não há filtro de período interno: a competência é a última completa armazenada. Não há exportação desta tela. A consulta fica registrada, com a competência usada.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Consultar benchmark nacional](#história-de-usuário-consultar-benchmark-nacional)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Benchmark nacional](#tela-1--benchmark-nacional)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** comparar Salvador com as outras capitais**, de modo que** a comparação use o relógio da REDESIM e não o tempo interno do Viabiliza.

## História de Usuário: Consultar benchmark nacional

**Como** gestor autorizado a consultar relatórios **quero** ver a posição nacional só quando as 27 unidades estão na competência**, de modo que** uma lacuna não vire ranking nacional.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Competência já ingerida da estatística pública da REDESIM

## Dependências

**Passadas**

- A carga da planilha pública ocorre à parte e só quando a importação está ligada

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Benchmark nacional

**Caminho no menu:** Indicadores > Benchmark nacional

*[Print da tela](screenshots/gestao-benchmark.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Competência incompleta**
**Dado que** a última competência não tem as 27 unidades
**Quando** o gestor abre a tela
**Então** a posição nacional não é montada, as unidades ausentes são informadas e a série de Salvador continua visível

**Cenário 2: Competência completa**
**Dado que** as 27 unidades foram ingeridas
**Quando** o gestor abre a tela
**Então** a tela mostra a posição, as capitais e a série de Salvador

**Cenário 3: Consulta registrada**
**Dado que** o gestor abre a tela
**Quando** a consulta é feita
**Então** a abertura fica registrada com a competência usada

**Cenário 4: Importação desligada**
**Dado que** a importação da estatística está desligada
**Quando** a carga é tentada
**Então** ela falha com motivo e não gera planilha fictícia

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
