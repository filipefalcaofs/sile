# HU.002 – RECUPERAR SENHA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |
| 22/10/2026 | Portal sem login próprio: o cidadão entra pelo Portal de Serviços da SEDUR (HU-142); a recuperação de senha passa a ser só do console | 2.3 | Sudoeste Informática |

---

## Visão Geral

Quem esqueceu a senha do console pede um link por e-mail e define outra senha. O caminho fica na tela de entrada do console e não aparece no menu lateral.

O cidadão não tem senha no Viabiliza: ele entra pelo Portal de Serviços da SEDUR com a conta GOV.BR, na HU-142, e a recuperação dessa senha é feita no próprio GOV.BR. No console, a frase é sempre a de que as instruções foram enviadas se a conta tiver acesso ao console, sem revelar se a conta existe. Conta inativa, conta da rede da SEDUR e conta sem acesso ao console não recebem o link. Não há recuperação por CPF ou SMS. A redefinição não abre a sessão: a pessoa volta para a tela de entrada do console.

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

**Como** a SEDUR **quero** permitir a redefinição da senha do console por link**, de modo que** o servidor volte a entrar sem que o sistema revele se a conta existe

## História de Usuário: Recuperar senha

**Como** servidor que não lembra a senha do console **quero** receber um link de redefinição no meu e-mail**, de modo que** eu defina outra senha sem informar a terceiros se a conta está cadastrada

## Pré-requisitos

- A pessoa ainda não entrou no console
- Informa um e-mail em formato válido
- A nova senha cumpre a política do sistema e a confirmação
- O link só segue para conta ativa, local e com acesso ao console

## Dependências

**Passadas**

- HU-004 ou HU-001 — existir conta do console com e-mail

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Esqueci a senha

**Caminho no menu:** Link a partir da tela de entrada do console, fora do menu

*[Print da tela](screenshots/gestao-login.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cidadão não recupera senha no Viabiliza**
**Dado que** o cidadão entra pelo Portal de Serviços da SEDUR
**Quando** esquece a senha da conta GOV.BR
**Então** a recuperação é feita no GOV.BR, e o portal do Viabiliza não oferece o link de esqueci a senha

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

**Cenário 5: Conta inativa, desconhecida ou sem acesso ao console**
**Dado que** o e-mail não está cadastrado, a conta está inativa ou não usa o console
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
