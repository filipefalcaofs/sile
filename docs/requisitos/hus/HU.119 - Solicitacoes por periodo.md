# HU.119 – SOLICITAÇÕES POR PERÍODO
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

O gestor vê quantas solicitações foram protocoladas em cada dia do recorte. Entram só processos protocolados, não rascunhos. Dia sem protocolo não aparece, e a lista pode vir vazia. Os filtros são período, setor, analista, bairro, categoria e atividade. A consulta fica na trilha de relatórios.

Data inicial posterior à final não consulta: a tela devolve séries vazias e avisa que o período está invertido, sem trocar as datas em silêncio. Nesse caso, as taxas ficam com total zero e sem percentual. A exportação da tela baixa as solicitações do recorte, não um gráfico.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Solicitações por período](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** ver quantas solicitações entraram em cada dia**, de modo que** eu leia a demanda do período

## História de Usuário: Solicitações por período

**Como** gestor **quero** a série diária só de processos protocolados**, de modo que** rascunho e dia vazio não inflam o gráfico

## Pré-requisitos

- Atribuição de consultar relatórios

## Dependências

**Passadas**

- Solicitações protocoladas

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Solicitações por período

**Caminho no menu:** Indicadores > Indicadores de viabilidade

*[Print da tela](screenshots/gestao-indicadores.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Recorte válido**
**Dado que** há protocolos no período informado
**Quando** o gestor consulta
**Então** vê uma linha por dia com protocolo, só dos dias que tiveram entrada, e a consulta fica na trilha

**Cenário 2: Período invertido**
**Dado que** a data inicial é posterior à final
**Quando** o gestor consulta
**Então** a tela avisa o período invertido, não lista dia inventado e não troca as datas

**Cenário 3: Exportar**
**Dado que** o gestor pede o arquivo na mesma tela
**Quando** a exportação ocorre
**Então** o arquivo traz as solicitações do recorte, não o desenho do gráfico

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
