# HU.154 – VINCULAR CNAES SECUNDÁRIOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Quem tem vínculo ativo substitui o conjunto de CNAEs secundários da empresa pelos CNAEs ativos escolhidos. A lista enviada é o conjunto final, não um acréscimo: o que sair deixa de estar vinculado, salvo o principal, que permanece. Lista vazia remove todos os secundários. Não há quantidade máxima de secundários.

O CNAE principal não pode constar da lista. Cada item precisa ser CNAE ativo, sem repetição. A busca de opções é a mesma do principal, somente ativos. A troca fica registrada com as subclasses de antes e de depois. CNAE inativo não entra por esta tela do portal.

---

## Sumário

- [Épico: EP03 — Cadastro Empresarial](#épico)
- [História de Usuário: Vincular CNAEs secundários](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP03 — Cadastro Empresarial

**Como** a SEDUR **quero** que os CNAEs secundários reflitam o conjunto escolhido**, de modo que** as atividades complementares do cadastro fiquem atualizadas sem perder o principal

## História de Usuário: Vincular CNAEs secundários

**Como** cidadão com vínculo ativo **quero** marcar os CNAEs secundários**, de modo que** o cadastro reflita as atividades complementares

## Pré-requisitos

- Cidadão autenticado no portal, com e-mail confirmado e termo aceito
- Vínculo ativo com a empresa
- Lista de CNAEs ativos, distintos, sem o principal; lista vazia é válida

## Dependências

**Passadas**

- HU-153 — o principal, quando existir, é preservado e não pode entrar na lista
- HU-034 — somente CNAE ativo da tabela oficial

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — CNAEs secundários

**Caminho no menu:** Serviços > Minhas empresas > detalhe da empresa

*[Print da tela](screenshots/portal-empresas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Sincronizar secundários**
**Dado que** há vínculo ativo e uma lista de CNAEs ativos diferente do principal
**Quando** o cidadão salva
**Então** os secundários passam a ser exatamente essa lista, o principal permanece, a mensagem é "CNAEs secundários atualizados com sucesso." e o registro guarda o antes e o depois

**Cenário 2: Remover todos**
**Dado que** há vínculo ativo
**Quando** o cidadão salva a lista vazia
**Então** a empresa fica sem CNAEs secundários

**Cenário 3: Principal na lista ou CNAE inválido**
**Dado que** a lista contém o principal, um inativo, um inexistente ou um item repetido
**Quando** a seleção é enviada
**Então** o sistema recusa com a mensagem correspondente: principal entre os secundários, CNAE inativo ou inexistente, ou CNAE duplicado

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
