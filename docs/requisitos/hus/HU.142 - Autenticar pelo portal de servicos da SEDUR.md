# HU.142 – AUTENTICAR PELO PORTAL DE SERVIÇOS DA SEDUR
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |
| 22/10/2026 | O portal passa a abrir já autenticado a partir do Portal de Serviços da SEDUR, com o mesmo login, no lugar do botão próprio de login com GOV.BR | 2.3 | Sudoeste Informática |

---

## Visão Geral

O cidadão entra no Portal de Serviços da SEDUR com a conta GOV.BR, escolhe na Carta de Serviços um serviço de Viabilidade de Localização e o portal do Viabiliza abre já com ele identificado, sem pedir login de novo. O Viabiliza não tem tela de login própria para o cidadão: a entrada é sempre a do Portal de Serviços. Funciona como a entrada do servidor pela rede da SEDUR: o Portal de Serviços confirma quem é a pessoa, a conta dela fica guardada no Viabiliza e, a partir daí, a sessão é do próprio Viabiliza.

Se o cidadão ainda não entrou no Portal de Serviços, o portal pede a entrada com GOV.BR antes de abrir o Viabiliza. Quem abre o endereço do Viabiliza direto, sem ter entrado, é levado à entrada do Portal de Serviços e, ao concluir, volta ao Viabiliza na página que tentou abrir.

O cidadão é reconhecido pelo CPF. Quem já tem conta no Viabiliza com esse CPF entra nela. Quem ainda não tem conta ganha uma, como cidadão, com o nome e o e-mail do cadastro, desde que o e-mail não esteja em outra conta (HU-140). A conta GOV.BR precisa ter o nível mínimo exigido, bronze por padrão; abaixo disso, a entrada é recusada com a orientação para elevar o nível. Conta inativa no Viabiliza não entra e informa para procurar a SEDUR.

Depois da entrada, valem as mesmas regras do portal: e-mail confirmado e aceite do termo vigente, quando houver. Conta nova já nasce com o e-mail confirmado e ainda passa pelo termo. Quando o cidadão sai do Portal de Serviços, ou a entrada dele lá expira, a sessão do Viabiliza também é encerrada. O console da gestão não usa esta entrada.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico-ep01--identidade-acesso-e-segurança)
- [História de Usuário: Autenticar pelo portal de serviços da SEDUR](#história-de-usuário-autenticar-pelo-portal-de-serviços-da-sedur)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Chegada pelo Portal de Serviços da SEDUR](#tela-1--chegada-pelo-portal-de-serviços-da-sedur)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** cidadão **quero** entrar no Viabiliza com a mesma entrada do Portal de Serviços da SEDUR**, de modo que** eu não precise de outro login para pedir a viabilidade.

## História de Usuário: Autenticar pelo portal de serviços da SEDUR

**Como** cidadão que entrou no Portal de Serviços da SEDUR **quero** abrir o serviço de Viabilidade de Localização e cair no meu painel do Viabiliza**, de modo que** eu continue o atendimento sem me identificar de novo.

## Pré-requisitos

- Conta GOV.BR com CPF e com o nível mínimo exigido
- Entrada feita no Portal de Serviços da SEDUR (servicos.sedur.salvador.ba.gov.br)
- Serviços de Viabilidade de Localização da Carta de Serviços levando ao Viabiliza
- No primeiro acesso, nome e e-mail no cadastro, sem o e-mail estar em outra conta do Viabiliza

## Dependências

**Passadas**

- Entrada no Portal de Serviços da SEDUR com a conta GOV.BR

**Futuras**

- HU-140 — conta criada no primeiro acesso
- HU-145 — conta nova ainda precisa aceitar o termo vigente, quando houver

---

## Interface de Usuário

### Tela 1 — Chegada pelo Portal de Serviços da SEDUR

**Caminho no menu:** Portal de Serviços da SEDUR › Carta de Serviços › Viabilidade de Localização. O Viabiliza não tem tela de login própria para o cidadão nem item de menu para esta entrada.

*[Print da tela](screenshots/portal-servicos-sedur.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cidadão já entrou no Portal de Serviços**
**Dado que** o cidadão entrou no Portal de Serviços da SEDUR e já tem conta ativa no Viabiliza com o mesmo CPF
**Quando** abre um serviço de Viabilidade de Localização
**Então** o Viabiliza abre no painel dele, sem pedir login, e a entrada fica registrada

**Cenário 2: Cidadão ainda não entrou no Portal de Serviços**
**Dado que** o cidadão não entrou no Portal de Serviços, ou abriu o endereço do Viabiliza direto
**Quando** tenta abrir o Viabiliza
**Então** é levado à entrada do Portal de Serviços com GOV.BR e, ao concluir, volta ao Viabiliza já identificado, na página que tentou abrir

**Cenário 3: Primeiro acesso ao Viabiliza**
**Dado que** o cidadão ainda não tem conta no Viabiliza e o cadastro traz nome e e-mail que não estão em outra conta
**Quando** chega ao Viabiliza
**Então** a conta de cidadão é criada no Viabiliza (HU-140), com o e-mail já confirmado, e o cadastro fica registrado

**Cenário 4: Nível da conta GOV.BR insuficiente ou conta inativa**
**Dado que** a conta GOV.BR está abaixo do nível mínimo ou a conta do cidadão no Viabiliza está inativa
**Quando** o cidadão chega ao Viabiliza
**Então** a entrada é recusada com a mensagem para elevar o nível da conta GOV.BR ou para procurar a SEDUR

**Cenário 5: E-mail já usado por outra conta**
**Dado que** o cidadão ainda não tem conta no Viabiliza e o e-mail do cadastro já está em outra conta
**Quando** chega ao Viabiliza pela primeira vez
**Então** nenhuma conta é criada e a mensagem informa que o e-mail já está em uso

**Cenário 6: Entrada indisponível ou não confirmada**
**Dado que** a entrada pelo Portal de Serviços está fora do ar, não foi concluída ou já expirou
**Quando** o cidadão tenta abrir o Viabiliza
**Então** o Viabiliza não abre a sessão e a mensagem orienta a entrar de novo pelo Portal de Serviços

**Cenário 7: Saída do Portal de Serviços**
**Dado que** o cidadão está usando o Viabiliza
**Quando** sai do Portal de Serviços da SEDUR, ou a entrada dele lá expira
**Então** a sessão do Viabiliza também é encerrada e, para continuar, ele precisa entrar de novo pelo Portal de Serviços

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
