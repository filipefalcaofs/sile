# HU.070 – FILA DE TRABALHO DO ANALISTA COM SLA
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

A caixa de entrada lista os processos atribuídos a quem está autenticado, em análise ou em pendência, fora da situação de aguardando conclusão, ordenados pelo prazo da análise. Processo deferido, indeferido ou em outra situação encerrada não aparece. Processo sem responsável também não entra. Abrir a fila não muda situação e não dispara aviso de vencimento.

Os contadores do próprio usuário são: em análise com responsável, em pendência e vencendo hoje. Quem distribui processos vê, na mesma tela, a carga por analista e os processos com prazo no vermelho. Os demais não veem essa visão do setor. A caixa do setor inteiro é outra tela.

O prazo mostrado é o já calculado no encaminhamento e recalculado na distribuição ou na assunção. A redistribuição não recalcula esse prazo. A página pode ter 10, 15, 25 ou 50 itens. Filtro vazio não restringe. A consulta fica registrada como a fila do próprio usuário.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Fila de trabalho do analista com SLA](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista **quero** ver minha fila pelo prazo**, de modo que** eu trabalhe primeiro o que vence antes

## História de Usuário: Fila de trabalho do analista com SLA

**Como** analista **quero** somente os processos atribuídos a mim, em análise ou em pendência**, de modo que** a visão do setor apareça só para quem distribui

## Pré-requisitos

- Atribuição de consultar solicitações
- Processos atribuídos ao usuário, em análise ou em pendência

## Dependências

**Passadas**

- Distribuição ou assunção do processo, que grava o prazo exibido

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Caixa de entrada

**Caminho no menu:** Operação > Caixa de entrada

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Minha fila**
**Dado que** o usuário consulta solicitações e tem processos atribuídos
**Quando** abre a caixa de entrada
**Então** vê só os seus, em análise ou em pendência, fora de aguardando conclusão, ordenados pelo prazo, e a consulta fica registrada

**Cenário 2: Quem distribui**
**Dado que** o usuário também distribui processos
**Quando** abre a mesma tela
**Então** vê ainda a carga por analista e os processos com prazo no vermelho

**Cenário 3: Quem não distribui**
**Dado que** o usuário não distribui processos
**Quando** abre a fila
**Então** não vê a visão do setor

**Cenário 4: Abrir não altera o processo**
**Dado que** a fila está na tela
**Quando** o analista apenas consulta
**Então** nenhuma situação é alterada e nenhum aviso de vencimento é disparado por esta tela

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
