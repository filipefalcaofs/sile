# HU.156 – MANTER VALORES TLL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O administrador mantém os valores da TLL por exercício. A lista filtra por texto, situação e exercício, pagina em 10, 15, 25 ou 50 itens e ordena pelo exercício mais recente, pelo código da TLL e pela especificação. Cada valor mostra código, especificação, exercício, valor, taxa de serviço, código SEDUR, código e nome do serviço da SEFAZ, situação e quantidade de CNAEs vinculados. A mesma página lista as versões do exercício, com autor, fonte e se a pessoa atual pode publicar. Incluir e editar gravam a linha. A combinação de código, exercício e especificação não se repete. Valor novo nasce ativo quando a situação não vem. Não há exclusão: desativar tira o valor do cálculo e preserva o registro. É possível ver os CNAEs ligados àquele valor. Gerar o exercício seguinte copia a origem para o destino com fator e decreto, em rascunho, e pede revisão e publicação por um segundo usuário. O exercício gerado não entra em vigor na cópia. Entra na publicação. Quem gerou o rascunho não publica o próprio exercício. Esta tela guarda a relação de valores e os códigos usados no envio à SEFAZ. Não emite o documento de arrecadação nem homologa o pagamento.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter valores TLL](#história-de-usuário-manter-valores-tll)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Valores TLL](#tela-1--valores-tll)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** manter os valores da TLL do exercício**, de modo que** o cálculo use a relação publicada e não o rascunho.

## História de Usuário: Manter valores TLL

**Como** administrador autorizado a manter os parâmetros **quero** cadastrar o valor, gerar o exercício pelo fator do decreto e publicar com outro usuário**, de modo que** o exercício só vigore depois da publicação por quem não o gerou.

## Pré-requisitos

- Administrador autorizado a manter os parâmetros
- Exercício entre 2000 e 2200
- Para gerar o exercício, destino posterior à origem, fator maior que zero e no máximo 2, e decreto informado
- Outro usuário, diferente do autor do rascunho, para publicar

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- A emissão do documento de arrecadação e a homologação na SEFAZ não ocorrem nesta tela

---

## Interface de Usuário

### Tela 1 — Valores TLL

**Caminho no menu:** Configuração > Valores TLL

*[Print da tela](screenshots/gestao-tll.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Combinação repetida**
**Dado que** já existe valor para o mesmo código, exercício e especificação
**Quando** o administrador cadastra a mesma combinação
**Então** a validação informa que já existe valor para esse código, exercício e especificação

**Cenário 2: Inativar**
**Dado que** o valor está ativo
**Quando** o administrador inverte a situação
**Então** o valor fica inativo, sai do cálculo e continua na lista

**Cenário 3: Rascunho do exercício**
**Dado que** há exercício de origem e um destino posterior, com fator maior que zero e no máximo 2
**Quando** o administrador gera o exercício
**Então** o resultado fica em rascunho e a mensagem pede um segundo usuário para publicar

**Cenário 4: Publicação pelo autor**
**Dado que** o rascunho do exercício foi gerado pelo próprio usuário
**Quando** esse usuário tenta publicar
**Então** a publicação é recusada

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
