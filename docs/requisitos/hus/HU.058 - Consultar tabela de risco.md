# HU.058 – CONSULTAR TABELA DE RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Quem consulta CNAEs vê, na ficha do código, o risco municipal da versão vigente. A lista de CNAEs mostra código, descrição, situação e classificação do CNAE. Não mostra o risco. Abrir a ficha exige quem mantém o cadastro: não há modo só de leitura.

As opções de nível são Baixo, Médio risco e Alto. Médio risco é o nome da SEDUR para o nível do meio. Sem versão municipal vigente, a ficha avisa a ausência e não inventa classificação. A ficha não lista risco sanitário, perguntas de condicionante nem o texto das condicionantes do decreto. Essas frases aparecem na fundamentação quando o sistema classifica.

A consulta de viabilidade mostra a classificação completa: risco municipal, risco sanitário, encaminhamento, fundamentação e versões. A exportação da lista de CNAEs não é uma lista de risco à parte.

O sistema ainda não oferece uma tela que liste, com filtro, todos os CNAEs com risco municipal e sanitário lado a lado, nem o histórico de versões de risco na ficha.

---

## Sumário

- [Épico: EP06 — Classificação de Risco](#épico)
- [História de Usuário: Consultar tabela de risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP06 — Classificação de Risco

**Como** a SEDUR **quero** consultar o risco municipal vigente do CNAE na ficha**, de modo que** a conferência não dependa de abrir a planilha do decreto

## História de Usuário: Consultar tabela de risco

**Como** servidor que consulta CNAEs **quero** ver o risco municipal vigente do código**, de modo que** eu confira a classificação usada pelo sistema

## Pré-requisitos

- Versão municipal vigente, para a ficha mostrar um nível
- Quem consulta CNAEs vê a lista; quem mantém CNAEs abre a ficha
- Gatilhos de risco são consultados e editados em Regras > Gatilhos de risco, na mesma tela de manutenção

## Dependências

**Passadas**

- HU.053 — Classificar CNAE por risco
- HU.037 — Atualizar classificação de risco, quando o nível da ficha já foi gravado

**Futuras**

- Consulta prévia, que exibe a classificação completa do sistema

---

## Interface de Usuário

### Tela 1 — Consultar tabela de risco

**Caminho no menu:** Regras > CNAEs

*[Print da tela](screenshots/gestao-cnaes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Ficha com versão vigente**
**Dado que** existe versão municipal vigente e o CNAE está nela
**Quando** quem mantém o cadastro abre a ficha
**Então** o campo mostra Baixo, Médio risco ou Alto

**Cenário 2: Sem versão municipal vigente**
**Dado que** não há versão municipal vigente
**Quando** a ficha abre
**Então** o sistema informa a ausência da versão e não apresenta uma classificação inventada

**Cenário 3: Consulta de viabilidade**
**Dado que** a consulta de viabilidade classifica o CNAE
**Quando** o resultado de risco é lido
**Então** aparecem risco municipal, risco sanitário, encaminhamento, fundamentação e versões

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
