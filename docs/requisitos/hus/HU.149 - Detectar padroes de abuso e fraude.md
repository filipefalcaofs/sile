# HU.149 – DETECTAR PADRÕES DE ABUSO E FRAUDE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema percorre as solicitações reais de uma janela de dias e gera alertas de padrão suspeito. Os padrões são: volume alto do mesmo CNPJ, volume alto do mesmo usuário que criou as solicitações, o mesmo desenho de imóvel em endereços distintos, a mesma inscrição imobiliária com atividades principais diferentes e requerente que só responde de um jeito que não aciona análise. Resposta de condicionante ausente não conta como esse último padrão. Com o recurso desligado, a varredura não grava alerta. Alerta já aberto para o mesmo padrão e o mesmo fato não é duplicado. Gravidade a partir do limiar configurado, quando há processo vinculado, encaminha o caso à malha fina. A situação do processo não muda. Na tela, o gestor filtra por padrão, gravidade, situação e período, vê a taxa de confirmação sobre todos os alertas gerados e confirma ou descarta com justificativa. Confirmar ou descartar altera só o alerta: não indefere, não cassa e não mexe na malha fina já criada. Sem alertas, a taxa de confirmação não é exibida. A lista mostra só padrões que já geraram alerta.

---

## Sumário

- [Épico: EP12 — Auditoria e Compliance](#épico-ep12--auditoria-e-compliance)
- [História de Usuário: Detectar padrões de abuso e fraude](#história-de-usuário-detectar-padrões-de-abuso-e-fraude)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Alertas de abuso](#tela-1--alertas-de-abuso)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP12 — Auditoria e Compliance

**Como** gestor da SEDUR **quero** ver padrões repetidos nas solicitações**, de modo que** a malha fina receba atenção onde o padrão se repetiu, sem indeferimento automático.

## História de Usuário: Detectar padrões de abuso e fraude

**Como** gestor autorizado a tratar alertas de abuso **quero** confirmar ou descartar o alerta com justificativa**, de modo que** o sinal fique registrado sem punir o processo.

## Pré-requisitos

- Recurso de detecção de abuso ligado; desligado, a varredura não grava nada
- Gestor autorizado a gerenciar os alertas de abuso
- Solicitações reais dentro da janela de dias configurada

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- A varredura de deferimentos do fluxo expresso é outra história, a auditoria preditiva

---

## Interface de Usuário

### Tela 1 — Alertas de abuso

**Caminho no menu:** Auditoria > Alertas de abuso

*[Print da tela](screenshots/gestao-abuso.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Recurso desligado**
**Dado que** a detecção de abuso está desligada
**Quando** a varredura é executada
**Então** nenhum alerta é criado

**Cenário 2: Padrão acima do limite**
**Dado que** um padrão passa do limite configurado
**Quando** a varredura encontra esse fato
**Então** nasce um alerta aberto, e repetir a varredura não duplica o alerta já aberto

**Cenário 3: Gravidade que vai à malha fina**
**Dado que** o alerta atinge a gravidade configurada para malha fina e há processo vinculado
**Quando** a varredura grava o alerta
**Então** o caso é encaminhado à malha fina e a situação do processo permanece a mesma

**Cenário 4: Confirmar ou descartar**
**Dado que** existe um alerta aberto
**Quando** o gestor confirma ou descarta com justificativa de até 2000 caracteres
**Então** ficam gravados a situação do alerta, o responsável, a data e a justificativa, e a situação do processo não muda

**Cenário 5: Sem alertas**
**Dado que** não há alerta gerado
**Quando** o gestor abre a tela
**Então** a taxa de confirmação não é apresentada

**Cenário 6: Justificativa ausente**
**Dado que** o gestor tenta decidir um alerta sem justificativa
**Quando** confirma ou descarta
**Então** a decisão não é gravada

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
