# HU.086 – DEFERIR SOLICITAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem analisa e também conclui a análise defere o processo em análise, a partir da ficha finalizada. A decisão é humana, com o autor e o fluxo de análise técnica. Fora de renovação, nasce número de TVL. A situação fica deferida. Uma segunda conclusão não cria outra decisão.

A ficha precisa ter ao menos um CNAE. Atividade marcada como indeferida impede o deferimento e a mensagem lista essas atividades. Atividade sem marcação não impede: o resultado é deferido quando nenhuma atividade está marcada como indeferida. Com condicionante na ficha, o consolidado é permitido com condições; sem condicionante, permitido. O analista pode deferir caso que o enquadramento deixou pendente, porque a decisão nasce da ficha.

Renovação deferida exige tipo de TVL, provisória ou definitiva, e nova data de validade. Se as condicionantes não foram atendidas, o resultado vira indeferido. Sede de escritório virtual confirmada na ficha, com o CNAE que a caracteriza, grava a condicionante, trava a inscrição e marca o processo como escritório virtual. Processo já deferido não é indeferido por esta conclusão: o caminho é desativado, cassado ou revogado.

No cadastro padrão, concluem Coordenador, Subcoordenador, Chefe de setor e Analista Servidor. Analista Terceirizado não defere. Apoio não analisa. Administrador, no cadastro padrão, não defere. O sistema ainda não oferece transmissão concluída à Junta ou à SEFAZ a partir deste deferimento: o resultado é comunicado, mas o canal indisponível fica registrado como bloqueado. A entrega do PDF ao requerente é outro passo.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Deferir solicitação](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** quem conclui a análise **quero** deferir o processo em análise**, de modo que** a análise encerre como deferida, com número de TVL quando não for renovação

## História de Usuário: Deferir solicitação

**Como** analista que analisa e conclui **quero** deferir a partir da ficha finalizada**, de modo que** atividade marcada como indeferida impeça o deferimento e a decisão não se duplique

## Pré-requisitos

- Processo em análise
- Ficha finalizada, com ao menos um CNAE
- Nenhuma atividade marcada como indeferida
- Quem analisa e quem conclui a análise

## Dependências

**Passadas**

- HU.085 — Emitir parecer

**Futuras**

- HU.088 — Aplicar condicionantes, no consolidado do deferimento
- HU.077 — Notificar resultado ao cidadão, quando o resultado é comunicado

---

## Interface de Usuário

### Tela 1 — Deferir solicitação

**Caminho no menu:** Operação > Caixa de entrada

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Sem concluir a análise**
**Dado que** a pessoa não conclui a análise
**Quando** tenta deferir
**Então** o sistema recusa

**Cenário 2: Deferimento gravado**
**Dado que** a ficha está finalizada, o processo está em análise e nenhuma atividade está marcada como indeferida
**Quando** defere
**Então** a situação fica deferida, a decisão registra o autor e o fluxo de análise técnica, e nasce número de TVL quando não é renovação

**Cenário 3: Atividade indeferida na ficha**
**Dado que** alguma atividade está marcada como indeferida
**Quando** tenta deferir
**Então** o deferimento é recusado e a decisão não é gravada

**Cenário 4: Segunda conclusão**
**Dado que** o processo já tem decisão
**Quando** a conclusão é repetida
**Então** não nasce outra decisão

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
