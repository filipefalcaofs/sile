# HU.181 – RELATÓRIO DE ESCRITÓRIO VIRTUAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O gestor localiza, pela inscrição imobiliária travada, a sede do escritório virtual e os processos abrigados. O recorte da tela é o número da viabilidade da sede ou a inscrição. Os demais filtros do pedido não entram nesta lista. A linha mostra a sede e quem a decisão marcou como abrigado, apontando o número da viabilidade da sede. Sem sede nem inscrição, a consulta segue sem inventar inscrição. A lista pagina em 10, 15, 25 ou 50 itens. A exportação comum mantém o agrupamento de sede e abrigados. A exportação pedida como lista de sedes sai plana, com empresa, CNPJ, bairro e resultado no período. Desta tela não se cria nem se encerra sede. A consulta fica registrada.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Relatório de escritório virtual](#história-de-usuário-relatório-de-escritório-virtual)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Escritório virtual](#tela-1--escritório-virtual)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** ver a sede e quem está abrigado na mesma inscrição**, de modo que** o vínculo seja conferido pelo número da viabilidade da sede ou pela inscrição.

## História de Usuário: Relatório de escritório virtual

**Como** gestor autorizado a consultar relatórios **quero** localizar a sede e os abrigados**, de modo que** a exportação agrupada e a lista plana de sedes sejam escolhas distintas.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Sede com inscrição travada e abrigados marcados pela decisão

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Escritório virtual

**Caminho no menu:** Relatórios > Escritório virtual (sede × abrigados)

*[Print da tela](screenshots/gestao-ev-rel.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Sede e abrigados**
**Dado que** há sede com inscrição travada
**Quando** o gestor consulta pelo número da viabilidade ou pela inscrição
**Então** a linha mostra a sede e os abrigados dessa inscrição

**Cenário 2: Exportação agrupada**
**Dado que** o recorte está aplicado
**Quando** o gestor exporta sem pedir a lista de sedes
**Então** a exportação mantém o agrupamento de sede e abrigados

**Cenário 3: Lista plana de sedes**
**Dado que** o gestor pede a exportação das sedes
**Quando** a exportação é gerada
**Então** sai a lista plana com empresa, CNPJ, bairro e resultado no período

**Cenário 4: Consulta registrada**
**Dado que** o gestor abre o relatório
**Quando** a consulta é feita
**Então** a abertura fica registrada e nenhuma sede é criada ou encerrada

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
