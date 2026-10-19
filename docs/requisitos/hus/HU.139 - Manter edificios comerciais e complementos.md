# HU.139 – MANTER EDIFÍCIOS COMERCIAIS E COMPLEMENTOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Não há cadastro de edifício comercial nem lista pesquisável de sala, loja ou andar. O complemento do imóvel é texto livre, opcional, com até 255 caracteres, na solicitação do portal e no atendimento em contingência. O requerente ou o operador informa sala, loja ou referência sem escolher um edifício cadastrado. Complemento vazio é aceito. O texto informado segue na renovação, no comprovante, no documento de decisão e no protocolo enviado à Junta. Processo já protocolado guarda o complemento que foi informado. Não há versionamento de um cadastro de edifício.

Quando a consulta da inscrição devolve edifício ou conjunto, a ficha de análise mostra o rótulo «Conjunto / Edifício» com esse texto da integração. Isso não grava um cadastro mantido pela SEDUR. O menu da gestão não tem item de edifício comercial nem de complemento.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter edifícios comerciais e complementos](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** requerente **quero** informar o complemento do imóvel em texto livre**, de modo que** sala, loja ou referência fiquem na solicitação sem um cadastro de edifício

## História de Usuário: Manter edifícios comerciais e complementos

**Como** administrador da SEDUR **quero** saber que não há manutenção de edifícios**, de modo que** o complemento digitado e o conjunto vindo do cadastro imobiliário não sejam confundidos com uma lista pesquisável

## Pré-requisitos

- Solicitação em preenchimento no portal ou atendimento em contingência

## Dependências

**Passadas**

- Etapa do imóvel da solicitação

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Complemento do imóvel

**Caminho no menu:** Serviços > Minhas solicitações, na etapa do imóvel. No console, Operação > Nova solicitação (contingência). Não há item de edifício na gestão.

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Texto livre**
**Dado que** o formulário do imóvel está no portal ou na contingência
**Quando** o usuário informa «Sala 2 do edifício comercial»
**Então** a solicitação guarda esse texto

**Cenário 2: Complemento omitido**
**Dado que** o mesmo formulário está aberto
**Quando** o complemento vai vazio
**Então** o envio é aceito, porque o campo é opcional

**Cenário 3: Sem cadastro de edifício**
**Dado que** o administrador procura manter edifícios ou complementos pesquisáveis
**Quando** percorre o menu da gestão
**Então** não há essa tela

**Cenário 4: Edifício vindo do cadastro imobiliário**
**Dado que** a consulta da inscrição devolve edifício ou conjunto
**Quando** a ficha de análise mostra o imóvel
**Então** o rótulo «Conjunto / Edifício» recebe esse texto e nenhum cadastro de edifícios é gravado

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
