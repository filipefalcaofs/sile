# HU.141 – CONFIRMAR E-MAIL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

A conta local confirma o e-mail por um link enviado por e-mail. O texto pede a confirmação do endereço no Viabiliza, informa o prazo de validade do link e orienta a ignorar a mensagem se a pessoa não criou a conta. Sem essa confirmação, as telas autenticadas do portal e a entrada no console não seguem.

Trocar o e-mail no perfil desfaz a confirmação e dispara outro envio. O primeiro acesso pela rede da SEDUR já nasce com o e-mail confirmado. O primeiro acesso pelo GOV.BR também, quando o provedor declara o e-mail verificado. Não há confirmação por número digitado na tela nem por SMS. O reenvio fica na entrada do portal; o console não tem um reenvio próprio.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Confirmar e-mail](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** confirmar o e-mail da conta local**, de modo que** o portal e o console só sigam com um endereço verificado

## História de Usuário: Confirmar e-mail

**Como** usuário que acabou de se cadastrar ou trocou o e-mail **quero** confirmar o endereço por um link**, de modo que** eu possa usar o portal e entrar no console

## Pré-requisitos

- Conta local com e-mail ainda não confirmado
- Link de confirmação dentro do prazo informado na mensagem (sessenta minutos, se outro prazo não estiver definido)
- O envio fica registrado como mensagem na fila

## Dependências

**Passadas**

- HU-140 — cadastro da conta local, ou HU-144 quando o e-mail é trocado

**Futuras**

- HU-001 e HU-145 — entrada no console e uso do portal depois da confirmação e do termo

---

## Interface de Usuário

### Tela 1 — Confirmar e-mail

**Caminho no menu:** Aviso de confirmação depois do cadastro ou da troca de e-mail, antes do menu

*[Print da tela](screenshots/portal-login.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro fica pendente de confirmação**
**Dado que** a conta local foi criada e o e-mail ainda não foi confirmado
**Quando** a pessoa tenta abrir uma tela autenticada do portal
**Então** o sistema não deixa seguir até a confirmação do e-mail

**Cenário 2: Console recusa e-mail pendente**
**Dado que** a credencial está correta, a conta está ativa, tem acesso ao console e o e-mail não foi confirmado
**Quando** a pessoa tenta entrar no console
**Então** a sessão não é criada e a mensagem pede a confirmação do e-mail institucional

**Cenário 3: Troca de e-mail pede nova confirmação**
**Dado que** a pessoa altera o e-mail no perfil do portal ou do console
**Quando** o endereço muda
**Então** a confirmação anterior deixa de valer e um novo link é enviado

**Cenário 4: Confirmação registrada**
**Dado que** a pessoa abre o link válido e o e-mail é marcado como confirmado
**Quando** a confirmação se conclui
**Então** o sistema registra que o e-mail foi confirmado pelo usuário

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
