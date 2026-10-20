# HU.112 – CLASSIFICAR DOCUMENTOS
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

A classificação sugere o tipo do anexo e pode alertar se a categoria não confere com a exigência. O alerta não rejeita o protocolo, não remove o anexo e não decide o processo. Enviar o documento não dispara essa classificação. Não há botão no fluxo que a chame. A ficha de análise só exibe sugestão que já esteja registrada.

Com a função desligada ou sem provedor de visão, nenhuma categoria é fabricada. Com a função ligada, o alerta de divergência fica na sugestão.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Classificar documentos](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** analista **quero** ser alertado se o anexo não parece corresponder à exigência**, de modo que** eu revise o documento sem o protocolo ser recusado por isso

## História de Usuário: Classificar documentos

**Como** analista **quero** que o alerta de categoria, quando existir, seja só uma sugestão**, de modo que** o envio do documento continue válido

## Pré-requisitos

- Função de classificação ligada e provedor de visão ativo, para haver sugestão
- Documento e solicitação já gravados

## Dependências

**Passadas**

- Anexo já enviado

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Alerta de classificação

**Caminho no menu:** Não há tela que dispare a classificação. A ficha de análise só mostra sugestão já registrada.

*[Print da tela](screenshots/gestao-ia.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Categoria divergente**
**Dado que** uma sugestão de classificação já aponta categoria diferente da exigência
**Quando** o analista vê o alerta
**Então** a situação da solicitação não muda e o anexo permanece

**Cenário 2: Função desligada**
**Dado que** a classificação está desligada ou não há provedor de visão
**Quando** se espera uma categoria
**Então** nenhuma sugestão é fabricada

**Cenário 3: Envio do documento**
**Dado que** o cidadão anexa um arquivo
**Quando** o protocolo segue
**Então** esta classificação não é disparada

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
