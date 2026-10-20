# HU.012 – MANTER TERMOS LEGAIS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador versiona os termos legais. O termo publicado não é alterado nem apagado. A lista, sem paginação, ordena por tipo e pela versão mais nova. Cada linha mostra tipo, versão, título, conteúdo, data de publicação e situação: rascunho, vigente ou substituído. Vigente é a maior versão já publicada daquele tipo cuja data de publicação já chegou. Incluir cria rascunho. A versão é a seguinte do tipo; o administrador não informa o número. Editar título e conteúdo só vale para rascunho. Termo publicado responde que termos publicados são imutáveis e que é preciso criar uma nova versão. Publicar grava a data atual. Publicar de novo um termo já publicado devolve a mesma orientação. Excluir só remove rascunho. Publicar torna a versão a vigente para novos aceites. Aceites anteriores permanecem na versão antiga, e quem ainda não aceitou a nova versão volta a ser cobrado. O aceite em si não ocorre nesta tela.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter termos legais](#história-de-usuário-manter-termos-legais)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Termos legais](#tela-1--termos-legais)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** publicar cada versão do termo sem reescrever a anterior**, de modo que** o aceite continue apontando para o texto que a pessoa viu.

## História de Usuário: Manter termos legais

**Como** administrador autorizado a manter os parâmetros **quero** redigir um rascunho, publicá-lo e abrir a versão seguinte quando o texto mudar**, de modo que** o termo publicado permaneça imutável.

## Pré-requisitos

- Administrador autorizado a manter os parâmetros
- Tipo do termo em letras minúsculas, números e sublinhado, com até 50 caracteres
- Título e conteúdo informados

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- O aceite do termo no login do console é outro fluxo, fora desta tela

---

## Interface de Usuário

### Tela 1 — Termos legais

**Caminho no menu:** Configuração > Termos legais

*[Print da tela](screenshots/gestao-termos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Próxima versão**
**Dado que** um tipo já tem a versão 2
**Quando** o administrador cria outro rascunho desse tipo
**Então** o rascunho nasce como versão 3, sem data de publicação

**Cenário 2: Publicado imutável**
**Dado que** o termo já foi publicado
**Quando** o administrador tenta editar ou excluir
**Então** o registro permanece e a mensagem pede uma nova versão

**Cenário 3: Publicar rascunho**
**Dado que** existe um rascunho
**Quando** o administrador publica
**Então** a data de publicação é gravada e, se for a maior versão publicada do tipo, a situação da lista passa a vigente

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
