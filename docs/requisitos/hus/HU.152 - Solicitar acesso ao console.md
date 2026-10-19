# HU.152 – SOLICITAR ACESSO AO CONSOLE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem entra no console e ainda não tem perfil pede um, com justificativa. O pedido aparece no painel, na seção de acesso ainda não liberado. Só entram perfis de trabalho do console que não sejam protegidos; o perfil de cidadão fica de fora. Se já há pedido aguardando decisão, o painel informa o perfil pedido e não mostra o formulário de novo. Se o último pedido foi recusado, o motivo aparece e é possível pedir outra vez. Sem perfil disponível para pedido, a orientação é procurar o administrador. Quem já tem perfil não cria pedido. Há no máximo um pedido pendente por pessoa. O administrador, na lista de usuários, aprova ou recusa. Na aprovação pode conceder outro perfil existente, não só o que foi pedido, e a conta fica só com esse perfil. A recusa exige motivo e não atribui perfil. Pedido já decidido não é decidido de novo. Criar, aprovar e recusar ficam registrados. Não há fila de mais de um aprovador, nem vários perfis no mesmo pedido, nem e-mail ao solicitante nesta decisão.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico-ep01--identidade-acesso-e-segurança)
- [História de Usuário: Solicitar acesso ao console](#história-de-usuário-solicitar-acesso-ao-console)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Pedido no painel](#tela-1--pedido-no-painel)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
  - [Tela 2 — Decisão na lista de usuários](#tela-2--decisão-na-lista-de-usuários)
  - [Comportamento Esperado Tela 2](#comportamento-esperado-tela-2)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** servidor do console **quero** pedir um perfil e ter a decisão registrada**, de modo que** o trabalho só comece depois da liberação de um administrador.

## História de Usuário: Solicitar acesso ao console

**Como** servidor autenticado no console sem perfil **quero** pedir um perfil e justificar o pedido**, de modo que** um administrador aprove, podendo conceder outro perfil, ou recuse com motivo.

## Pré-requisitos

- Conta do console sem perfil, com acesso ao console e termo aceito
- Pelo menos um perfil de trabalho do console disponível para pedido
- Administrador autorizado a manter usuários, para aprovar ou recusar

## Dependências

**Passadas**

- A entrada no console pode existir sem perfil atribuído

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Pedido no painel

**Caminho no menu:** Operação > Painel, quando a conta ainda não tem perfil

#### Comportamento Esperado Tela 1

**Cenário 1: Pedido enviado**
**Dado que** o servidor não tem perfil nem pedido pendente
**Quando** escolhe um perfil disponível e escreve justificativa de 10 a 500 caracteres
**Então** o pedido fica aguardando decisão e a mensagem informa que um administrador vai analisar

**Cenário 2: Pedido já pendente**
**Dado que** já existe pedido aguardando decisão da mesma conta
**Quando** o servidor tenta enviar outro
**Então** nenhum pedido novo é criado e a mensagem informa que já há solicitação aguardando decisão

**Cenário 3: Pedido recusado**
**Dado que** o último pedido foi recusado
**Quando** o servidor abre o painel
**Então** vê o motivo e pode pedir de novo

### Tela 2 — Decisão na lista de usuários

**Caminho no menu:** Administração > Usuários

*[Print da tela](screenshots/gestao-usuarios.png)*

#### Comportamento Esperado Tela 2

**Cenário 1: Aprovação com outro perfil**
**Dado que** o administrador está autorizado a manter usuários e há um pedido pendente
**Quando** aprova informando um perfil de trabalho existente
**Então** a conta fica só com esse perfil, o pedido fica aprovado e o registro mostra o perfil pedido e o concedido

**Cenário 2: Recusa**
**Dado que** há um pedido pendente
**Quando** o administrador recusa com motivo de até 300 caracteres
**Então** o pedido fica recusado, o motivo é guardado, nenhum perfil é atribuído e a recusa fica registrada

**Cenário 3: Decisão repetida**
**Dado que** o pedido já foi aprovado ou recusado
**Quando** alguém tenta aprovar ou recusar de novo
**Então** a mensagem informa que a solicitação já foi decidida e a situação não muda

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
