# HU.185 – AUDITORIA PREDITIVA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

A varredura pontua deferimentos automáticos do fluxo expresso e submete anomalias à revisão humana, sem alterar o desfecho do processo. Não há provedor de inteligência artificial nesta pontuação. Os sinais são volume de deferimentos do mesmo CNPJ, inscrição imobiliária repetida e requerente que prosseguiu apesar de alerta. Pontuação acima do limiar configurado, com padrão de 70, cria anomalia aberta. A gravidade alta usa o corte de 80 e a média usa o corte de 60. Anomalia aberta do mesmo processo não duplica. Gravidade alta pode encaminhar à malha fina. A situação do processo não muda: ele continua deferido. Com o recurso desligado, a varredura não grava anomalia. A tela informa se o recurso está ligado, lista por pontuação maior primeiro, filtra gravidade, situação e período, mostra a taxa de confirmação e permite confirmar ou descartar com justificativa de até 2000 caracteres. Só a anomalia é atualizada. Confirmar ou descartar não indefere, não cassa e não mexe na malha fina já criada. Sem anomalias, a taxa fica sem valor. A tela mostra se a anomalia já foi encaminhada à malha fina. A consulta e a decisão ficam registradas. A janela tem padrão de 30 dias.

---

## Sumário

- [Épico: EP12 — Auditoria e Compliance](#épico-ep12--auditoria-e-compliance)
- [História de Usuário: Auditoria preditiva](#história-de-usuário-auditoria-preditiva)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Auditoria preditiva](#tela-1--auditoria-preditiva)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP12 — Auditoria e Compliance

**Como** gestor da SEDUR **quero** ver sinais nos deferimentos do fluxo expresso**, de modo que** a pontuação antecipe a revisão humana sem decidir o processo.

## História de Usuário: Auditoria preditiva

**Como** gestor autorizado a tratar alertas de abuso **quero** confirmar ou descartar a anomalia com justificativa**, de modo que** o deferimento permaneça e só o sinal mude.

## Pré-requisitos

- Recurso de auditoria preditiva ligado; desligado, a varredura não grava anomalia
- Gestor autorizado a gerenciar os alertas de abuso
- Deferimentos automáticos do fluxo expresso na janela de dias

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Auditoria preditiva

**Caminho no menu:** Auditoria > Auditoria preditiva

*[Print da tela](screenshots/gestao-preditiva.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Recurso desligado**
**Dado que** a auditoria preditiva está desligada
**Quando** a varredura é executada
**Então** nenhuma anomalia é criada e a tela informa que o recurso está desligado

**Cenário 2: Deferimento acima do limiar**
**Dado que** um deferimento expresso fica acima do limiar
**Quando** a varredura pontua o processo
**Então** nasce anomalia aberta, o processo continua deferido e repetir a varredura não duplica a anomalia aberta

**Cenário 3: Confirmar**
**Dado que** existe anomalia aberta
**Quando** o gestor confirma com justificativa
**Então** ficam gravados justificativa, responsável e data na anomalia, e a situação do processo não muda

**Cenário 4: Ordem da lista**
**Dado que** há mais de uma anomalia
**Quando** o gestor abre a lista
**Então** a maior pontuação aparece primeiro

**Cenário 5: Sem anomalias**
**Dado que** não há anomalia
**Quando** a tela abre
**Então** a taxa de confirmação fica sem valor

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
