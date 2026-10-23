# HU.143 – ALTERAR SENHA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |
| 22/10/2026 | Portal sem login próprio: o cidadão entra pelo Portal de Serviços da SEDUR (HU-142); a troca de senha passa a ser só do console | 2.3 | Sudoeste Informática |

---

## Visão Geral

O servidor já autenticado no console troca a própria senha informando a senha atual e a nova, com confirmação. A tela mostra o texto da política de senha. A troca fica registrada como alteração feita pelo próprio usuário.

No console, a troca de senha não aparece no menu: o cabeçalho leva aos dados da conta. Conta da rede da SEDUR não altera senha local e é orientada a mudar a senha no portal de senhas da prefeitura. O cidadão não tem senha no Viabiliza, porque entra pelo Portal de Serviços da SEDUR com a conta GOV.BR (HU-142); por isso Minha conta, no portal, não tem a aba Senha. Esta função não consulta mapa, REDESIM nem a LOUOS, e não encerra as outras sessões da pessoa.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Alterar senha](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** que o servidor troque a própria senha do console**, de modo que** o acesso continue sem o link de recuperação

## História de Usuário: Alterar senha

**Como** servidor autenticado no console com senha local **quero** definir uma nova senha depois de confirmar a atual**, de modo que** eu mantenha o acesso sem usar o link de recuperação

## Pré-requisitos

- Pessoa já autenticada no console, com acesso ao console e termo aceito
- Senha atual correta
- Nova senha obrigatória, confirmada e dentro da política de tamanho e, quando exigido, de maiúsculas, minúsculas, números e símbolos

## Dependências

**Passadas**

- HU-001 — sessão já aberta no console

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Alterar senha

**Caminho no menu:** Console: dados da conta, pelo cabeçalho. A tela não está no menu, e o portal não tem troca de senha

*[Print da tela](screenshots/gestao-senha.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cidadão não troca senha no portal**
**Dado que** o cidadão entrou pelo Portal de Serviços da SEDUR
**Quando** abre Minha conta no portal
**Então** não há aba Senha, e a senha que ele usa é a da conta GOV.BR

**Cenário 2: Senha atual incorreta**
**Dado que** a senha atual digitada não confere
**Quando** o formulário é enviado
**Então** o sistema recusa a troca e a senha gravada não muda

**Cenário 3: Conta da rede da SEDUR no console**
**Dado que** o servidor autenticado no console entra pela rede da SEDUR
**Quando** tenta alterar a senha no console
**Então** o sistema recusa e orienta a alterar a senha no portal de senhas da prefeitura, sem trocar a senha local

**Cenário 4: Troca concluída no console**
**Dado que** a conta do console é local e a senha atual confere
**Quando** a nova senha é aceita
**Então** o console informa que a senha foi alterada com sucesso e a alteração fica registrada

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
