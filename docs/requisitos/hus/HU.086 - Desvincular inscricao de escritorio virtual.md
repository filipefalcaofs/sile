# HU.086 – DESVINCULAR INSCRIÇÃO DE ESCRITÓRIO VIRTUAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 20/10/2026 | Criação da HU para função já existente no sistema e contada, que nenhuma HU descrevia | 2.2 | Sudoeste Informática |

---

## Visão Geral

O gestor desvincula a inscrição imobiliária da sede de um escritório virtual, informando o motivo. A inscrição volta a ficar livre, os abrigados que dependiam dela são avisados e a comunicação devida à SEFAZ fica registrada para envio. Hoje a ação existe como rota da gestão, sem botão em tela.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Desvincular inscrição de escritório virtual](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** a SEDUR **quero** poder encerrar o vínculo da inscrição com a sede de escritório virtual**, de modo que** a inscrição possa ser usada por outra sede e os abrigados saibam que perderam o vínculo

## História de Usuário: Desvincular inscrição de escritório virtual

**Como** gestor com a permissão de emitir TVL **quero** desvincular a inscrição da sede de escritório virtual de um processo, informando o motivo**, de modo que** a inscrição fique livre e os abrigados sejam avisados

## Pré-requisitos

- Gestor autenticado no console, com a permissão `emitir-tvl`
- Processo de sede de escritório virtual deferido, com bloqueio de inscrição ativo
- Motivo da desvinculação

## Dependências

**Passadas**

- HU-079 — o deferimento da sede cria o bloqueio da inscrição
- HU-083 — a mesma permissão `emitir-tvl` autoriza a ação

**Futuras**

- HU-109 — envio à SEFAZ da comunicação registrada
- HU-181 — os abrigados recebem o aviso na central de notificações
- HU-137 — o relatório de escritório virtual deixa de mostrar o vínculo desfeito

---

## Interface de Usuário

Esta versão não tem tela para a ação: ela é a rota `POST /gestao/processos/{id}/desvincular-inscricao`, que devolve à página do processo a mensagem de resultado.

#### Comportamento Esperado

**Cenário 1: Desvinculação com motivo**
**Dado que** há um processo de sede com bloqueio ativo e um gestor com `emitir-tvl`
**Quando** o gestor informa o motivo e confirma a desvinculação
**Então** a inscrição fica livre, os abrigados são notificados e a mensagem informa quantos foram avisados

**Cenário 2: Motivo obrigatório**
**Dado que** o gestor não informa o motivo
**Quando** confirma a desvinculação
**Então** a ação é recusada com «Informe o motivo da desvinculação da inscrição.» e nada é alterado

**Cenário 3: Sem inscrição ativa**
**Dado que** há um processo cuja sede não tem bloqueio ativo
**Quando** o gestor tenta desvincular
**Então** o sistema responde «Não há inscrição de sede ativa para desvincular neste processo.»

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
