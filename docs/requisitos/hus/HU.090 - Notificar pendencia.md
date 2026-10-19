# HU.090 – NOTIFICAR PENDÊNCIA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quando o analista abre um convite, o requerente é avisado pelos canais ligados para esse aviso. No padrão, são e-mail e o aviso interno do portal. O WhatsApp não entra neste aviso. A abertura do convite e a ida para pendência já ocorreram antes: falha de canal não desfaz o convite.

Sem requerente, nada é criado e a falta de destinatário fica registrada. E-mail vazio ou inválido impede qualquer canal, inclusive o aviso interno. Canal ligado entra na fila e depois fica enviado ou falhou. Canal desligado fica desativado, com registro, e não é marcado como enviado. O requerente não escolhe o canal. O sistema ainda não oferece envio por WhatsApp neste aviso.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico)
- [História de Usuário: Notificar pendência](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** o requerente **quero** ser avisado quando a análise abre um convite**, de modo que** eu responda no portal dentro do prazo

## História de Usuário: Notificar pendência

**Como** requerente com e-mail **quero** receber o aviso do convite nos canais ligados**, de modo que** canal desligado não apareça como enviado

## Pré-requisitos

- Convite já aberto
- Requerente com e-mail utilizável
- Canal de e-mail ou aviso interno ligado para este aviso

## Dependências

**Passadas**

- HU.083 — Solicitar pendência

**Futuras**

- HU.091 — Responder pendência

---

## Interface de Usuário

### Tela 1 — Notificar pendência

**Caminho no menu:** Início > Notificações

*[Print da tela](screenshots/portal-notificacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Aviso enfileirado**
**Dado que** o convite é aberto para um processo com requerente e e-mail utilizável
**Quando** o aviso é tratado
**Então** nascem as comunicações dos canais ligados, na fila, e o aviso é disparado

**Cenário 2: Canal desligado**
**Dado que** um canal do aviso está desligado
**Quando** o convite é aberto
**Então** esse canal fica desativado, com registro, e não é marcado como enviado

**Cenário 3: Sem requerente**
**Dado que** o processo não tem requerente
**Quando** o convite é aberto
**Então** não nasce comunicação e a falta de destinatário fica registrada

**Cenário 4: Uma abertura, um aviso**
**Dado que** o convite é aberto uma vez
**Quando** o aviso é tratado
**Então** o requerente não recebe dois avisos da mesma abertura

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
