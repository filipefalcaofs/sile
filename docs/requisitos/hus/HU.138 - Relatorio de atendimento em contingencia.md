# HU.138 – RELATÓRIO DE ATENDIMENTO EM CONTINGÊNCIA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O gestor vê quanto do volume protocolado no período entrou pelo atendimento de contingência e por quais motivos. O resumo mostra a participação sobre o total protocolado, não só sobre os atendimentos de contingência, e o ranking de motivos. A lista é paginada em 10, 15, 25 ou 50 itens. O filtro da tela é o período, com data de início e data de fim. A exportação repete o mesmo recorte. O relatório não abre novo atendimento. A consulta fica registrada.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Relatório de atendimento em contingência](#história-de-usuário-relatório-de-atendimento-em-contingência)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Atendimento em contingência](#tela-1--atendimento-em-contingência)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** medir o canal do operador no período**, de modo que** a participação seja lida sobre tudo o que foi protocolado, não só sobre a contingência.

## História de Usuário: Relatório de atendimento em contingência

**Como** gestor autorizado a consultar relatórios **quero** ver o volume e os motivos do atendimento de contingência**, de modo que** eu compare esse canal com o total protocolado no período.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Solicitações protocoladas no período, quando o recorte for usado

## Dependências

**Passadas**

- O registro do atendimento de contingência ocorre na tela de nova solicitação em contingência

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Atendimento em contingência

**Caminho no menu:** Relatórios > Atendimento em contingência

*[Print da tela](screenshots/gestao-cont-rel.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Resumo, motivos e lista**
**Dado que** há protocolos no período
**Quando** o gestor abre o relatório
**Então** a tela mostra a participação sobre o total protocolado, o ranking de motivos e a lista do período

**Cenário 2: Exportar**
**Dado que** o período está aplicado
**Quando** o gestor exporta
**Então** a exportação repete o mesmo recorte

**Cenário 3: Consulta registrada**
**Dado que** o gestor abre o relatório
**Quando** a consulta é feita
**Então** a abertura fica registrada e nenhum atendimento novo é aberto

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
