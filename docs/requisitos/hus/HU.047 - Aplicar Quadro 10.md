# HU.047 – APLICAR QUADRO 10
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O motor lê, na versão vigente do Quadro 10, se o grupo de uso é permitido, permitido com condições ou proibido na zona já identificada. Não busca a zona de novo e não aceita zona digitada livremente. Zona ausente ou não identificada deixa o quadro indisponível, sem consultar a tabela. Sem enquadramento identificado, o quadro também fica indisponível, salvo linha de CNLU que já traga grupo: nesse caso o Quadro 10 é consultado mesmo assim. Sem versão do quadro, o resultado da dimensão é não encontrado.

A busca usa a grafia oficial da zona e, se não achar, o nome original. Com subgrupo, vale a linha daquele subgrupo; se existem linhas de subgrupo e a do subgrupo não existe, o sistema não cai na regra geral do grupo. Sem subgrupo, a regra geral só vale se os subgrupos não tiverem permissões diferentes. Observação (a) ou (b), quando a permissão não é proibida, consulta a lista correspondente: fora da lista, a permissão vira proibido; sem número do logradouro e sem bairro, a dimensão fica indisponível; na lista, a permissão da linha permanece. Outro texto de observação não dispara lista. Proibido no parecer vira não permitido. Permitido com condições entra como condicionante de zona. Zona ou regra ausente deixam o parecer pendente. A observação (c) não é reconhecida.

---

## Sumário

- [Épico: EP05 — Motor de Regras da LOUOS](#épico)
- [História de Usuário: Aplicar Quadro 10](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP05 — Motor de Regras da LOUOS

**Como** a SEDUR **quero** ler a permissão da zona para o grupo enquadrado**, de modo que** o parecer não invente permissão quando a zona ou a regra faltam

## História de Usuário: Aplicar Quadro 10

**Como** analista **quero** ver se o uso é permitido, permitido com condições ou proibido na zona**, de modo que** o parecer consolidado use a versão vigente do Quadro 10

## Pré-requisitos

- Zona já identificada no território
- Enquadramento com grupo, ou linha de CNLU com grupo
- Versão vigente do Quadro 10, ou a versão de uma data ou de uma simulação

## Dependências

**Passadas**

- HU-028 — versão vigente do Quadro 10
- HU-040 — zona identificada
- HU-046 — grupo de uso do enquadramento

**Futuras**

- HU-051 — o resultado único usa esta permissão

---

## Interface de Usuário

### Tela 1 — Quadro 10 no parecer

**Caminho no menu:** O resultado aparece em Consultar viabilidade e no processo. A manutenção fica em Regras > Motor de regras

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Linha encontrada**
**Dado que** a zona está identificada e existe linha do grupo na versão vigente
**Quando** o Quadro 10 é aplicado
**Então** a dimensão fica identificada, com a permissão, a referência de condicionante e a base legal da linha

**Cenário 2: Zona não identificada**
**Dado que** a zona não está identificada
**Quando** o motor enquadra
**Então** o Quadro 10 fica indisponível e a tabela não é consultada

**Cenário 3: Fora da lista da observação**
**Dado que** a linha tem observação (a) ou (b) e o logradouro ou o bairro está fora da lista
**Quando** o Quadro 10 é aplicado
**Então** a permissão devolvida é proibido

**Cenário 4: Sem dado para a lista**
**Dado que** a linha tem observação (a) ou (b) e não há número do logradouro nem bairro
**Quando** o Quadro 10 é aplicado
**Então** a dimensão fica indisponível

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
