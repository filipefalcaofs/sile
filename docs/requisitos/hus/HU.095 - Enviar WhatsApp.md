# HU.095 – ENVIAR WHATSAPP
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não oferece o envio de WhatsApp. Não há provedor que entregue a mensagem ao aparelho. Enquanto isso, uma tentativa que chegue a esse canal não pode constar como enviada: fica bloqueada e o bloqueio fica registrado. Os outros avisos não são interrompidos por causa do WhatsApp.

No padrão, o canal está desligado e nenhum aviso de processo sai por WhatsApp. Se o canal for incluído e permanecer desligado, a comunicação nasce desativada, o que não é bloqueio de provedor. Com o canal ligado e telefone informado, a linha que estava na fila fica bloqueada. Sem telefone, não há transmissão e a situação da linha não muda. O registro de e-mail e de aviso interno não transforma esse bloqueio em enviado.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico)
- [História de Usuário: Enviar WhatsApp](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** a SEDUR **quero** registrar a tentativa de WhatsApp como bloqueada enquanto não houver provedor**, de modo que** ninguém veja como enviado um aviso que não saiu

## História de Usuário: Enviar WhatsApp

**Como** gestor **quero** que o WhatsApp de processo não conste como enviado**, de modo que** o canal desligado fique desativado e o canal sem provedor fique bloqueado

## Pré-requisitos

- O sistema ainda não oferece provedor de WhatsApp
- Canal previsto para o aviso e ligado, para a tentativa chegar a ser bloqueada
- Telefone do destinatário, quando a tentativa for registrada

## Dependências

**Passadas**

- Avisos de processo já existentes por e-mail e aviso interno

**Futuras**

- Provedor de WhatsApp, quando a SEDUR dispuser do serviço

---

## Interface de Usuário

### Tela 1 — Enviar WhatsApp

**Caminho no menu:** O sistema ainda não oferece o envio por WhatsApp

#### Comportamento Esperado Tela 1

**Cenário 1: Tentativa bloqueada**
**Dado que** um aviso chega ao WhatsApp e há telefone
**Quando** o provedor atual é usado
**Então** a comunicação não fica enviada: a que estava na fila fica bloqueada e o bloqueio fica registrado

**Cenário 2: Outros canais**
**Dado que** o WhatsApp ficou bloqueado
**Quando** e-mail ou aviso interno são registrados
**Então** eles não transformam essa comunicação em enviada

**Cenário 3: Canal desligado**
**Dado que** o WhatsApp está desligado
**Quando** o aviso é tratado
**Então** o canal não é enviado e fica desativado

**Cenário 4: Padrão sem WhatsApp**
**Dado que** os avisos de processo estão na configuração padrão
**Quando** um aviso é montado
**Então** nenhum aviso de processo sai por WhatsApp

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
