# HU.109 – INTEGRAR COM PORTAL DO CONTRIBUINTE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não envia nem consulta o Portal do Contribuinte. Não há compartilhamento de cadastro com esse portal. Ligar ou desligar as outras integrações não cria esse envio. A consulta de CNPJ usa a base de contato da SEDUR, e o envio da viabilidade à SEFAZ municipal é outro assunto: nenhum dos dois é o Portal do Contribuinte.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Integrar com Portal do Contribuinte](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** cidadão **quero** que o cadastro só seja compartilhado com o Portal do Contribuinte quando esse serviço existir**, de modo que** outra consulta não seja apresentada como esse envio

## História de Usuário: Integrar com Portal do Contribuinte

**Como** gestor **quero** saber que não há sincronização com o Portal do Contribuinte**, de modo que** o cadastro da SEDUR e o envio à SEFAZ não sejam confundidos com esse portal

## Pré-requisitos

- Nenhum, porque o serviço não está ligado

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Portal do Contribuinte

**Caminho no menu:** Não há tela nem item de menu para este envio.

#### Comportamento Esperado Tela 1

**Cenário 1: Compartilhar o cadastro**
**Dado que** um cadastro é gravado no Viabiliza
**Quando** se espera o envio ao Portal do Contribuinte
**Então** o sistema ainda não envia este serviço

**Cenário 2: Outras integrações**
**Dado que** a consulta de CNPJ da SEDUR ou outra integração é ligada ou desligada
**Quando** essa mudança ocorre
**Então** nenhum envio ao Portal do Contribuinte é criado

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
