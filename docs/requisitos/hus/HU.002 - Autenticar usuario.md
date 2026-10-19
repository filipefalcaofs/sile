# HU.002 – AUTENTICAR USUÁRIO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O cidadão entra no portal com CPF e senha da própria conta. O servidor entra no console com e-mail ou usuário da rede da SEDUR e senha. As duas entradas são independentes: sair do console não encerra a sessão do portal, e o portal nunca abre uma tela do console.

Conta da rede da SEDUR só vale a senha conferida na rede institucional. A senha local da conta não é usada nesse caso, e a senha digitada no primeiro acesso não fica guardada como senha do Viabiliza. Se a entrada pela rede estiver desligada, a conta da rede é recusada. Não há segundo fator nem acesso pela rede da SEDUR no portal. Este fluxo não consulta mapa, REDESIM, CNAE nem a LOUOS.

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

**Como** a SEDUR **quero** separar a entrada do cidadão e a do servidor**, de modo que** cada um use só o ambiente e as funções da própria sessão

## História de Usuário: Autenticar usuário

**Como** usuário com conta no Viabiliza **quero** entrar no portal ou no console com a credencial do meu ambiente**, de modo que** eu use somente o que a minha sessão permite

## Pré-requisitos

- Portal: CPF e senha local; a pessoa ainda não está no portal
- Console: identificador (e-mail ou usuário da rede da SEDUR) e senha, com até 255 caracteres no identificador
- Conta ativa, e-mail confirmado no console, e permissão de uso do console quando a entrada for de servidor
- Depois da entrada, o termo de proteção de dados vigente precisa estar aceito para seguir no ambiente
- Tentativas incorretas repetidas bloqueiam temporariamente novas tentativas, por conta e, no console, também por endereço de rede

## Dependências

**Passadas**

- HU-001 — conta local do cidadão, quando a entrada do portal não for pela rede da SEDUR

**Futuras**

- HU-005 — e-mail confirmado para concluir a entrada no console e para usar o portal autenticado
- HU-006 — aceite do termo vigente depois da entrada

---

## Interface de Usuário

### Tela 1 — Entrar no Viabiliza

**Caminho no menu:** Tela de entrada do portal e tela de entrada do console, antes do menu

*[Print da tela](screenshots/gestao-login.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cidadão entra com CPF**
**Dado que** existe conta ativa com CPF e senha local
**Quando** a pessoa envia a entrada do portal
**Então** a sessão do portal é aberta, o acesso fica registrado e o destino não é uma tela do console

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
**Quando** a conta está inativa, ou o e-mail do console ainda não foi confirmado
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
