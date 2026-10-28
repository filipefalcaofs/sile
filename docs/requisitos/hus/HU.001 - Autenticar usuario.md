# HU.001 – AUTENTICAR USUÁRIO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |
| 22/10/2026 | Portal sem login próprio: o cidadão entra pelo Portal de Serviços da SEDUR (HU-142); esta HU passa a tratar só a entrada do console | 2.3 | Sudoeste Informática |

---

## Visão Geral

O servidor entra no console com e-mail ou usuário da rede da SEDUR e senha. O cidadão não entra por esta tela: a entrada dele no portal do Viabiliza é sempre pelo Portal de Serviços da SEDUR, na HU-142, e o portal não tem tela de login com CPF e senha. As duas entradas são independentes: sair do console não encerra a sessão do portal, e o portal nunca abre uma tela do console.

Conta da rede da SEDUR só vale a senha conferida na rede institucional. A senha local da conta não é usada nesse caso, e a senha digitada no primeiro acesso não fica guardada como senha do Viabiliza. Se a entrada pela rede estiver desligada, a conta da rede é recusada. Não há segundo fator. Este fluxo não consulta mapa, REDESIM, CNAE nem a LOUOS.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Autenticar usuário](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** separar a entrada do servidor da entrada do cidadão**, de modo que** cada um use só o ambiente e as funções da própria sessão

## História de Usuário: Autenticar usuário

**Como** servidor com conta no Viabiliza **quero** entrar no console com a credencial do meu ambiente**, de modo que** eu use somente o que a minha sessão permite

## Pré-requisitos

- Identificador (e-mail ou usuário da rede da SEDUR) e senha, com até 255 caracteres no identificador
- Conta ativa, e-mail confirmado e permissão de uso do console
- Depois da entrada, o termo de proteção de dados vigente precisa estar aceito para seguir no console
- Tentativas incorretas repetidas bloqueiam temporariamente novas tentativas, por conta e por endereço de rede

## Dependências

**Passadas**

- HU-004 — conta local criada pela gestão, quando a entrada não for pela rede da SEDUR

**Futuras**

- HU-141 — e-mail confirmado para concluir a entrada no console
- HU-145 — aceite do termo vigente depois da entrada

---

## Interface de Usuário

### Tela 1 — Entrar no console

**Caminho no menu:** Tela de entrada do console, antes do menu

*[Print da tela](screenshots/gestao-login.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cidadão não entra por esta tela**
**Dado que** a pessoa abre o portal do Viabiliza sem ter entrado
**Quando** tenta usar uma tela que exige entrada
**Então** é levada à entrada do Portal de Serviços da SEDUR, como descrito na HU-142, e não vê tela de CPF e senha do Viabiliza

**Cenário 2: Credencial errada não revela a conta**
**Dado que** o identificador não existe ou a senha está incorreta no console
**Quando** a pessoa tenta entrar
**Então** o sistema responde que as credenciais não coincidem com as registradas, conta a tentativa e registra falha, sem dizer se a conta existe

**Cenário 3: Servidor entra pela rede da SEDUR**
**Dado que** a entrada pela rede da SEDUR está ligada e a conta é desse tipo
**Quando** o servidor informa o usuário da rede e a senha aceita pela rede institucional
**Então** a sessão do console é criada sem usar a senha local; se a rede estiver indisponível, o acesso não é criado e a indisponibilidade fica registrada

**Cenário 4: Conta sem acesso ao console**
**Dado que** a credencial local está correta, a conta está ativa e o e-mail foi confirmado, mas a conta não usa o console
**Quando** a pessoa tenta entrar no console
**Então** o sistema recusa com a mensagem de conta sem acesso ao console e registra a tentativa barrada

**Cenário 5: Primeiro acesso pela rede da SEDUR**
**Dado que** o identificador ainda não existe, a entrada pela rede está ligada e a rede devolve a pessoa
**Quando** o servidor entra no console
**Então** a conta é criada como conta da rede da SEDUR, com acesso ao console, e a senha digitada não fica utilizável como senha local

**Cenário 6: Conta inativa ou e-mail pendente**
**Dado que** a credencial está correta
**Quando** a conta está inativa, ou o e-mail ainda não foi confirmado
**Então** o sistema informa conta inativa ou pede a confirmação do e-mail institucional, e não abre a sessão

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
