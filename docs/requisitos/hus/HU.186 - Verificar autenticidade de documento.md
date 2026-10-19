# HU.186 – VERIFICAR AUTENTICIDADE DE DOCUMENTO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Qualquer pessoa confere, pelo código impresso, se uma viabilidade locacional, um indeferimento ou um comprovante de protocolo foi emitido pelo sistema. Não é preciso entrar. O limite de consultas é o mesmo da consulta pública de protocolo. Código desconhecido ou adulterado aparece como inválido, sem tipo, protocolo nem data, e não revela dado cadastral de um número parecido. Código de viabilidade devolve o tipo Viabilidade locacional, o protocolo e a data de referência. Código de indeferimento devolve o tipo Documento de Indeferimento. Código de comprovante devolve o tipo Comprovante de Protocolo. A página mostra só o código e esse resultado. Não vão empresa, CNPJ nem endereço. Não se baixa o documento completo. Outro tipo de documento não é confirmado. Cada consulta fica registrada, mesmo sem usuário logado, com o código, a validade e o tipo.

---

## Sumário

- [Épico: EP13 — Integrações](#épico-ep13--integrações)
- [História de Usuário: Verificar autenticidade de documento](#história-de-usuário-verificar-autenticidade-de-documento)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Verificação do documento](#tela-1--verificação-do-documento)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** portador do documento **quero** conferir o código impresso**, de modo que** eu saiba se ele foi emitido pelo sistema sem ver dados da empresa.

## História de Usuário: Verificar autenticidade de documento

**Como** qualquer pessoa **quero** informar o código de verificação**, de modo que** o resultado diga se a viabilidade, o indeferimento ou o comprovante confere, sem login.

## Pré-requisitos

- Código impresso no documento
- Documento que seja viabilidade locacional, indeferimento ou comprovante de protocolo

## Dependências

**Passadas**

- O documento já foi emitido pelo sistema

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Verificação do documento

**Caminho no menu:** Página pública de verificação, sem login e sem item de menu.

*[Print da tela](screenshots/verificar-documento.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Código válido**
**Dado que** o código é de uma viabilidade, de um indeferimento ou de um comprovante emitido
**Quando** a pessoa consulta
**Então** a página mostra o tipo, o protocolo e a data, e informa que o documento é válido

**Cenário 2: Código inexistente**
**Dado que** o código não existe ou foi adulterado
**Quando** a pessoa consulta
**Então** o resultado é inválido, sem tipo, protocolo, data nem dado cadastral

**Cenário 3: Consulta sem login**
**Dado que** ninguém está autenticado
**Quando** a consulta é feita
**Então** a verificação entra na trilha com o código, a validade e o tipo

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
