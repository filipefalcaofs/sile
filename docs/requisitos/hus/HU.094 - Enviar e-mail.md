# HU.094 – ENVIAR E-MAIL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Os avisos de processo saem por e-mail quando o canal está previsto e ligado. No padrão, usam e-mail o convite aberto, o convite expirado, o prazo perto de vencer, o escalonamento de prazo e o resultado. A resposta de pendência, no padrão, fica só no aviso interno.

Cada envio nasce na fila e, se o correio aceita, fica enviado. Se falha, fica falhou, com o motivo, ou com o texto Falha no envio da notificação quando não houver motivo. Canal desligado fica desativado e não é marcado como enviado. Não há situação de enviado sem o envio ter ocorrido. Destinatário sem e-mail utilizável não entra na fila. E-mail de conta, como confirmação ou senha, não usa esta lista de avisos de processo.

Quem mantém os servidores de e-mail cadastra, altera, exclui e testa o servidor. A senha não é mostrada de novo. O teste envia uma mensagem real de teste, separada dos avisos de processo. Se existe servidor padrão ativo, os avisos passam a usá-lo. Sem servidor ativo, permanece o correio do ambiente. O envio dos avisos de processo não depende de quem cadastra o servidor. O sistema ainda não oferece e-mail de processo para destinatário sem e-mail utilizável.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico)
- [História de Usuário: Enviar e-mail](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** a SEDUR **quero** entregar por e-mail os avisos de processo cujo canal está ligado**, de modo que** a retaguarda saiba se foi enviado, se falhou ou se o canal estava desligado

## História de Usuário: Enviar e-mail

**Como** gestor **quero** ver o resultado do e-mail do processo e manter o servidor de envio**, de modo que** a senha não seja reexibida e o teste de conexão seja um envio real

## Pré-requisitos

- Aviso de processo com canal de e-mail previsto e ligado
- Destinatário com e-mail utilizável
- Quem mantém a configuração de e-mail, para cadastrar e testar o servidor

## Dependências

**Passadas**

- HU.090 — Notificar pendência
- HU.077 — Notificar resultado ao cidadão

**Futuras**

- HU.096 — Consultar histórico de comunicações

---

## Interface de Usuário

### Tela 1 — Enviar e-mail

**Caminho no menu:** Configuração > Monitoramento de e-mails

*[Print da tela](screenshots/gestao-emails.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: E-mail enviado**
**Dado que** o canal de e-mail está ligado e previsto para o aviso
**Quando** o correio aceita a mensagem
**Então** a comunicação sai da fila e fica enviada

**Cenário 2: Falha do correio**
**Dado que** o correio recusa ou falha
**Quando** o envio termina
**Então** a comunicação fica como falhou, com o motivo, e não como enviada

**Cenário 3: Canal desligado**
**Dado que** o e-mail está desligado
**Quando** o aviso é tratado
**Então** a comunicação fica desativada e o desligamento fica registrado

**Cenário 4: Servidor padrão**
**Dado que** existe servidor de e-mail padrão e ativo
**Quando** o sistema inicia
**Então** os avisos passam a usar esse servidor; sem servidor ativo, permanece o correio do ambiente

**Cenário 5: Cadastro do servidor**
**Dado que** a pessoa não mantém a configuração de e-mail
**Quando** tenta cadastrar o servidor
**Então** o sistema recusa; o envio dos avisos de processo não depende dessa atribuição

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
