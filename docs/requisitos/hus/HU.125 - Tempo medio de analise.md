# HU.125 – TEMPO MÉDIO DE ANÁLISE
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

O gestor vê, no mesmo recorte, o tempo por etapa, o tempo agregado de emissão do documento de viabilidade, os percentis do período, os percentis por categoria e os gargalos. Não é uma média única. A lista processo a processo da emissão fica em outra tela. A exportação desta tela é o detalhe por etapa, em CSV, XLSX ou PDF habilitados, e não a lista de sedes de escritório virtual. Os filtros são datas, setor, analista, bairro, atividade e categoria. A consulta fica registrada.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Tempo médio de análise](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** ver onde o tempo de análise se concentra**, de modo que** eu compare etapas e não só uma média única

## História de Usuário: Tempo médio de análise

**Como** gestor **quero** etapa, emissão agregada, percentis e gargalos no mesmo recorte**, de modo que** o detalhe da emissão documento a documento fique na tela própria

## Pré-requisitos

- Atribuição de consultar relatórios

## Dependências

**Passadas**

- Processos com tempo de etapa e de emissão já apurado

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Tempo de análise

**Caminho no menu:** Indicadores > Tempo de análise. O detalhe processo a processo da emissão fica em Indicadores > Emissão da viabilidade.

*[Print da tela](screenshots/gestao-tempo.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Medidas do recorte**
**Dado que** o gestor informa o recorte
**Quando** abre o tempo de análise
**Então** vê etapa, emissão agregada, percentis e gargalos do mesmo filtro, e a consulta fica registrada

**Cenário 2: Exportar**
**Dado que** o gestor pede CSV, XLSX ou PDF
**Quando** a exportação ocorre
**Então** o arquivo é o detalhe por etapa e não a lista de escritório virtual

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
