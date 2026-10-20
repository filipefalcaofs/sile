# HU.129 – CONSULTAR SALA DE CONTROLE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O gestor abre a sala de controle com o recorte de datas e vê, numa tela, o tempo de viabilidade, o volume decidido, a automação, a distância para a referência federal, a série de Salvador e os gargalos. O bloco de operação conta processos com decisão no período, separa fluxo expresso e semi-expresso e calcula a taxa de automação como a parte expressa sobre o total. Sem decisão no período, a taxa não é zero: fica sem valor e o total é zero. O período recorta a data da decisão, não a data do protocolo. Se a referência federal não tiver competência completa, os outros blocos continuam na tela. A sala não decide processo e não exporta o recorte. A abertura fica registrada, com o período usado.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Consultar sala de controle](#história-de-usuário-consultar-sala-de-controle)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Sala de Controle](#tela-1--sala-de-controle)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** ver o desempenho do período numa única tela**, de modo que** eu não precise abrir cada relatório para ler tempo, volume e gargalo.

## História de Usuário: Consultar sala de controle

**Como** gestor autorizado a consultar relatórios **quero** abrir a sala de controle com o recorte de datas**, de modo que** a falta da referência federal não apague o restante da tela.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Período informado pelas datas de início e fim, quando o recorte for usado

## Dependências

**Passadas**

- Os blocos reúnem indicadores já existentes de tempo, operação, referência federal, evolução e gargalos

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Sala de Controle

**Caminho no menu:** Indicadores > Sala de Controle

*[Print da tela](screenshots/gestao-sala.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Visão do período**
**Dado que** o gestor está autorizado
**Quando** abre a sala com o período
**Então** a tela mostra tempo, operação, distância para a referência federal, evolução de Salvador e gargalos

**Cenário 2: Período sem decisão**
**Dado que** não há decisão no período
**Quando** a sala é aberta
**Então** a taxa de automação fica sem valor e o total decidido é zero

**Cenário 3: Referência federal incompleta**
**Dado que** a competência federal não está completa
**Quando** a sala é aberta
**Então** os demais blocos continuam visíveis

**Cenário 4: Consulta registrada**
**Dado que** o gestor abre a sala
**Quando** a consulta é feita
**Então** a abertura fica registrada com o período usado

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
