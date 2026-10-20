# HU.120 – SOLICITAÇÕES POR ZONA
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

Nesta tela de indicadores, a série chamada “por zona” não agrupa a zona urbanística. Ela agrupa o bairro informado no processo e declara a substituição com o texto “Zona urbanística indisponível — agrupado por bairro”. Solicitação sem bairro não entra num grupo vazio. A zona oficial já gravada no processo não é a chave desta série. O sistema não inventa zona para completar a lista.

Período invertido devolve a lista vazia com o mesmo aviso. A zona oficial consultável está no painel por bairro, não aqui. Os filtros são os da tela de indicadores.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Solicitações por zona](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** ver a concentração territorial das solicitações**, de modo que** eu saiba que, nesta tela, o recorte é o bairro e não a zona

## História de Usuário: Solicitações por zona

**Como** gestor **quero** ler o aviso de que a zona não entrou na agregação**, de modo que** o bairro informado não seja tomado por zona urbanística

## Pré-requisitos

- Atribuição de consultar relatórios

## Dependências

**Passadas**

- Solicitações protocoladas com bairro informado

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Série territorial dos indicadores

**Caminho no menu:** Indicadores > Indicadores de viabilidade. A zona oficial consultável fica em Relatórios > Painel por bairro.

*[Print da tela](screenshots/gestao-indicadores.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Agrupamento por bairro**
**Dado que** há solicitações protocoladas com bairro
**Quando** o gestor abre a série por zona
**Então** a lista traz bairro e total, com o aviso de que a zona urbanística está indisponível e o agrupamento é por bairro

**Cenário 2: Sem bairro**
**Dado que** a solicitação não tem bairro
**Quando** a série é montada
**Então** ela fica de fora e não vira um grupo vazio

**Cenário 3: Zona não inventada**
**Dado que** o processo não tem zona identificada nesta série
**Quando** a lista é exibida
**Então** nenhuma zona é atribuída para completar o número

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
