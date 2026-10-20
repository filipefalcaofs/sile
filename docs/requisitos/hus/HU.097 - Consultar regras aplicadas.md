# HU.097 – CONSULTAR REGRAS APLICADAS
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

Quem abre o processo na gestão vê os passos de regra que fundamentaram o desfecho, lidos do que foi gravado na decisão. O motor não roda de novo: zona, risco e quadro não são recalculados na consulta. Não há tela separada de “regras aplicadas”. A mesma leitura aparece na ficha de resultado do fluxo expresso e no registro de análise.

Com o caminho da decisão gravado, a ordem é a registrada por atividade: entrada, risco, enquadramento da lei urbanística, consolidação e desfecho, com a versão de regra daquela decisão. Sem esse caminho, a explicação fica compacta, a partir do que foi gravado por atividade e da fundamentação, e cada passo que não foi registrado aparece como não registrado. Decisão antiga não recebe quadro, artigo ou zona inventados. Os títulos desses passos usam o texto decisório cadastrado; se não houver texto cadastrado, vale o texto de fábrica.

Sem decisão, não há explicação.

---

## Sumário

- [Épico: EP12 — Auditoria e Compliance](#épico)
- [História de Usuário: Consultar regras aplicadas](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP12 — Auditoria e Compliance

**Como** analista **quero** ver as regras que fundamentaram a decisão**, de modo que** eu entenda o desfecho sem o motor calcular de novo

## História de Usuário: Consultar regras aplicadas

**Como** analista ou auditor **quero** ler os passos gravados na decisão do processo**, de modo que** a fundamentação consultada seja a da época, e não a regra de hoje

## Pré-requisitos

- Processo com decisão já gravada
- Acesso à ficha do processo na gestão

## Dependências

**Passadas**

- Decisão de viabilidade já registrada

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Regras aplicadas no processo

**Caminho no menu:** Operação > Consulta de processos, na ficha do processo decidido

*[Print da tela](screenshots/gestao-auditoria.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Decisão com o caminho gravado**
**Dado que** a decisão guardou os passos por atividade
**Quando** o analista abre o processo
**Então** a tela mostra entrada, risco, enquadramento, consolidação e desfecho, na ordem gravada, com a versão de regra daquela decisão

**Cenário 2: Decisão antiga, sem o caminho gravado**
**Dado que** a decisão é anterior a esse registro detalhado
**Quando** o analista abre o processo
**Então** a explicação fica compacta e cada passo ausente aparece como não registrado, sem quadro, artigo ou zona inventados

**Cenário 3: Processo sem decisão**
**Dado que** o processo ainda não tem decisão
**Quando** alguém abre a ficha
**Então** não há explicação de regras aplicadas

**Cenário 4: A consulta não recalcula**
**Dado que** a regra vigente hoje é diferente da que decidiu o processo
**Quando** a explicação é exibida
**Então** o motor não roda de novo e o passado da decisão permanece o que foi gravado

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
