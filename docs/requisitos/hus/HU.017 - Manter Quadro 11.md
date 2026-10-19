# HU.017 – MANTER QUADRO 11
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O Viabiliza não oferece a manutenção do Quadro 11. No Motor de regras, os quadros que a pessoa consulta e versiona são o Quadro 10 e o Quadro 11A. O Quadro 11B também não aparece.

Pedir o Quadro 11 na consulta cai no Quadro 10. Nas operações de rascunho e de publicação, o sistema recusa e informa que o quadro é inválido ou está ausente. As condições por classe de via que a tela mantém são as do Quadro 11A. Não há inclusão, importação, rascunho, publicação nem simulação de um Quadro 11 próprio.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter Quadro 11](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** deixar claro que o Quadro 11 não é mantido à parte**, de modo que** a condição por via seja procurada no Quadro 11A

## História de Usuário: Manter Quadro 11

**Como** administrador **quero** localizar o Quadro 11 no Motor de regras**, de modo que** eu saiba que esse quadro não está disponível para versionar

## Pré-requisitos

- Acesso ao Motor de regras, para quem consulta ou mantém as regras da LOUOS
- Os quadros aceitos na manutenção são somente o Quadro 10 e o Quadro 11A

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- HU-018 — a manutenção por classe de via é a do Quadro 11A

---

## Interface de Usuário

### Tela 1 — Motor de regras

**Caminho no menu:** Regras > Motor de regras

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Quadro ausente do catálogo**
**Dado que** o administrador procura um Quadro 11 para consultar ou versionar
**Quando** abre os quadros do Motor de regras
**Então** só encontra o Quadro 10 e o Quadro 11A

**Cenário 2: Pedido recusado**
**Dado que** o administrador tenta abrir rascunho ou publicar indicando o Quadro 11
**Quando** confirma a operação
**Então** o sistema recusa e informa que o quadro é inválido ou está ausente

**Cenário 3: Consulta sem o quadro pedido**
**Dado que** a consulta pede um quadro que não é o 10 nem o 11A
**Quando** a aba de quadros é aberta
**Então** o sistema mostra o Quadro 10

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
