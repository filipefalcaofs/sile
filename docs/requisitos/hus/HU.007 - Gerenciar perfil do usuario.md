# HU.007 – GERENCIAR PERFIL DO USUÁRIO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

A pessoa autenticada atualiza o próprio nome, e-mail e telefone. O CPF aparece na tela com a orientação de que não pode ser alterado, e um valor diferente enviado junto não é gravado. Trocar o e-mail desfaz a confirmação e envia um novo link.

No portal, o caminho é Minha conta > Meus dados. No console, os dados da conta ficam no cabeçalho e não há item no menu lateral. Papel, inativação e tipo de entrada de outra conta não são esta tela: isso fica em Administração > Usuários. Não há exclusão da própria conta nem foto. O telefone é opcional nesta edição; no cadastro ele é obrigatório.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Gerenciar perfil do usuário](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** que a pessoa mantenha os próprios dados de contato**, de modo que** o cadastro siga atualizado sem mudar o CPF da conta

## História de Usuário: Gerenciar perfil do usuário

**Como** usuário autenticado **quero** corrigir meu nome, e-mail e telefone**, de modo que** meus dados de contato fiquem atualizados sem alterar o CPF

## Pré-requisitos

- Pessoa autenticada no portal, com e-mail confirmado e termo aceito, ou no console, com acesso ao console e termo aceito
- Nome obrigatório, com até 255 caracteres
- E-mail obrigatório, válido, com até 255 caracteres e único, ignorando a própria conta
- Telefone opcional; quando informado, com DDD e até 20 caracteres

## Dependências

**Passadas**

- HU-002 — sessão aberta
- HU-006 — termo vigente aceito

**Futuras**

- HU-005 — nova confirmação quando o e-mail muda

---

## Interface de Usuário

### Tela 1 — Meus dados

**Caminho no menu:** Portal: Minha conta > Meus dados. Console: dados da conta, pelo cabeçalho

*[Print da tela](screenshots/portal-perfil.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cidadão atualiza o próprio contato**
**Dado que** o cidadão está em Meus dados
**Quando** salva nome, e-mail único e telefone com DDD
**Então** esses dados são gravados e a mensagem é "Perfil atualizado com sucesso."

**Cenário 2: CPF não é alterado**
**Dado que** o envio inclui um CPF diferente do da conta
**Quando** os dados são salvos
**Então** o CPF gravado não muda

**Cenário 3: E-mail alterado pede confirmação**
**Dado que** o e-mail informado é diferente do atual
**Quando** o perfil é salvo no portal ou no console
**Então** a confirmação anterior deixa de valer e a mensagem de verificação é enviada

**Cenário 4: Servidor edita a própria conta no console**
**Dado que** o servidor tem acesso ao console e o termo aceito
**Quando** salva os próprios dados
**Então** a conta é atualizada com as mesmas regras do portal

**Cenário 5: Telefone em branco**
**Dado que** o telefone é deixado vazio
**Quando** a pessoa salva o perfil
**Então** o sistema aceita a atualização, porque o telefone não é obrigatório nesta edição

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
