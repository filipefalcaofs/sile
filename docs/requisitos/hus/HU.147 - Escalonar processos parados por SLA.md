# HU.147 – ESCALONAR PROCESSOS PARADOS POR SLA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quando um processo está em análise e o prazo da etapa entra em alerta ou vence, o sistema avisa as pessoas certas. No alerta, o aviso vai ao analista responsável. No prazo vencido, o aviso vai a quem está autorizado a recebê-lo, em qualquer setor, e não ao gestor do setor do processo. O aviso não muda a situação do processo, não troca o analista e não encerra o pedido. Processo dentro do prazo não gera aviso. Processo fora de análise, ou sem a data de início e a data de vencimento da etapa, fica de fora. Sem analista responsável, o alerta não tem destinatário. Se ninguém está autorizado a receber o vencido, ninguém é avisado. O mesmo aviso não se repete para a mesma pessoa, no mesmo processo, enquanto a etapa atual não recomeçar. Se o tratamento configurado para a faixa não for avisar o analista nem avisar quem recebe o vencido, nada é enviado e nenhuma outra ação ocorre. O aviso chega por e-mail e na caixa do sistema. E-mail que não pode ser entregue impede o registro da comunicação daquele destinatário.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico-ep11--pendências-e-comunicação)
- [História de Usuário: Escalonar processos parados por SLA](#história-de-usuário-escalonar-processos-parados-por-sla)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Aviso de prazo da análise](#tela-1--aviso-de-prazo-da-análise)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** gestor da SEDUR **quero** que o atraso da análise seja avisado a quem deve agir**, de modo que** o prazo vencido não passe em silêncio e o processo não seja decidido por esse aviso.

## História de Usuário: Escalonar processos parados por SLA

**Como** analista responsável ou pessoa autorizada a receber o aviso de prazo vencido **quero** ser avisado na etapa atual**, de modo que** eu veja o atraso sem que o processo seja redistribuído ou encerrado.

## Pré-requisitos

- Processo em análise, com data de início e data de vencimento do prazo da etapa
- Analista responsável, para o aviso de alerta
- Pessoas autorizadas a receber o aviso de prazo vencido; na configuração inicial, essa autorização está nas chefias
- O aviso só sai para alerta e para prazo vencido

## Dependências

**Passadas**

- O semáforo do prazo é o mesmo usado na fila de análise

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Aviso de prazo da análise

**Caminho no menu:** Não há tela para o usuário.

*[Print da tela](screenshots/gestao-sla.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Alerta com analista responsável**
**Dado que** o processo está em análise, na faixa de alerta, com analista responsável
**Quando** o aviso da etapa atual é gerado
**Então** o analista recebe o aviso e a situação do processo permanece a mesma

**Cenário 2: Prazo vencido**
**Dado que** o processo está em análise com o prazo vencido
**Quando** o aviso da etapa atual é gerado
**Então** cada pessoa autorizada a receber o escalonamento é avisada uma vez nessa etapa

**Cenário 3: Repetição na mesma etapa**
**Dado que** a pessoa já recebeu o aviso desta etapa
**Quando** a verificação do prazo ocorre de novo antes de a etapa recomeçar
**Então** o aviso não é duplicado

**Cenário 4: Tratamento que não é aviso**
**Dado que** o tratamento configurado para a faixa não é avisar o analista nem avisar quem recebe o vencido
**Quando** a verificação do prazo ocorre
**Então** ninguém é avisado, o processo não é redistribuído e não é encerrado

**Cenário 5: Prazo em dia**
**Dado que** o processo está em análise e o prazo ainda está em dia
**Quando** a verificação do prazo ocorre
**Então** nenhuma comunicação é gerada

**Cenário 6: Alerta sem responsável**
**Dado que** o processo está em alerta e não tem analista responsável
**Quando** a verificação do prazo ocorre
**Então** ninguém é avisado nessa faixa

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
