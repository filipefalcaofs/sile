# HU.151 – AUTENTICAR COM GOV.BR
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O cidadão entra no portal pelo Login Único do GOV.BR. A identidade usada é o CPF. Conta já cadastrada com esse CPF é vinculada. CPF ainda sem conta cria a conta de verdade, com o papel de cidadão, desde que o GOV.BR traga nome e e-mail verificado que não esteja em outra conta. Com o recurso desligado ou sem as credenciais da integração, o botão não leva ao GOV.BR: a pessoa volta ao login com o aviso de que o login com GOV.BR está indisponível e pode entrar com e-mail e senha. Nenhuma sessão é simulada. O botão só aparece na tela de entrada quando o recurso está disponível. Não há item de menu. Nível de confiabilidade abaixo do mínimo configurado, com padrão bronze, não cria sessão e orienta a elevar o nível. Conta local inativa não entra e informa para procurar o administrador. Autorização não concluída, sessão de autenticação expirada ou CPF fora do formato de 11 dígitos não cria sessão. Depois da entrada, valem as mesmas portas do portal: e-mail verificado e aceite do termo vigente, quando houver. Conta nova já nasce com o e-mail verificado e ainda passa pelo termo. O destino é o painel do portal. Não há saída única no GOV.BR nesta entrada. Sem credenciais configuradas, o login permanece indisponível.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico-ep01--identidade-acesso-e-segurança)
- [História de Usuário: Autenticar com GOV.BR](#história-de-usuário-autenticar-com-govbr)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Entrada pelo GOV.BR](#tela-1--entrada-pelo-govbr)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** cidadão **quero** entrar no portal com a conta do GOV.BR**, de modo que** eu use o Viabiliza sem depender só do CPF e da senha local.

## História de Usuário: Autenticar com GOV.BR

**Como** cidadão com conta GOV.BR **quero** autorizar o Login Único e cair no painel**, de modo que** a conta existente seja reconhecida pelo CPF ou uma conta nova seja criada de verdade.

## Pré-requisitos

- Recurso de login com GOV.BR ligado e credenciais da integração preenchidas
- CPF com 11 dígitos no retorno do GOV.BR
- Nível de confiabilidade igual ou acima do mínimo configurado
- No primeiro acesso, nome e e-mail verificado no GOV.BR, ainda não usado por outra conta

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Conta nova ainda precisa aceitar o termo vigente, quando houver

---

## Interface de Usuário

### Tela 1 — Entrada pelo GOV.BR

**Caminho no menu:** Botão na tela de entrada do portal, somente quando o login com GOV.BR está disponível. Não há item de menu.

*[Print da tela](screenshots/portal-login.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Login indisponível**
**Dado que** o recurso está desligado ou as credenciais não estão preenchidas
**Quando** o visitante tenta entrar pelo GOV.BR
**Então** volta ao login com o aviso de indisponibilidade e nenhuma sessão é criada

**Cenário 2: CPF já cadastrado e ativo**
**Dado que** o login está disponível e já existe usuário ativo com o CPF informado pelo GOV.BR
**Quando** a autorização é concluída
**Então** a sessão do portal é criada, o vínculo com o GOV.BR é gravado ou atualizado e a entrada fica registrada

**Cenário 3: Primeiro acesso**
**Dado que** não existe usuário com aquele CPF e o GOV.BR traz nome e e-mail verificado que não está em outra conta
**Quando** a autorização é concluída
**Então** a conta de cidadão é criada, o e-mail fica verificado e o cadastro pelo GOV.BR fica registrado

**Cenário 4: Nível insuficiente ou conta inativa**
**Dado que** o nível está abaixo do mínimo ou a conta local com o mesmo CPF está inativa
**Quando** a autorização é lida
**Então** a sessão não é criada e o cidadão recebe a mensagem de nível ou de conta inativa

**Cenário 5: E-mail já usado por outra conta**
**Dado que** o CPF ainda não tem usuário e o e-mail do GOV.BR já está em outra conta
**Quando** o primeiro acesso é tentado
**Então** nenhuma conta é criada e a mensagem informa que o e-mail já está em uso

**Cenário 6: Autorização não concluída**
**Dado que** a pessoa não conclui a autorização no GOV.BR ou a sessão de autenticação expira
**Quando** o retorno chega ao portal
**Então** nenhuma sessão é criada e a mensagem orienta a tentar de novo ou entrar com e-mail e senha

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
