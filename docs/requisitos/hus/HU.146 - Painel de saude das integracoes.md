# HU.146 – PAINEL DE SAÚDE DAS INTEGRAÇÕES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não mostra um painel de saúde das integrações, a fila de nova tentativa e um reprocessamento manual na tela. Consultar o registro das chamadas não reenvia nada. Testar a conexão da Junta ou da inscrição imobiliária, na configuração, só exercita o endereço informado e não marca a integração como saudável para o negócio.

Há uma rotina que reenvia à Junta os pareceres ainda sem confirmação. Ela é só desse canal e não é um painel. O envio da viabilidade à SEFAZ municipal continua sem transmissão: o sistema ainda não envia este serviço e não há, na tela, fila de nova tentativa dessa comunicação.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Painel de saúde das integrações](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** gestor **quero** ver o estado das integrações e reprocessar falha pela tela quando esse painel existir**, de modo que** o registro das chamadas não seja lido como reenvio

## História de Usuário: Painel de saúde das integrações

**Como** gestor **quero** saber que ainda não há painel de saúde**, de modo que** teste de conexão e reenvio de parecer à Junta não sejam tomados por essa tela

## Pré-requisitos

- Nenhum, porque o painel não existe

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Saúde das integrações

**Caminho no menu:** Não há tela. O registro das chamadas fica em Auditoria > Log de integrações e não reenvia. O teste de conexão fica em Configuração > APIs.

#### Comportamento Esperado Tela 1

**Cenário 1: Painel**
**Dado que** o gestor procura o estado das integrações e um botão para reprocessar
**Quando** percorre o console
**Então** essa tela não existe e o sistema ainda não oferece este serviço

**Cenário 2: Registro das chamadas**
**Dado que** quem consulta a auditoria abre o registro das integrações
**Quando** a lista é exibida
**Então** nenhuma chamada nova é disparada

**Cenário 3: Teste de conexão**
**Dado que** o administrador testa o endereço da Junta ou da inscrição imobiliária
**Quando** o teste termina
**Então** isso não marca a integração como saudável para o negócio

**Cenário 4: Parecer à Junta**
**Dado que** há pareceres ainda sem confirmação da Junta
**Quando** a rotina desse canal reenvia
**Então** o reenvio não é um painel de todas as integrações

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
