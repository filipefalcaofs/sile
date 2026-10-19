# HU.071 – VISUALIZAR DAM DA SOLICITAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não oferece a visualização do DAM da solicitação. Não há tela com número da guia, valor, vencimento, código de barras ou documento para pagamento, nem consulta dessa guia na SEFAZ.

O que existe ao lado não é o DAM. A ficha de análise pode mostrar o valor da taxa de licenciamento, quando o cadastro de valores do exercício está vigente, ou o texto de que a taxa está pendente. No deferimento, o envio da viabilidade à SEFAZ pode registrar esse valor, mas o canal está indisponível e não apresenta guia. Protocolar e decidir não dependem de DAM.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Visualizar DAM da solicitação](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente ou o analista **quero** visualizar o DAM da solicitação**, de modo que** eu acompanhe a guia de arrecadação no processo

## História de Usuário: Visualizar DAM da solicitação

**Como** requerente ou analista **quero** ver número, valor, vencimento e o documento da guia**, de modo que** a arrecadação do pedido fique visível no processo

## Pré-requisitos

- O sistema ainda não oferece esta consulta
- Não há quem possa abrir uma tela de DAM, porque ela não existe

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Consulta da guia na SEFAZ, quando esse serviço estiver disponível para a SEDUR

---

## Interface de Usuário

### Tela 1 — Visualizar DAM da solicitação

**Caminho no menu:** O sistema ainda não oferece esta tela

#### Comportamento Esperado Tela 1

**Cenário 1: Guia não disponível**
**Dado que** o requerente ou o analista procura o DAM da solicitação
**Quando** abre o processo
**Então** o sistema ainda não oferece número, situação, vencimento nem documento da guia

**Cenário 2: Taxa na ficha de análise**
**Dado que** a ficha de análise mostra o valor da taxa ou o aviso de taxa pendente
**Quando** o analista lê esse valor
**Então** isso não é um DAM e não substitui a guia

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
