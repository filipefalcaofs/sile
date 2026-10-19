# HU.001 – CADASTRAR USUÁRIO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O visitante cria a própria conta no portal do Viabiliza, com nome, e-mail, CPF, telefone e senha. A conta nasce somente no perfil de cidadão. As telas do portal autenticado só se abrem depois da confirmação do e-mail.

A tela mostra as regras da senha e, quando o acesso pelo GOV.BR está disponível, o botão correspondente. Conta de servidor da rede da SEDUR e conta criada pelo GOV.BR não nascem neste formulário. O administrador não cadastra usuário digitando os dados: ele consulta contas, troca o perfil, inativa e define se a entrada é local ou pela rede da SEDUR. Este cadastro não consulta mapa, REDESIM, CNAE nem as regras da LOUOS, e não busca a pessoa em base externa.

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

**Como** a SEDUR **quero** que o cidadão crie a própria conta**, de modo que** ele use o portal com CPF, e-mail, telefone e senha, no perfil de cidadão

## História de Usuário: Cadastrar usuário

**Como** visitante **quero** me cadastrar com nome, e-mail, CPF, telefone e senha**, de modo que** eu tenha uma conta de cidadão no Viabiliza

## Pré-requisitos

- A pessoa ainda não está autenticada
- Nome obrigatório, com até 255 caracteres
- E-mail obrigatório, em formato válido, com até 255 caracteres e ainda não usado em outra conta
- CPF obrigatório, com 11 dígitos, dígitos verificadores corretos, sem sequência repetida, e ainda não usado
- Telefone obrigatório, com DDD e 8 ou 9 dígitos; a máscara não entra na contagem; o campo aceita até 20 caracteres
- Senha obrigatória e confirmada, no tamanho mínimo exigido e, quando a SEDUR assim definir, com letras maiúsculas e minúsculas, números e símbolos

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- HU-005 — a conta só usa as telas autenticadas do portal depois de confirmar o e-mail

---

## Interface de Usuário

### Tela 1 — Cadastrar usuário

**Caminho no menu:** Tela de cadastro do portal, aberta a partir da entrada, antes do menu

*[Print da tela](screenshots/portal-login.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro válido cria conta de cidadão**
**Dado que** o visitante informa nome, e-mail inédito, CPF válido, telefone com DDD e senha que cumpre a política
**Quando** envia o formulário
**Então** o sistema grava a conta e atribui somente o perfil de cidadão

**Cenário 2: CPF ou e-mail já cadastrado**
**Dado que** o CPF ou o e-mail já pertence a outra conta
**Quando** o visitante tenta se cadastrar
**Então** o sistema recusa o envio e nenhuma conta nova é gravada

**Cenário 3: CPF inválido**
**Dado que** o CPF não tem 11 dígitos, é uma sequência repetida ou os dígitos verificadores não conferem
**Quando** o visitante envia o cadastro
**Então** o sistema recusa e informa que o CPF é inválido

**Cenário 4: Conta ainda não entra no portal**
**Dado que** o cadastro foi concluído e o e-mail ainda não foi confirmado
**Quando** a pessoa tenta abrir uma tela do portal que exige a confirmação
**Então** o sistema impede o uso até a confirmação do e-mail

**Cenário 5: Senha ou telefone fora da regra**
**Dado que** a senha não cumpre a política ou o telefone não tem DDD com 8 ou 9 dígitos
**Quando** o visitante envia o cadastro
**Então** o sistema recusa e não cria a conta

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
