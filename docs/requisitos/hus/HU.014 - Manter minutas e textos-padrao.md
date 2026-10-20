# HU.014 – MANTER MINUTAS E TEXTOS-PADRÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador mantém a biblioteca de trechos que o analista insere no parecer. A lista busca no conteúdo, filtra por categoria e por situação, ordena por categoria ou versão e pagina em 10, 15, 25 ou 50 itens, com padrão de 15. Incluir grava categoria, conteúdo e situação, com versão 1. Texto novo nasce ativo quando a situação não vem. Se a edição muda o conteúdo, a versão soma um. Mudar só a categoria ou a situação mantém a versão. Não há exclusão. Desativar tira o trecho da biblioteca oferecida ao analista e o histórico da versão permanece. Criar, atualizar e desativar ficam registrados. A leitura da lista ativa no parecer é de quem analisa processos, em outro fluxo. Quem mantém esta biblioteca é a mesma pessoa autorizada a manter os parâmetros. A inserção do trecho no parecer não ocorre aqui.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter minutas e textos-padrão](#história-de-usuário-manter-minutas-e-textos-padrão)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Minutas e textos-padrão](#tela-1--minutas-e-textos-padrão)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** manter trechos pré-aprovados do parecer**, de modo que** a versão registre quando o conteúdo mudou.

## História de Usuário: Manter minutas e textos-padrão

**Como** administrador autorizado a manter os parâmetros **quero** cadastrar, corrigir e desativar textos-padrão por categoria**, de modo que** o analista use só os trechos ativos e a versão suba somente quando o conteúdo mudar.

## Pré-requisitos

- Administrador autorizado a manter os parâmetros
- Categoria com até 100 caracteres e conteúdo informado

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- A inserção do trecho no parecer fica no fluxo de análise, fora desta tela

---

## Interface de Usuário

### Tela 1 — Minutas e textos-padrão

**Caminho no menu:** Configuração > Minutas e textos-padrão

*[Print da tela](screenshots/gestao-minutas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Primeira versão**
**Dado que** o administrador está autorizado
**Quando** cadastra categoria e conteúdo
**Então** o texto é gravado com versão 1

**Cenário 2: Conteúdo novo**
**Dado que** o texto está na versão 1
**Quando** a edição muda o conteúdo
**Então** a versão passa a 2 e o registro marca que o conteúdo mudou

**Cenário 3: Só a categoria**
**Dado que** o texto está na versão 2
**Quando** a edição muda a categoria e mantém o conteúdo
**Então** a versão continua 2

**Cenário 4: Desativar**
**Dado que** o texto está ativo
**Quando** o administrador inverte a situação
**Então** o texto fica inativo, sai da biblioteca oferecida ao analista e não é apagado

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
