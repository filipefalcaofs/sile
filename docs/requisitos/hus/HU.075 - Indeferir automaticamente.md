# HU.075 – INDEFERIR AUTOMATICAMENTE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

No fluxo expresso, o sistema indefere quando o local não permite a atividade, quando uma regra territorial manda indeferir ou quando uma pergunta indispensável ao enquadramento chegou sem resposta. A decisão é única, com fundamentação, e não recebe número de TVL. O motivo do indeferimento pelo local é a justificativa do veredito, não um texto genérico.

O veto do local indefere mesmo quando há alto risco. A análise só recebe o que ainda pode ser deferido. Alto risco sem veto do local, inelegibilidade, pendência, gatilho de sede e regra territorial de análise não indeferem: vão para análise. O sistema ainda não oferece indeferimento automático só por alto risco quando o local ainda permite a atividade, nem indeferimento por DAM não pago.

Pergunta sem resposta indefere quando o consolidado ainda não é não permitido e essa regra está ligada, o que é o padrão. O texto cita o número, o enunciado e o CNAE da pergunta. Se o local já é não permitido, vale o indeferimento locacional. Com a regra desligada, a pergunta sem resposta não usa esse atalho. O indeferimento por prazo de convite é outro passo e não passa por aqui. Indeferimento do fluxo expresso não é enviado à SEFAZ; o registro fica como não aplicável.

---

## Sumário

- [Épico: EP09 — Fluxo Expresso](#épico)
- [História de Usuário: Indeferir automaticamente](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP09 — Fluxo Expresso

**Como** a SEDUR **quero** indeferir no fluxo expresso o uso não permitido no local**, de modo que** o processo encerre com fundamentação, sem análise humana e sem número de TVL

## História de Usuário: Indeferir automaticamente

**Como** analista **quero** que o sistema indefira não permitido, regra territorial de indeferimento e pergunta indispensável sem resposta**, de modo que** alto risco sem veto do local continue indo para análise

## Pré-requisitos

- Processo ainda decidível pelo fluxo expresso
- Veredito não permitido, regra territorial de indeferir, ou pergunta necessária sem resposta com a regra ligada

## Dependências

**Passadas**

- HU.073 — Identificar elegibilidade para fluxo expresso

**Futuras**

- HU.076 — Emitir resultado expresso
- HU.077 — Notificar resultado ao cidadão

---

## Interface de Usuário

### Tela 1 — Indeferir automaticamente

**Caminho no menu:** Relatórios > Resultados do fluxo expresso

*[Print da tela](screenshots/gestao-expresso.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Local não permitido**
**Dado que** o consolidado é não permitido, depois das regras territoriais que não indeferiram antes
**Quando** o sistema emite a decisão
**Então** a solicitação fica indeferida, a decisão registra o motivo e não há número de TVL

**Cenário 2: Regra territorial de indeferir**
**Dado que** a regra territorial manda indeferir
**Quando** o sistema decide
**Então** a decisão usa o motivo da regra e o consolidado não permitido, sem TVL

**Cenário 3: Pergunta sem resposta**
**Dado que** há pergunta necessária sem resposta, o consolidado não é não permitido e a regra está ligada
**Quando** o sistema decide
**Então** indefere citando número, enunciado e CNAE

**Cenário 4: Alto risco sem veto do local**
**Dado que** há alto risco e o local ainda permite a atividade
**Quando** o sistema decide
**Então** não indefere e encaminha à análise

**Cenário 5: Envio à SEFAZ**
**Dado que** o resultado é indeferimento
**Quando** o resultado é comunicado
**Então** o envio à SEFAZ não ocorre e fica registrado como não aplicável

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
