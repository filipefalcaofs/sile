# HU.185 – REENVIAR O LINK DE DEFINIÇÃO DE SENHA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 04/11/2026 | Criação da HU da melhoria entregue entre 16/10 e 04/11/2026 | 1.0 | Sudoeste Informática |

---

## Visão Geral

Quando o administrador cadastra um usuário da gestão (HU.004), o sistema envia por e-mail o link para o novo usuário definir a própria senha. Se o e-mail se perde ou o link vence, o usuário só conseguia um novo link pela recuperação de senha da tela de entrada.

Esta HU cria a ação **Reenviar link de senha** na lista de usuários da gestão. O administrador envia de novo o link ao e-mail da conta, sem depender do usuário.

1. **Só conta local e ativa.** Conta que entra com a senha da rede corporativa da Prefeitura não recebe link. Conta inativa precisa ser reativada antes.
2. **Um link vale.** O link novo invalida os enviados antes.
3. **Fica registrado.** O envio aparece no monitoramento de e-mails e na trilha de auditoria.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico-ep01--identidade-acesso-e-segurança)
- [História de Usuário](#história-de-usuário-reenviar-o-link-de-definição-de-senha)
- [Regras](#regras)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Comportamento Esperado](#comportamento-esperado)
- [Cenários de teste para homologação](#cenários-de-teste-para-homologação)
- [Pontos para validação da SEDUR](#pontos-para-validação-da-sedur)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** SEDUR **quero** que o administrador consiga reenviar o link de senha aos usuários da gestão **, de modo que** o primeiro acesso não dependa de um e-mail que se perdeu ou de um link vencido

## História de Usuário: Reenviar o link de definição de senha

**Como** administrador com a atribuição de cadastrar usuários **quero** reenviar a um usuário da gestão o link para definir a senha **, de modo que** ele consiga entrar no Viabiliza sem abrir chamado

---

## Regras

| Nº | Regra |
|:---:|---|
| RN-01 | A ação fica em Administração > Usuários, na aba dos usuários da gestão, e exige a atribuição "Cadastrar usuários" |
| RN-02 | O botão "Reenviar link de senha" aparece só para conta ativa que entra com senha do próprio Viabiliza |
| RN-03 | Antes do envio, o sistema pede confirmação: "Enviar novamente a (e-mail) o link para definir a senha? Links enviados antes deixam de valer." |
| RN-04 | Conta que usa a senha da rede corporativa é recusada: "Conta AD usa a senha da rede corporativa; não há link para enviar." |
| RN-05 | Conta inativa é recusada: "Reative a conta antes de reenviar o link de senha." |
| RN-06 | Um novo envio para a mesma conta só é aceito um minuto depois do anterior. Antes disso: "Um link foi enviado há pouco. Aguarde um minuto para reenviar." |
| RN-07 | O link leva à tela de definição de senha da gestão e vale 60 minutos. O link novo substitui os anteriores |
| RN-08 | Envio aceito mostra: "Enviamos novamente a (e-mail) o link para definir a senha." Falha no envio mostra: "Não foi possível enviar o link de senha." |
| RN-09 | O e-mail entra na fila de envio e aparece no monitoramento de e-mails |
| RN-10 | O reenvio fica na trilha de auditoria como tratamento de dado pessoal, com o usuário de destino e quem enviou |

**Diferença para a HU.002.** Na HU.002 o próprio usuário pede o link na tela de entrada. Aqui quem pede é o administrador, pela lista de usuários. O link e a tela de definição de senha são os mesmos.

---

## Pré-requisitos

- Atribuição "Cadastrar usuários"
- Conta da gestão ativa, com senha do próprio Viabiliza
- Servidor de e-mail configurado

## Dependências

**Passadas**

- HU.002 — Recuperar senha
- HU.004 — Cadastrar usuário pela gestão
- HU.005 — Manter usuários
- HU.016 — Configurar servidores de e-mail
- HU.017 — Monitorar envio de e-mails

**Futuras**

- Nenhuma

---

## Comportamento Esperado

**Cenário 1: Reenvio aceito**
**Dado que** a conta é local e está ativa
**Quando** o administrador clica em "Reenviar link de senha" e confirma
**Então** o link é enviado ao e-mail da conta
**E** aparece a mensagem de envio com o e-mail
**E** o envio fica no monitoramento de e-mails e na auditoria

**Cenário 2: Link anterior perde a validade**
**Dado que** o usuário recebeu um link antes
**Quando** o administrador reenvia
**Então** só o link novo permite definir a senha

**Cenário 3: Conta da rede corporativa**
**Dado que** a conta entra com a senha da rede corporativa
**Quando** o reenvio é pedido
**Então** o sistema recusa e informa que não há link para enviar

**Cenário 4: Conta inativa**
**Dado que** a conta está inativa
**Quando** o reenvio é pedido
**Então** o sistema recusa e pede a reativação

**Cenário 5: Reenvio em sequência**
**Dado que** um link foi enviado há menos de um minuto
**Quando** o administrador pede outro
**Então** o sistema recusa e pede para aguardar

---

## Cenários de teste para homologação

| # | Situação | Como testar | Resultado esperado |
|:---:|---|---|---|
| H1 | Conta local ativa | Reenviar o link | E-mail recebido; link abre a definição de senha |
| H2 | Link antigo | Usar o link do envio anterior | Link recusado |
| H3 | Conta inativa | Inativar a conta e observar a lista | Botão não aparece |
| H4 | Dois envios seguidos | Reenviar duas vezes em menos de um minuto | Segundo envio recusado |
| H5 | Conta da rede corporativa | Observar a lista | Botão não aparece |
| H6 | Registro | Consultar monitoramento de e-mails e auditoria | Envio registrado |

---

## Pontos para validação da SEDUR

1. **Validade do link.** O link vale 60 minutos, o mesmo prazo da recuperação de senha. Para o primeiro acesso, a SEDUR quer um prazo maior?

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
