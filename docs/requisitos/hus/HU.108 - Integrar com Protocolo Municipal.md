# HU.108 – INTEGRAR COM PROTOCOLO MUNICIPAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não envia o processo a um protocolo municipal externo. O número de protocolo do Viabiliza, o comprovante e a consulta pública de protocolo nascem e são lidos no próprio sistema. Receber o protocolo que a Junta enviou não abre chamada ao protocolo da prefeitura. Comprovante e consulta no portal não contam como essa integração. Não há confirmação de registro em sistema municipal de tramitação.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Integrar com Protocolo Municipal](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** gestor **quero** que o processo só conste no protocolo da prefeitura quando esse serviço existir**, de modo que** o protocolo interno não seja apresentado como registro municipal

## História de Usuário: Integrar com Protocolo Municipal

**Como** gestor **quero** saber que o envio ao protocolo municipal ainda não ocorre**, de modo que** comprovante e consulta no portal não sejam lidos como essa integração

## Pré-requisitos

- Nenhum, porque o serviço não está ligado

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Protocolo municipal

**Caminho no menu:** Não há tela. O comprovante do Viabiliza fica na solicitação do cidadão e não registra o processo em protocolo externo.

#### Comportamento Esperado Tela 1

**Cenário 1: Envio ao protocolo da prefeitura**
**Dado que** um processo é protocolado no Viabiliza
**Quando** se espera o registro no protocolo municipal
**Então** o sistema ainda não envia este serviço

**Cenário 2: Comprovante interno**
**Dado que** o cidadão recebe o comprovante ou consulta o protocolo no portal
**Quando** essa consulta ocorre
**Então** o número é o do próprio Viabiliza e não conta como integração com o protocolo da prefeitura

**Cenário 3: Protocolo vindo da Junta**
**Dado que** a Junta enviou o protocolo dela
**Quando** o recebimento é gravado
**Então** isso não abre chamada a um protocolo municipal

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
