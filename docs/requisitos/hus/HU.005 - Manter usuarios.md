# HU.005 – MANTER USUÁRIOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois); o cadastro de usuário pela gestão passou para a HU-004 | 2.2 | Sudoeste Informática |
| 22/10/2026 | Portal sem login próprio: o cidadão entra pelo Portal de Serviços da SEDUR (HU-142); a conta do cidadão nasce no primeiro acesso pelo Portal de Serviços | 2.3 | Sudoeste Informática |

---

## Visão Geral

O administrador localiza contas, troca o papel, inativa ou reativa, define se a entrada é pela rede da SEDUR ou local e decide pedidos de primeiro acesso. O botão Novo usuário, na mesma lista, abre o cadastro de uma conta nova, descrito na HU-004. Não há, nesta tela, edição de nome, e-mail, CPF ou telefone de uma conta existente, nem exclusão da conta. Uma conta tem um papel por vez. O CPF aparece mascarado, só com os dois últimos dígitos, inclusive na exportação.

A lista tem duas abas. A da equipe mostra contas da rede da SEDUR e papéis com acesso ao console. A do portal mostra usuários externos e nunca contas da rede. A busca é por nome ou e-mail. Essas ações são tratadas como dado pessoal e ficam registradas, com o papel anterior e o novo quando o papel muda.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter usuários](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** administrar contas já existentes**, de modo que** o administrador controle quem entra no console e no portal

## História de Usuário: Manter usuários

**Como** administrador **quero** localizar contas, trocar o papel, inativar ou reativar, definir a forma de entrada e decidir solicitações de acesso**, de modo que** eu controle quem usa o Viabiliza

## Pré-requisitos

- Administrador autenticado no console, com acesso à manutenção de usuários
- Papel escolhido já existente
- Na entrada pela rede da SEDUR, o usuário da rede é obrigatório, único, em minúsculas, com até 100 caracteres, formado por letras, números, ponto, underline ou hífen
- Na entrada local, o usuário da rede não é informado
- Recusa de solicitação de acesso exige motivo com até 300 caracteres

## Dependências

**Passadas**

- HU-140, HU-001 e HU-004 — as contas nascem no primeiro acesso do cidadão pelo Portal de Serviços, no primeiro acesso pela rede da SEDUR ou pelo cadastro feito pelo administrador
- HU-006 — os papéis que podem ser atribuídos

**Futuras**

- HU-002 — ao converter conta da rede para local, a nova senha chega pelo link de redefinição do console

---

## Interface de Usuário

### Tela 1 — Usuários

**Caminho no menu:** Administração > Usuários

*[Print da tela](screenshots/gestao-usuarios.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Abas e CPF mascarado**
**Dado que** o administrador abre a lista na aba da equipe ou na aba do portal
**Quando** a lista carrega
**Então** ele vê só o conjunto da aba e o CPF apenas com os dois últimos dígitos

**Cenário 2: Um papel e registro da troca**
**Dado que** a conta tem um papel atual
**Quando** o administrador grava outro papel existente
**Então** a conta fica com esse único papel e o registro guarda o papel anterior e o novo

**Cenário 3: O administrador não se inativa**
**Dado que** o administrador está autenticado
**Quando** tenta inativar a própria conta
**Então** a inativação é recusada com a mensagem de que ele não pode inativar a própria conta

**Cenário 4: Da rede da SEDUR para senha local**
**Dado que** a conta entra pela rede da SEDUR
**Quando** o administrador muda a entrada para local
**Então** o usuário da rede é removido, a senha anterior deixa de valer e o link de redefinição do console é enviado ao e-mail da conta

**Cenário 5: Solicitação já decidida**
**Dado que** a solicitação de acesso não está pendente
**Quando** o administrador tenta aprovar ou recusar
**Então** a decisão não é reaplicada e a mensagem informa que a solicitação já foi decidida

**Cenário 6: Recusa de primeiro acesso**
**Dado que** a solicitação está pendente
**Quando** o administrador recusa com um motivo de até 300 caracteres
**Então** a solicitação fica recusada; aprovar, no caso pendente, vincula o papel informado

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
