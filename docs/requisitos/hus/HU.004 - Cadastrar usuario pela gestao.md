# HU.004 – CADASTRAR USUÁRIO PELA GESTÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 19/10/2026 | Criação da HU, separada de Manter usuários | 2.2 | Sudoeste Informática |
| 22/10/2026 | Portal sem login próprio: o cidadão entra pelo Portal de Serviços da SEDUR (HU-142); a conta do cidadão nasce no primeiro acesso, não em formulário do portal | 2.3 | Sudoeste Informática |

---

## Visão Geral

O administrador cria uma conta local pelo botão Novo usuário, na lista de Administração > Usuários. Informa nome, e-mail, CPF, telefone (opcional) e papel. A conta nasce local, com o e-mail já confirmado e sem senha, e o usuário recebe por e-mail o link para definir a própria senha. O cadastro fica registrado como dado pessoal.

Este cadastro é diferente da conta do cidadão, que nasce no primeiro acesso pelo Portal de Serviços da SEDUR (HU-140), sem senha no Viabiliza e no perfil de cidadão.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Cadastrar usuário pela gestão](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** que o administrador cadastre contas locais**, de modo que** o novo usuário defina a própria senha e entre no console

## História de Usuário: Cadastrar usuário pela gestão

**Como** administrador **quero** cadastrar uma conta local com nome, e-mail, CPF, telefone e papel**, de modo que** o novo usuário receba o link para definir a própria senha e use o Viabiliza

## Pré-requisitos

- Administrador autenticado no console, com acesso à manutenção de usuários
- Nome obrigatório, com até 255 caracteres
- E-mail obrigatório, em formato válido, com até 255 caracteres e ainda não usado em outra conta
- CPF obrigatório, válido e ainda não usado em outra conta
- Telefone opcional, com DDD e até 20 caracteres
- Papel obrigatório e já existente

## Dependências

**Passadas**

- HU-006 — os papéis que podem ser atribuídos

**Futuras**

- HU-002 — o link para definir a senha chega pelo fluxo de redefinição do console
- HU-005 — a conta cadastrada passa a aparecer na lista de usuários

---

## Interface de Usuário

### Tela 1 — Novo usuário

**Caminho no menu:** Administração > Usuários > Novo usuário

*[Print da tela](screenshots/gestao-usuarios.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro de conta local**
**Dado que** o administrador abre Novo usuário e informa nome, e-mail ainda não usado, CPF válido e um papel existente
**Quando** confirma o cadastro
**Então** a conta é criada como local, com o e-mail já confirmado e sem senha, o papel é vinculado, o link para definir a senha é enviado ao e-mail da conta e o cadastro fica registrado como dado pessoal

**Cenário 2: Telefone é opcional**
**Dado que** o administrador não informa telefone
**Quando** confirma o cadastro
**Então** a conta é criada sem telefone

**Cenário 3: Dados recusados no cadastro**
**Dado que** o e-mail ou o CPF já pertencem a outra conta, o CPF é inválido, o telefone informado não tem DDD ou o papel não existe
**Quando** o administrador confirma o cadastro
**Então** o cadastro é recusado com a mensagem do campo e nenhuma conta é criada

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
