# HU.174 – CONSULTAR SLA E VENCIMENTOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O gestor vê os processos em andamento que têm prazo, do mais urgente ao menos urgente, com resumo, tempo de permanência, atraso por etapa e cumprimento no período. A lista e o estoque usam setor e analista. As datas do pedido não filtram essa lista: o estoque é o momento atual. O período recorta só o cumprimento. Processo já decidido não entra no estoque. O semáforo e o tempo de permanência saem do prazo já gravado no processo. A lista pagina em 10, 15, 25 ou 50 itens. A exportação respeita setor e analista. A consulta fica registrada. Desta tela não se altera prazo nem se redistribui processo. A lista de analistas do filtro reúne quem analisa processos.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Consultar SLA e vencimentos](#história-de-usuário-consultar-sla-e-vencimentos)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — SLA e vencimentos](#tela-1--sla-e-vencimentos)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** ver o que está vencido ou por vencer**, de modo que** o prazo da análise em curso seja acompanhado pelo setor e pelo analista.

## História de Usuário: Consultar SLA e vencimentos

**Como** gestor autorizado a consultar relatórios **quero** filtrar o estoque por setor e analista e o cumprimento por período**, de modo que** a data do pedido não esvazie a lista do que está em andamento.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Processos em andamento com prazo gravado

## Dependências

**Passadas**

- O semáforo é o mesmo prazo usado na fila de análise

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — SLA e vencimentos

**Caminho no menu:** Indicadores > SLA e vencimentos

*[Print da tela](screenshots/gestao-sla.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Lista do mais urgente**
**Dado que** há processos em andamento com prazo
**Quando** o gestor abre a tela
**Então** a lista mostra esses processos, mais urgentes primeiro, com resumo, tempo de permanência e atraso por etapa

**Cenário 2: Período no cumprimento**
**Dado que** o gestor informa o período
**Quando** a tela atualiza
**Então** o cumprimento muda e o estoque em andamento não é esvaziado pela data da decisão

**Cenário 3: Exportar**
**Dado que** setor e analista estão no filtro
**Quando** o gestor exporta
**Então** a exportação respeita esse setor e esse analista

**Cenário 4: Consulta registrada**
**Dado que** o gestor consulta a tela
**Quando** a lista é carregada
**Então** a consulta fica registrada

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
