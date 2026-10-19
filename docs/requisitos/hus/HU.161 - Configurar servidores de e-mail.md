# HU.161 – CONFIGURAR SERVIDORES DE E-MAIL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O administrador mantém os servidores de e-mail e escolhe o padrão ativo que o envio passa a usar. A lista ordena o padrão primeiro e depois o nome, sem paginação. O único tipo aceito é o envio por servidor de correio. A proteção da conexão pode ser TLS, SSL ou nenhuma. Incluir grava nome, servidor, porta, proteção, tempo limite, usuário, senha, remetente e situação. Se for marcado como padrão, esse servidor passa a ser o padrão. Na edição, senha em branco não substitui a atual. A senha não volta em claro e não entra no registro da alteração. Excluir apaga o servidor e registra a exclusão, sem a senha. Testar exige um destinatário e envia uma mensagem de verdade. Sucesso e falha ficam registrados, e a falha aparece no destinatário. O monitoramento da fila de envios é outra tela.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Configurar servidores de e-mail](#história-de-usuário-configurar-servidores-de-e-mail)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Servidores de e-mail](#tela-1--servidores-de-e-mail)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** escolher o servidor de e-mail padrão**, de modo que** os avisos saiam pela configuração gravada.

## História de Usuário: Configurar servidores de e-mail

**Como** administrador autorizado a configurar o e-mail **quero** cadastrar o servidor, testar o envio e marcá-lo como padrão**, de modo que** a senha não seja reexibida e o teste seja um envio real.

## Pré-requisitos

- Administrador autorizado a configurar servidores de e-mail
- Nome, servidor, porta de 1 a 65535, remetente válido e nome do remetente
- Destinatário válido, para o teste

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- O acompanhamento do que está na fila, do que saiu e do que falhou fica no monitoramento de e-mails

---

## Interface de Usuário

### Tela 1 — Servidores de e-mail

**Caminho no menu:** Configuração > Servidores de e-mail

*[Print da tela](screenshots/gestao-email.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Servidor padrão**
**Dado que** o administrador está autorizado
**Quando** cadastra um servidor marcado como padrão
**Então** esse servidor passa a ser o padrão do envio

**Cenário 2: Senha em branco**
**Dado que** o servidor já tem senha
**Quando** a edição envia a senha vazia
**Então** a senha armazenada permanece

**Cenário 3: Teste real**
**Dado que** o servidor está gravado e há um e-mail destinatário
**Quando** o administrador testa a conexão
**Então** o sistema tenta o envio e registra sucesso ou falha, sem gravar a senha

**Cenário 4: Excluir**
**Dado que** o servidor está cadastrado
**Quando** o administrador exclui
**Então** o registro é removido e a exclusão fica registrada, sem a senha

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
