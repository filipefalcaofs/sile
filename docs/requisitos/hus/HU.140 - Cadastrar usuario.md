# HU.140 – CADASTRAR USUÁRIO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois); esclarecimento de que o administrador cadastra contas pela HU-004 | 2.2 | Sudoeste Informática |
| 22/10/2026 | Portal sem login próprio: o cidadão entra pelo Portal de Serviços da SEDUR (HU-142); a conta do cidadão passa a nascer no primeiro acesso, sem formulário nem senha | 2.3 | Sudoeste Informática |

---

## Visão Geral

A conta do cidadão é criada no Viabiliza no primeiro acesso pelo Portal de Serviços da SEDUR, do mesmo jeito que a conta do servidor nasce no primeiro acesso pela rede da SEDUR. O Portal de Serviços confirma quem é a pessoa; o Viabiliza guarda a conta com o nome, o CPF e o e-mail do cadastro, no perfil de cidadão. A partir daí, a conta e a sessão são do Viabiliza.

Não há formulário de cadastro nem senha no portal do Viabiliza. A conta nasce com o e-mail já confirmado e ainda passa pelo aceite do termo vigente, quando houver. O telefone, quando o cadastro não trouxer, pode ser informado depois em Minha conta > Meus dados. O administrador cria contas do console pela gestão, na HU-004. Este cadastro não consulta mapa, REDESIM, CNAE nem as regras da LOUOS.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Cadastrar usuário](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** que a conta do cidadão nasça no primeiro acesso pelo Portal de Serviços**, de modo que** ele use o Viabiliza sem preencher outro cadastro nem criar outra senha

## História de Usuário: Cadastrar usuário

**Como** cidadão que chega pela primeira vez ao Viabiliza pelo Portal de Serviços **quero** que minha conta seja criada com os dados que já informei**, de modo que** eu comece a usar o portal sem outro cadastro

## Pré-requisitos

- Entrada feita no Portal de Serviços da SEDUR com a conta GOV.BR (HU-142)
- Nenhuma conta no Viabiliza com o mesmo CPF
- Nome e e-mail no cadastro, sem o e-mail estar em outra conta do Viabiliza

## Dependências

**Passadas**

- HU-142 — entrada pelo Portal de Serviços da SEDUR

**Futuras**

- HU-145 — aceite do termo vigente antes de seguir no portal
- HU-144 — completar o telefone e manter os dados de contato

---

## Interface de Usuário

### Tela 1 — Primeiro acesso

**Caminho no menu:** Portal de Serviços da SEDUR › Carta de Serviços › Viabilidade de Localização, no primeiro acesso. Não há tela de cadastro no portal do Viabiliza nem item de menu.

*[Print da tela](screenshots/portal-servicos-sedur.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Primeiro acesso cria a conta de cidadão**
**Dado que** o cidadão entrou no Portal de Serviços e ainda não tem conta no Viabiliza com o seu CPF
**Quando** chega ao Viabiliza
**Então** a conta é criada com nome, CPF e e-mail do cadastro, somente no perfil de cidadão, com o e-mail já confirmado, e o cadastro fica registrado

**Cenário 2: CPF que já tem conta**
**Dado que** já existe conta no Viabiliza com o mesmo CPF
**Quando** o cidadão chega ao Viabiliza
**Então** nenhuma conta nova é criada e ele entra na conta que já existe

**Cenário 3: E-mail já usado por outra conta**
**Dado que** o e-mail do cadastro já pertence a outra conta do Viabiliza
**Quando** o cidadão chega ao Viabiliza pela primeira vez
**Então** nenhuma conta é criada e a mensagem informa que o e-mail já está em uso

**Cenário 4: Cadastro sem nome ou e-mail**
**Dado que** o cadastro do cidadão não traz nome ou e-mail
**Quando** ele chega ao Viabiliza pela primeira vez
**Então** nenhuma conta é criada e a mensagem orienta a completar o cadastro no GOV.BR

**Cenário 5: Conta nova ainda passa pelo termo**
**Dado que** a conta acabou de ser criada
**Quando** o cidadão segue para o painel
**Então** o Viabiliza pede o aceite do termo vigente, quando houver, antes de abrir as telas do portal

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
