# HU.066 – INDEFERIR SEM ATUAÇÃO POR PRAZO BAP
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

A rotina indefere a solicitação que permanece aguardando atuação na Junta depois do prazo. O prazo considerado é de 48 horas, ou o prazo definido para essa espera. Não há analista: o responsável da decisão fica em branco. Não há tela. A rotina passa de hora em hora e não roda duas vezes ao mesmo tempo.

Só entra quem está aguardando a Junta e tem vencimento já passado. Sem data de vencimento e sem data de vínculo, o processo não entra na leva. Outra situação não é alterada e não recebe decisão por este caminho. A decisão é do fluxo expresso, indeferida, com o motivo de falta de atuação, sem documento de viabilidade, sem detalhe por atividade, sem versão de regras e sem fundamentação de localização. O caso não é tratado como veredito de local: o registro é a falta de atuação na Junta. O processo passa a indeferido, a trilha guarda a decisão e o resultado é emitido.

O indeferimento não é enviado à SEFAZ. A varredura seguinte não repete quem já foi indeferido. Se não há processo aguardando com prazo vencido, a rotina informa isso e não inventa indeferimento.

Hoje nenhum processo entra na espera da Junta, porque o sistema ainda não consulta este serviço. Sem o vínculo, o processo não é colocado em aguardando atuação. Na operação atual, a rotina horária não indefere ninguém.

---

## Sumário

- [Épico: EP09 — Fluxo Expresso](#épico)
- [História de Usuário: Indeferir sem atuação por prazo BAP](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP09 — Fluxo Expresso

**Como** gestor **quero** encerrar a espera na Junta quando o prazo vence sem atuação**, de modo que** a decisão fique registrada e nenhum indeferimento seja inventado com a fila vazia

## História de Usuário: Indeferir sem atuação por prazo BAP

**Como** gestor **quero** que só o processo já em espera, com prazo vencido, seja indeferido**, de modo que** a operação atual não indefira ninguém enquanto o vínculo da Junta não existir

## Pré-requisitos

- Processo já colocado em espera da Junta, com vencimento calculável e vencido
- Na operação atual esse estado não é alcançado, porque o sistema ainda não consulta o protocolo BAP

## Dependências

**Passadas**

- Vínculo do protocolo BAP, que hoje não é obtido

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Indeferimento por prazo na Junta

**Caminho no menu:** Não há tela. A rotina verifica de hora em hora os processos em espera da Junta.

*[Print da tela](screenshots/gestao-expresso.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Prazo vencido**
**Dado que** a solicitação já está aguardando a Junta e o vencimento passou
**Quando** a rotina roda
**Então** a decisão fica indeferida por falta de atuação, sem documento de viabilidade, a situação vai a indeferida e o resultado é emitido

**Cenário 2: Outra situação**
**Dado que** a solicitação está protocolada, em análise ou já indeferida
**Quando** a rotina passa
**Então** ela não é alterada

**Cenário 3: Ninguém em espera**
**Dado que** não há solicitação aguardando a Junta
**Quando** a rotina roda
**Então** ela informa que não há processo vencido e não inventa indeferimento

**Cenário 4: Serviço da Junta indisponível**
**Dado que** o vínculo do protocolo BAP é procurado
**Quando** a operação atual tenta colocar o processo em espera
**Então** o sistema ainda não consulta este serviço, nenhum processo entra nessa espera e a rotina não indefere ninguém

**Cenário 5: SEFAZ**
**Dado que** este indeferimento foi registrado
**Quando** o resultado é emitido
**Então** o indeferimento não é enviado à SEFAZ

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
