# HU.187 – MANTER REGRAS TERRITORIAIS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O mantenedor ajusta uma regra territorial que já existe e publica o rascunho com outro mantenedor. A manutenção fica na aba territoriais do Motor de regras. Não há item de menu próprio. Quem só consulta a LOUOS ou os CNAEs lê o hub e não grava. A alteração de título, situação, código do logradouro, nome do logradouro, camada, filtro, zona alvo, motivo de análise, texto de decisão, base legal, CNAEs e endereços fica no rascunho. Tipo e efeito não mudam. Código que não existe em nenhuma versão territorial é recusado e não cria regra nova. A vigente não é editada no lugar. Publicar exige publicador diferente do autor. O autor recebe a mensagem de que a publicação por quatro olhos exige outra pessoa. Outro mantenedor publica e a versão entra em uso. Reativar só vale para versão já publicada: rascunho pede publicação antes. A vigente anterior é encerrada, não apagada. Descartar o rascunho apaga as regras dele e o próprio rascunho; a vigente permanece. Versão de outro assunto é recusada. Publicar ou descartar o que não é rascunho informa que a operação exige rascunho. Cada CNAE informado tem os 7 dígitos da subclasse, sem máscara, e não se repete. A aplicação da regra no processo é outro fluxo.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter regras territoriais](#história-de-usuário-manter-regras-territoriais)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Regras territoriais](#tela-1--regras-territoriais)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** mantenedor da LOUOS **quero** ajustar a restrição territorial vigente só pela publicação**, de modo que** logradouro, camada ou lista permitida mudem quando outro mantenedor publicar o rascunho.

## História de Usuário: Manter regras territoriais

**Como** mantenedor autorizado a manter a LOUOS **quero** ajustar título, alvos, CNAEs e endereços de uma regra já existente e publicar o rascunho**, de modo que** a versão vigente continue a anterior até a publicação por outra pessoa.

## Pré-requisitos

- Mantenedor autorizado a manter a LOUOS
- Código de regra territorial já versionado
- Título, situação e, quando enviados, CNAEs de 7 dígitos sem repetição
- Outro mantenedor, diferente do autor do rascunho, para publicar

## Dependências

**Passadas**

- A regra já existe em alguma versão territorial

**Futuras**

- A aplicação da restrição no processo continua nos fluxos de identificação e de restrição territorial

---

## Interface de Usuário

### Tela 1 — Regras territoriais

**Caminho no menu:** Regras > Motor de regras, aba territoriais

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Gravar no rascunho**
**Dado que** o código da regra já existe e o mantenedor está autorizado
**Quando** ele envia título, situação, CNAEs e endereços
**Então** a alteração fica no rascunho, tipo e efeito não mudam, e a vigente continua a anterior até a publicação

**Cenário 2: Código inexistente**
**Dado que** o código não existe em nenhuma versão territorial
**Quando** o mantenedor tenta gravar
**Então** a gravação é recusada e nenhuma regra nova é criada

**Cenário 3: Publicação pelo autor**
**Dado que** o rascunho territorial foi aberto pelo próprio usuário
**Quando** esse usuário publica
**Então** a publicação é recusada

**Cenário 4: Publicação por outro mantenedor**
**Dado que** outro mantenedor autorizado abre o rascunho
**Quando** publica
**Então** a versão passa a vigorar

**Cenário 5: Descarte**
**Dado que** há rascunho territorial aberto
**Quando** o mantenedor descarta
**Então** o rascunho e as regras dele saem, e a versão vigente permanece

**Cenário 6: Reativar rascunho**
**Dado que** a versão ainda é rascunho
**Quando** o mantenedor tenta reativá-la
**Então** a mensagem pede que ela seja publicada antes

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
