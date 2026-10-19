# HU.008 – VINCULAR PROCURADOR
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O cidadão autoriza outra conta já cadastrada, localizada pelo e-mail, a atuar em seu nome. A lista mostra o que ele outorgou e o que recebeu: nome, e-mail da outra parte, início, validade, revogação e se a procuração está ativa. O procurador inicia a atuação a partir de uma procuração ativa e o painel passa a informar em nome de quem ele está.

Não há envio de documento, consulta à Receita, vínculo por CPF ou CNPJ, nem aprovação do procurador antes de a outorga valer. Não há tela de procurações no console. A procuração vale na hora, com início agora. Sem data de validade, ela não expira por data. Se a procuração deixar de estar ativa, a atuação em aberto é encerrada na próxima ação do procurador no portal.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Vincular procurador](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** que o cidadão autorize outra conta a atuar em seu nome**, de modo que** o procurador acompanhe os serviços do portal nessa condição

## História de Usuário: Vincular procurador

**Como** cidadão autenticado **quero** vincular um procurador pelo e-mail da conta dele e, quando eu for o procurador, passar a atuar em nome de quem me outorgou**, de modo que** eu acompanhe os serviços do portal nessa condição

## Pré-requisitos

- Cidadão autenticado no portal, com e-mail confirmado e termo aceito
- E-mail de uma conta já cadastrada no Viabiliza
- A validade, quando informada, precisa ser uma data futura
- A procuração não pode ser para a própria conta nem repetir outra procuração ativa do mesmo outorgante para o mesmo procurador

## Dependências

**Passadas**

- HU-001 — as duas contas já existem no portal
- HU-006 — termo vigente aceito

**Futuras**

- HU-009 — revogação da procuração e encerramento da atuação

---

## Interface de Usuário

### Tela 1 — Procurações

**Caminho no menu:** Serviços > Procurações

*[Print da tela](screenshots/portal-procuracoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Vínculo por e-mail**
**Dado que** o cidadão está autenticado e a outra conta tem e-mail cadastrado
**Quando** vincula o procurador sem validade ou com data futura
**Então** a procuração é criada com início agora e a mensagem é "Procurador vinculado com sucesso."

**Cenário 2: E-mail sem conta**
**Dado que** o e-mail não pertence a nenhuma conta
**Quando** o cidadão tenta vincular
**Então** o sistema recusa e orienta o procurador a se cadastrar no Viabiliza primeiro

**Cenário 3: Auto-outorga ou duplicidade**
**Dado que** o e-mail é o da própria conta, ou já existe procuração ativa daquele outorgante para o mesmo procurador
**Quando** o formulário é enviado
**Então** o vínculo não é criado e o sistema explica o motivo

**Cenário 4: Atuar em nome do outorgante**
**Dado que** o usuário recebeu uma procuração ativa
**Quando** inicia a atuação
**Então** o painel informa em nome de quem ele está e o início da atuação fica registrado

**Cenário 5: Procuração que deixou de valer**
**Dado que** a atuação estava aberta e a procuração foi revogada, ainda não começou ou já venceu
**Quando** o procurador faz uma nova ação no portal
**Então** o sistema encerra a atuação e avisa que a representação foi encerrada porque a procuração não está mais ativa

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
