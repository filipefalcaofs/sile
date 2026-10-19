# HU.096 – CONSULTAR HISTÓRICO DE COMUNICAÇÕES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O histórico lista, por processo, as comunicações já registradas, da mais recente para a mais antiga, com o destinatário e a situação real de cada canal: na fila, enviado, falhou, bloqueado ou desativado. Entram e-mail, aviso interno e, se houver linha, WhatsApp. A consulta não recalcula envio e não reenvia. WhatsApp bloqueado continua bloqueado.

O requerente vê as comunicações da própria solicitação, sem a mensagem interna de erro. Quem consulta solicitações na gestão vê as mesmas linhas e o erro do canal, para diagnosticar a falha sem procurar em outro lugar. Cada consulta fica registrada, com a origem e a quantidade. E-mail de conta, sem solicitação associada, não entra nesta lista. Processo de outro requerente não abre no portal. Processo inexistente não abre.

O sistema ainda não oferece reenvio a partir desta tela nem o histórico de e-mails de conta que não têm solicitação.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico)
- [História de Usuário: Consultar histórico de comunicações](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** o requerente **quero** ver as comunicações da minha solicitação**, de modo que** eu saiba o que foi registrado em cada canal

## História de Usuário: Consultar histórico de comunicações

**Como** usuário da gestão que consulta solicitações **quero** ver o mesmo histórico com a mensagem de erro do canal**, de modo que** eu diagnostique falha sem sair do processo

## Pré-requisitos

- Solicitação existente
- Requerente da solicitação, no portal
- Quem consulta solicitações, na gestão

## Dependências

**Passadas**

- HU.094 — Enviar e-mail
- HU.090 — Notificar pendência

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Consultar histórico de comunicações

**Caminho no menu:** Operação > Consulta de processos

*[Print da tela](screenshots/gestao-notificacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Visão do requerente**
**Dado que** o requerente autorizado abre as comunicações do próprio processo
**Quando** a lista é exibida
**Então** vê o que foi registrado, sem a mensagem interna de erro

**Cenário 2: Visão da gestão**
**Dado que** quem consulta solicitações abre as comunicações do processo
**Quando** a lista é exibida
**Então** vê as mesmas linhas e o erro interno do canal

**Cenário 3: Situação real**
**Dado que** há comunicação bloqueada ou desativada
**Quando** a lista é lida
**Então** a situação permanece bloqueada ou desativada e não vira enviada

**Cenário 4: Registro da consulta**
**Dado que** a lista é aberta no portal ou na gestão
**Quando** a consulta termina
**Então** fica registrada a origem e a quantidade

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
