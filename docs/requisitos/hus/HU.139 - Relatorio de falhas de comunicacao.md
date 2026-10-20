# HU.139 – RELATÓRIO DE FALHAS DE COMUNICAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O gestor vê as comunicações que falharam ou foram bloqueadas, por processo e por canal, sem o destinatário. O resumo separa os canais. Os filtros são o período e o canal: e-mail, aviso no sistema ou WhatsApp. Canal diferente desses três é recusado. Entrega com sucesso não entra na lista. A linha e a exportação não trazem e-mail, telefone nem nome do destinatário. A lista pagina em 10, 15, 25 ou 50 itens. Desta tela não se reenvia a mensagem. A consulta fica registrada.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Relatório de falhas de comunicação](#história-de-usuário-relatório-de-falhas-de-comunicação)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Falhas de comunicação](#tela-1--falhas-de-comunicação)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** ver as notificações não entregues no período**, de modo que** eu saiba o processo e o canal sem receber o destinatário.

## História de Usuário: Relatório de falhas de comunicação

**Como** gestor autorizado a consultar relatórios **quero** filtrar falha e bloqueio por canal**, de modo que** a lista e a exportação não mostrem e-mail, telefone ou nome de quem deveria receber.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Canal, quando filtrado, entre e-mail, aviso no sistema e WhatsApp

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Falhas de comunicação

**Caminho no menu:** Relatórios > Falhas de comunicação

*[Print da tela](screenshots/gestao-falhas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Falha ou bloqueio**
**Dado que** há comunicações não entregues no período
**Quando** o gestor abre a lista
**Então** vê falha ou bloqueio, o processo e o canal, e o resumo separado por canal

**Cenário 2: Sem destinatário**
**Dado que** a lista ou a exportação é gerada
**Quando** o gestor lê a linha
**Então** não há e-mail, telefone nem nome do destinatário

**Cenário 3: Canal inválido**
**Dado que** o canal informado não é e-mail, aviso no sistema nem WhatsApp
**Quando** o filtro é aplicado
**Então** a consulta é recusada

**Cenário 4: Consulta registrada**
**Dado que** o gestor abre o relatório
**Quando** a consulta é feita
**Então** a abertura fica registrada e a mensagem não é reenviada

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
