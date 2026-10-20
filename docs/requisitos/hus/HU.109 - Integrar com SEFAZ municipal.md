# HU.109 – INTEGRAR COM SEFAZ MUNICIPAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não envia a viabilidade deferida à SEFAZ municipal. No deferimento, a tentativa fica registrada como bloqueada, nenhuma comunicação é marcada como enviada e a decisão não é desfeita. Quando o serviço está indisponível, o processo não é dado como enviado. O indeferimento não é enviado por este caminho: fica registrado como ignorado para a SEFAZ.

Também não há aviso simulado de escritório virtual à SEFAZ. A consulta da inscrição imobiliária é outra integração e não conta como envio da viabilidade. Não há retorno real de homologação da SEFAZ nesta história.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Integrar com SEFAZ municipal](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** gestor **quero** comunicar o deferimento à SEFAZ quando o serviço existir**, de modo que** a decisão local não seja desfeita enquanto o envio não ocorre

## História de Usuário: Integrar com SEFAZ municipal

**Como** gestor **quero** que o deferimento não apareça como enviado à SEFAZ**, de modo que** a pendência fique explícita e o desfecho já gravado permaneça

## Pré-requisitos

- Processo deferido, para haver a tentativa de envio
- O serviço de envio à SEFAZ ainda não está disponível

## Dependências

**Passadas**

- Decisão de deferimento já gravada

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Envio da viabilidade à SEFAZ

**Caminho no menu:** Não há tela de envio. A pendência aparece na trilha de integrações depois do deferimento.

#### Comportamento Esperado Tela 1

**Cenário 1: Deferimento**
**Dado que** o processo foi deferido
**Quando** o sistema trata o envio à SEFAZ
**Então** o sistema ainda não envia este serviço, a trilha registra bloqueio e o processo não é dado como enviado

**Cenário 2: A decisão permanece**
**Dado que** o envio não ocorreu
**Quando** a pendência é registrada
**Então** o deferimento já gravado não é desfeito

**Cenário 3: Indeferimento**
**Dado que** o processo foi indeferido
**Quando** o resultado é emitido
**Então** a SEFAZ não é chamada e o registro informa que o envio foi ignorado

**Cenário 4: Inscrição imobiliária**
**Dado que** a inscrição do imóvel foi consultada
**Quando** se confere o envio da viabilidade
**Então** essa consulta não conta como comunicação da decisão à SEFAZ

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
