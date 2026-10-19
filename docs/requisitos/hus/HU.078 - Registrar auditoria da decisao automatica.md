# HU.078 – REGISTRAR AUDITORIA DA DECISÃO AUTOMÁTICA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

No momento em que a decisão do fluxo expresso é gravada, o sistema registra o resultado, o motivo, o que valeu para cada CNAE, a versão de regra representativa e a fundamentação. A conferência posterior lê o que foi gravado. Não depende de reprocessar a LOUOS ao abrir a tela. O sistema ainda não oferece registrar regra que o enquadramento não devolveu.

No deferimento e no indeferimento, o registro traz protocolo, resultado, consolidado, número de TVL quando houver e o resumo por CNAE. No indeferimento o TVL fica em branco. No encaminhamento à análise, o registro traz o motivo e não nasce decisão. A queda não inventa exceção de risco quando o enquadramento não trouxe uma. Na exclusão de atividade sem enquadramento, o texto gravado é o da própria regra de exclusão e não há versão de regra.

Se a avaliação falha de vez, fica registrada a falha e não surge decisão. Abrir a lista ou o detalhe em Resultados do fluxo expresso gera outro registro, de consulta, e não altera a decisão. A explicação do detalhe lê o traço já gravado.

---

## Sumário

- [Épico: EP09 — Fluxo Expresso](#épico)
- [História de Usuário: Registrar auditoria da decisão automática](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP09 — Fluxo Expresso

**Como** a SEDUR **quero** registrar a decisão automática no momento em que ela é gravada**, de modo que** a conferência posterior não dependa de recalcular o resultado

## História de Usuário: Registrar auditoria da decisão automática

**Como** servidor que consulta solicitações **quero** ver a fundamentação e o resultado já gravados**, de modo que** abrir a tela não altere a decisão nem invente regra

## Pré-requisitos

- Decisão do fluxo expresso, encaminhamento à análise ou falha definitiva da avaliação
- Quem consulta solicitações, para ler o detalhe

## Dependências

**Passadas**

- HU.074 — Deferir automaticamente
- HU.075 — Indeferir automaticamente
- HU.073 — Identificar elegibilidade para fluxo expresso, no encaminhamento

**Futuras**

- HU.076 — Emitir resultado expresso, que mostra o traço gravado

---

## Interface de Usuário

### Tela 1 — Registrar auditoria da decisão automática

**Caminho no menu:** Relatórios > Resultados do fluxo expresso

*[Print da tela](screenshots/gestao-expresso.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Deferimento ou indeferimento**
**Dado que** o sistema emite deferimento ou indeferimento
**Quando** a gravação conclui
**Então** existem a decisão e o registro com protocolo, resultado e o que valeu para cada CNAE, mesmo que um aviso posterior falhe

**Cenário 2: Encaminhamento à análise**
**Dado que** o processo vai para análise
**Quando** a gravação conclui
**Então** o registro traz o resultado de análise e o motivo, e não existe decisão

**Cenário 3: Queda sem exceção conhecida**
**Dado que** a queda não trouxe exceção de risco conhecida
**Quando** a queda é gravada
**Então** a exceção fica em branco em vez de um motivo inventado

**Cenário 4: Falha definitiva**
**Dado que** a avaliação falha de vez
**Quando** a falha é registrada
**Então** não surge decisão

**Cenário 5: Explicação no detalhe**
**Dado que** o servidor autorizado abre o detalhe
**Quando** a explicação é montada
**Então** ela lê o que já foi gravado e não recalcula o veredito

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
