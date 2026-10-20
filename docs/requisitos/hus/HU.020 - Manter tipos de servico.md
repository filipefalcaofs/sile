# HU.020 – MANTER TIPOS DE SERVIÇO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador mantém os tipos de serviço oferecidos na solicitação de viabilidade. A lista busca por código ou nome, filtra a situação, ordena por código e pagina em 10, 15, 25 ou 50 itens, com padrão de 15. Cada item mostra código, nome, pista de fluxo e situação. No cadastro, o código é livre, único e obrigatório, com até 50 caracteres. O formato oficial dos códigos ainda depende de definição da SEDUR. Tipo novo nasce ativo quando a situação não é informada. Na edição, nome, pista de fluxo e situação mudam; o código enviado é ignorado. Não há exclusão. Desativar inverte a situação: o tipo sai da seleção do requerente e o registro permanece, inclusive quando já foi usado em solicitação.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter tipos de serviço](#história-de-usuário-manter-tipos-de-serviço)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Tipos de serviço](#tela-1--tipos-de-serviço)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** manter os tipos de serviço da solicitação**, de modo que** o requerente veja só os tipos ativos.

## História de Usuário: Manter tipos de serviço

**Como** administrador autorizado a manter tipos de serviço **quero** cadastrar, corrigir e desativar um tipo**, de modo que** o código permaneça o do cadastro e o tipo inativo saia da seleção sem ser apagado.

## Pré-requisitos

- Administrador autorizado a manter tipos de serviço
- Código ainda não usado, no cadastro

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Tipos de serviço

**Caminho no menu:** Regras > Tipos de serviço

*[Print da tela](screenshots/gestao-tipos-servico.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro**
**Dado que** o administrador está autorizado e informa um código ainda não usado e o nome
**Quando** cadastra o tipo, com ou sem pista de fluxo
**Então** o tipo entra na lista e nasce ativo se a situação não for informada

**Cenário 2: Código imutável**
**Dado que** o tipo já está gravado
**Quando** a edição envia outro código
**Então** o código armazenado não muda

**Cenário 3: Desativar**
**Dado que** o tipo está ativo
**Quando** o administrador inverte a situação
**Então** o tipo fica inativo, continua na lista quando o filtro inclui inativos e não é apagado

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
