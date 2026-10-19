# HU.003 – RECUPERAR SENHA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem esqueceu a senha pede um link por e-mail e define outra senha. O portal e o console têm caminhos separados, com mensagens diferentes, e nenhum dos dois aparece no menu lateral.

No portal, a resposta é a mesma quando o e-mail não existe: o sistema diz que o link foi enviado e não revela se a conta está cadastrada. No console, a frase é sempre a de que as instruções foram enviadas se a conta tiver acesso ao console. Conta inativa, conta da rede da SEDUR e conta sem acesso ao console não recebem o link. Não há recuperação por CPF, SMS ou GOV.BR. A redefinição do console não abre a sessão: a pessoa volta para a tela de entrada.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Recuperar senha](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** permitir a redefinição de senha por link**, de modo que** a pessoa volte a entrar sem que o sistema revele se a conta existe

## História de Usuário: Recuperar senha

**Como** usuário que não lembra a senha **quero** receber um link de redefinição no meu e-mail**, de modo que** eu defina outra senha sem informar a terceiros se a conta está cadastrada

## Pré-requisitos

- A pessoa ainda não está autenticada
- Informa um e-mail em formato válido
- A nova senha cumpre a política do sistema e a confirmação
- No console, o link só segue para conta ativa, local e com acesso ao console

## Dependências

**Passadas**

- HU-001 ou HU-002 — existir uma conta com e-mail, no portal ou no console

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Esqueci a senha

**Caminho no menu:** Link a partir da tela de entrada do portal ou do console, fora do menu

*[Print da tela](screenshots/gestao-login.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: E-mail desconhecido no portal**
**Dado que** o e-mail não está cadastrado
**Quando** o visitante pede recuperação no portal
**Então** a resposta apresentada é a de link enviado

**Cenário 2: Conta local do console recebe o link**
**Dado que** a conta está ativa, é local e tem acesso ao console
**Quando** a pessoa pede recuperação no console
**Então** o link do console é enviado e a mensagem exibida é a frase genérica de instruções enviadas

**Cenário 3: Conta da rede da SEDUR**
**Dado que** a conta entra pela rede da SEDUR
**Quando** a pessoa pede o link ou abre a redefinição do console
**Então** o link não é enviado e, na redefinição, o sistema informa que a senha é gerenciada pela rede corporativa

**Cenário 4: Nova senha no console**
**Dado que** o link é válido e a conta é local
**Quando** a nova senha cumpre a política e a confirmação
**Então** a senha é gravada, a alteração fica registrada e a pessoa volta à entrada do console, sem sessão aberta

**Cenário 5: Conta inativa ou sem acesso ao console**
**Dado que** a conta está inativa ou não usa o console
**Quando** a pessoa pede o link no console
**Então** o link não é enviado e a mensagem continua a mesma frase genérica

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
