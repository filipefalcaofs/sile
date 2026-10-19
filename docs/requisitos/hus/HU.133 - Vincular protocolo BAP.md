# HU.133 – VINCULAR PROTOCOLO BAP
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não consulta o protocolo BAP da Junta. A busca de vínculo não devolve número e nenhum protocolo BAP é inventado. Não há tela para digitar um número no lugar da consulta. A ausência de vínculo não coloca o processo em espera e não fabrica protocolo. O indeferimento por prazo sem atuação na Junta é outra regra e não cria este vínculo. Receber a declaração da Junta grava o protocolo do envelope e também não consulta a base de BAP.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Vincular protocolo BAP](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** gestor **quero** associar o protocolo BAP da Junta ao processo quando a base existir**, de modo que** nenhum número seja inventado enquanto o serviço não responde

## História de Usuário: Vincular protocolo BAP

**Como** gestor **quero** que a falta do vínculo apareça como ausência**, de modo que** o processo não entre em espera por um protocolo fabricado

## Pré-requisitos

- Nenhum número local substitui a consulta, porque o serviço não está disponível

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Protocolo BAP

**Caminho no menu:** Não há tela para informar o protocolo BAP.

#### Comportamento Esperado Tela 1

**Cenário 1: Consultar o vínculo**
**Dado que** um processo precisa do protocolo BAP da Junta
**Quando** o vínculo é procurado
**Então** o sistema ainda não consulta este serviço e não devolve número

**Cenário 2: Número local**
**Dado que** não há retorno da Junta
**Quando** o processo segue
**Então** nenhum número BAP é gerado no lugar da consulta e o processo não entra em espera por esse retorno

**Cenário 3: Declaração já recebida**
**Dado que** a declaração da Junta foi gravada
**Quando** se confere o protocolo BAP
**Então** o protocolo do envelope não conta como esta consulta

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
