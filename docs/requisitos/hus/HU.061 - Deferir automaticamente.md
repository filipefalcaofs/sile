# HU.061 – DEFERIR AUTOMATICAMENTE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

No fluxo expresso, o sistema defere o processo elegível cujo veredito consolidado é permitido ou permitido com condições. Não há situação intermediária de deferido com condições: os dois viram deferimento, e o consolidado guarda se houve condição. Todo deferimento deste caminho recebe número de TVL. O padrão do prefixo é TVL, a sequência não reinicia por ano e o início configurável é 3000000. Indeferimento não recebe número.

A decisão é única por solicitação. Uma segunda tentativa não cria outra decisão nem avisa o resultado de novo. O motivo da decisão fica em branco neste deferimento. Se a inscrição é abrigada de escritório virtual, a decisão referencia o TVL da sede. Se o processo estava na análise sem dono, a fila de análise é encerrada.

Exclusão só de CNAEs, sem o CNAE que caracteriza a sede, defere sem novo enquadramento, com a fundamentação da exclusão e número de TVL. Exclusão desse CNAE pelo titular da sede ativa, com confirmação verdadeira de perda da condição, defere do mesmo modo e, depois, desvincula a inscrição. Sem essa confirmação, não defere: encaminha à análise. Outra empresa na mesma inscrição não defere a exclusão da sede alheia.

Veredito pendente não é deferido. Alto risco sem ramo da planilha marcado como fluxo expresso não chega ao deferimento. O sistema ainda não oferece, neste passo, esperar DAM pago nem entregar o PDF do TVL ao requerente. O PDF sob demanda é outro passo, da análise.

---

## Sumário

- [Épico: EP09 — Fluxo Expresso](#épico)
- [História de Usuário: Deferir automaticamente](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP09 — Fluxo Expresso

**Como** a SEDUR **quero** deferir no fluxo expresso os casos que a regra autoriza**, de modo que** o processo encerre sem análise humana e com número de TVL

## História de Usuário: Deferir automaticamente

**Como** analista **quero** que o sistema defira permitido e permitido com condições**, de modo que** a decisão fique registrada e eu não precise de um botão de deferimento expresso

## Pré-requisitos

- Processo elegível, protocolado ou em análise ainda não assumida e sem decisão
- Veredito permitido ou permitido com condições, ou exclusão de atividade que a regra manda deferir
- Confirmação verdadeira de perda da condição de sede, quando a exclusão for do CNAE da sede pelo titular

## Dependências

**Passadas**

- HU.060 — Identificar elegibilidade para fluxo expresso

**Futuras**

- HU.063 — Emitir resultado expresso
- HU.064 — Notificar resultado ao cidadão

---

## Interface de Usuário

### Tela 1 — Deferir automaticamente

**Caminho no menu:** Relatórios > Resultados do fluxo expresso

*[Print da tela](screenshots/gestao-expresso.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Deferimento com TVL**
**Dado que** o processo é elegível e o consolidado é permitido ou permitido com condições
**Quando** o sistema emite a decisão
**Então** a decisão fica deferida, nasce número de TVL, a solicitação fica deferida e o resultado é comunicado depois da gravação

**Cenário 2: Exclusão de atividades**
**Dado que** só há CNAEs marcados para excluir, sem o CNAE que caracteriza a sede
**Quando** o sistema decide
**Então** defere sem passar pelo enquadramento e ainda gera TVL

**Cenário 3: Exclusão da sede confirmada**
**Dado que** o titular exclui o CNAE da sede, com confirmação verdadeira e sede ativa
**Quando** o sistema decide
**Então** defere e desvincula a inscrição depois da gravação

**Cenário 4: Exclusão da sede sem confirmação**
**Dado que** a mesma exclusão não tem confirmação verdadeira
**Quando** o sistema decide
**Então** não defere e encaminha à análise

**Cenário 5: Caso que não defere**
**Dado que** o consolidado está pendente ou há CNAE fora do fluxo expresso sem veto do local
**Quando** o sistema decide
**Então** não há deferimento

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
