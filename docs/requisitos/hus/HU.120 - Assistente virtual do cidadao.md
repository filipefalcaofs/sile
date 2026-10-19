# HU.120 – ASSISTENTE VIRTUAL DO CIDADÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não oferece conversa do cidadão com um assistente. Não há tela para enviar pergunta, histórico de conversa nem resposta. Ligar a função de assistente, por si, não abre um diálogo. O resumo da solicitação e a explicação da decisão são cartões de sugestão, não um assistente de perguntas. A busca por semelhança de casos, quando existe, também não é conversa.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Assistente virtual do cidadão](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** cidadão **quero** tirar dúvidas no portal com um assistente quando esse serviço existir**, de modo que** resumo e explicação da decisão não sejam apresentados como conversa

## História de Usuário: Assistente virtual do cidadão

**Como** cidadão **quero** saber que ainda não há diálogo no portal**, de modo que** eu não espere resposta de uma tela que não existe

## Pré-requisitos

- Nenhum, porque a conversa não está disponível

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Assistente do cidadão

**Caminho no menu:** Não há tela no portal para conversa com assistente.

#### Comportamento Esperado Tela 1

**Cenário 1: Perguntar no portal**
**Dado que** o cidadão procura um assistente para tirar dúvidas
**Quando** percorre o portal
**Então** nenhuma página oferece conversa e o sistema ainda não envia nem responde este serviço

**Cenário 2: Função ligada**
**Dado que** a função de assistente é ligada na configuração
**Quando** o cidadão volta ao portal
**Então** isso, por si, não abre um diálogo

**Cenário 3: Outros textos**
**Dado que** o requerente vê o resumo da solicitação ou a explicação da decisão
**Quando** lê esses cartões
**Então** eles continuam sendo sugestão e não uma conversa

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
