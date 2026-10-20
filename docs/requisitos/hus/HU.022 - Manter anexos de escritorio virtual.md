# HU.022 – MANTER ANEXOS DE ESCRITÓRIO VIRTUAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O mantenedor publica, com um segundo usuário, os Anexos A e B do Decreto nº 35.062/2021, que definem os CNAEs de sede e de abrigado. A página mostra a versão vigente, com data, quem publicou, fonte e totais dos dois anexos, o rascunho aberto e o histórico. A versão sugerida no formulário junta a data do dia. Importar recebe versão, fonte e as duas planilhas, uma de cada anexo. Cada planilha traz o código e a descrição do CNAE, em texto, com até 5 MB. A versão usa letras minúsculas, números e hífen, como no exemplo ev-anexos-2026-09-18. A importação grava rascunho e informa quantos CNAEs entraram em cada anexo. A lista vigente não muda nesse momento. Falha de formato volta a mensagem da importação. Versão já publicada é recusada. Publicar exige outro mantenedor, diferente de quem importou. Sem rascunho aberto com essa versão, a publicação informa a ausência. Os dois anexos sobem juntos. O motor lê a lista vigente. O rascunho não autoriza CNAE antes da publicação. Não há edição de um CNAE linha a linha.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter anexos de escritório virtual](#história-de-usuário-manter-anexos-de-escritório-virtual)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Anexos de escritório virtual](#tela-1--anexos-de-escritório-virtual)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** mantenedor de CNAEs **quero** publicar os anexos de sede e de abrigado**, de modo que** a lista vigente do escritório virtual mude só na publicação feita por outra pessoa.

## História de Usuário: Manter anexos de escritório virtual

**Como** mantenedor autorizado a manter CNAEs **quero** importar as duas planilhas para um rascunho, ver a diferença e publicar com outro usuário**, de modo que** a versão vigente permaneça até essa publicação.

## Pré-requisitos

- Mantenedor autorizado a manter CNAEs
- Versão nova, em letras minúsculas, números e hífen, com até 60 caracteres
- Fonte informada e as duas planilhas, Anexo A e Anexo B
- Outro mantenedor, diferente do autor do rascunho, para publicar

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Anexos de escritório virtual

**Caminho no menu:** Regras > Anexos escritório virtual

*[Print da tela](screenshots/gestao-anexos-ev.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Rascunho sem trocar a vigente**
**Dado que** existe uma versão vigente e o mantenedor está autorizado
**Quando** importa as duas planilhas numa versão nova
**Então** nasce rascunho com a contagem de cada anexo e a vigente permanece

**Cenário 2: Publicação pelo autor**
**Dado que** o usuário é o autor do rascunho
**Quando** ele tenta publicar
**Então** a publicação é recusada

**Cenário 3: Publicação por outro mantenedor**
**Dado que** outro mantenedor autorizado a manter CNAEs abre o rascunho
**Quando** publica
**Então** a mensagem confirma a atualização dos anexos vigentes

**Cenário 4: Versão mal formada**
**Dado que** a versão tem letra maiúscula ou espaço
**Quando** a importação é enviada
**Então** a versão é recusada

**Cenário 5: Sem rascunho**
**Dado que** a versão não está em rascunho
**Quando** alguém tenta publicar esse nome
**Então** a publicação informa que não há rascunho aberto com essa versão

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
