# HU.128 – TAXA DE INDEFERIMENTO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

A taxa de indeferimento é a parcela de decisões indeferidas sobre as decisões do recorte, com uma casa decimal. O denominador é o mesmo da taxa de deferimento: decisões filtradas, não protocolos em aberto. Sem decisões, a taxa não aparece; zero decisões não é taxa zero. O deferimento não entra no numerador. Período invertido não calcula. A série sai na mesma resposta dos outros indicadores. O ranking de motivos de queda do fluxo expresso está em outro relatório.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Taxa de indeferimento](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** a taxa de indeferimento**, de modo que** eu veja o peso dos indeferimentos no conjunto decidido

## História de Usuário: Taxa de indeferimento

**Como** gestor **quero** o percentual de indeferidas sobre as decisões do filtro**, de modo que** a ausência de decisão não apareça como zero por cento

## Pré-requisitos

- Atribuição de consultar relatórios

## Dependências

**Passadas**

- Decisões já registradas no recorte

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Taxa de indeferimento

**Caminho no menu:** Indicadores > Indicadores de viabilidade

*[Print da tela](screenshots/gestao-indicadores.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Há decisões**
**Dado que** o recorte tem decisões
**Quando** o gestor consulta
**Então** os contadores de indeferidas e do total batem com a taxa

**Cenário 2: Sem decisão**
**Dado que** o recorte não tem decisão
**Quando** o gestor consulta
**Então** a taxa não aparece

**Cenário 3: Mesma tela**
**Dado que** o gestor abre os indicadores
**Quando** a resposta é montada
**Então** a taxa de indeferimento sai junto com os outros indicadores do recorte

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
