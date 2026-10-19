# HU.177 – RELATÓRIO DE PENDÊNCIAS E EXIGÊNCIAS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O gestor vê o resumo das pendências abertas, vencidas, respondidas e expiradas, o tempo médio de resposta do requerente e a lista do recorte. A lista ordena as abertas pelo prazo primeiro e pagina em 10, 15, 25 ou 50 itens. O filtro de situação é o da pendência: aberta, respondida, expirada ou cancelada. Não é a situação do processo. Cancelada entra no filtro, além dos grupos do resumo. O período recorta as datas de início e fim. O tempo médio é calculado sobre as pendências reais do recorte. A exportação repete o mesmo recorte. A consulta fica registrada. Desta tela não se abre nem se responde pendência.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Relatório de pendências e exigências](#história-de-usuário-relatório-de-pendências-e-exigências)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Pendências e exigências](#tela-1--pendências-e-exigências)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** ver as exigências e o tempo de resposta do requerente**, de modo que** eu acompanhe o que ainda está com ele e o que já voltou no período.

## História de Usuário: Relatório de pendências e exigências

**Como** gestor autorizado a consultar relatórios **quero** filtrar as pendências por situação e período**, de modo que** o recorte seja o da exigência, e não o da situação do processo.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Situação da pendência, quando filtrada, entre aberta, respondida, expirada e cancelada

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Pendências e exigências

**Caminho no menu:** Relatórios > Pendências e exigências

*[Print da tela](screenshots/gestao-pendencias.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Resumo e lista**
**Dado que** há pendências no período
**Quando** o gestor abre a tela
**Então** vê o resumo de abertas, vencidas, respondidas e expiradas, o tempo médio de resposta e a lista paginada, com as abertas ordenadas pelo prazo

**Cenário 2: Situação da pendência**
**Dado que** o gestor escolhe uma situação da pendência
**Quando** a lista atualiza
**Então** o filtro não usa a situação do processo

**Cenário 3: Situação inválida**
**Dado que** a situação informada não é aberta, respondida, expirada nem cancelada
**Quando** o filtro é aplicado
**Então** a consulta é recusada

**Cenário 4: Exportar**
**Dado que** o recorte está aplicado
**Quando** o gestor exporta
**Então** a exportação repete o mesmo período e a mesma situação da pendência

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
