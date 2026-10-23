# HU.141 – CONFIRMAR E-MAIL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |
| 22/10/2026 | Portal sem login próprio: o cidadão entra pelo Portal de Serviços da SEDUR (HU-142); a conta do cidadão nasce com o e-mail confirmado e a confirmação vale para a troca de e-mail | 2.3 | Sudoeste Informática |

---

## Visão Geral

A confirmação do e-mail é feita por um link enviado por e-mail. O texto pede a confirmação do endereço no Viabiliza, informa o prazo de validade do link e orienta a ignorar a mensagem se a pessoa não pediu a mudança. Sem essa confirmação, a entrada no console e as telas autenticadas do portal não seguem.

A conta do cidadão criada no primeiro acesso pelo Portal de Serviços da SEDUR já nasce com o e-mail confirmado, e a do servidor no primeiro acesso pela rede da SEDUR também. A conta criada pela gestão já nasce confirmada. A confirmação é pedida quando a pessoa troca o e-mail no perfil, no portal ou no console. Não há confirmação por número digitado na tela nem por SMS. O reenvio fica no aviso de e-mail pendente do portal; o console não tem um reenvio próprio.

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

**Como** a SEDUR **quero** confirmar o e-mail quando ele muda**, de modo que** o portal e o console só sigam com um endereço verificado

## História de Usuário: Confirmar e-mail

**Como** usuário que trocou o e-mail **quero** confirmar o novo endereço por um link**, de modo que** eu continue usando o portal e o console

## Pré-requisitos

- Conta com e-mail ainda não confirmado, depois da troca no perfil
- Link de confirmação dentro do prazo informado na mensagem (sessenta minutos, se outro prazo não estiver definido)
- O envio fica registrado como mensagem na fila

## Dependências

**Passadas**

- HU-144 — troca do e-mail no perfil

**Futuras**

- HU-001 e HU-145 — entrada no console e uso do portal depois da confirmação e do termo

---

## Interface de Usuário

### Tela 1 — Confirmar e-mail

**Caminho no menu:** Aviso de confirmação depois da troca de e-mail, antes do menu

*[Print da tela](screenshots/portal-perfil.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cidadão no primeiro acesso não confirma**
**Dado que** a conta do cidadão foi criada no primeiro acesso pelo Portal de Serviços
**Quando** ele segue para o painel
**Então** o e-mail já está confirmado e nenhum link é enviado

**Cenário 2: Troca de e-mail no portal fica pendente**
**Dado que** o cidadão trocou o e-mail em Meus dados e ainda não confirmou o novo endereço
**Quando** tenta abrir uma tela autenticada do portal
**Então** o sistema não deixa seguir até a confirmação e oferece o reenvio do link

**Cenário 3: Console recusa e-mail pendente**
**Dado que** a credencial está correta, a conta está ativa, tem acesso ao console e o e-mail não foi confirmado
**Quando** a pessoa tenta entrar no console
**Então** a sessão não é criada e a mensagem pede a confirmação do e-mail institucional

**Cenário 4: Troca de e-mail pede nova confirmação**
**Dado que** a pessoa altera o e-mail no perfil do portal ou do console
**Quando** o endereço muda
**Então** a confirmação anterior deixa de valer e um novo link é enviado

**Cenário 5: Confirmação registrada**
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
