# HU.172 – SIMULAR VIABILIDADE ANTES DE PROTOCOLAR
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

O requerente, dono do rascunho, pede a simulação antes de protocolar. Ela não dispara sozinha ao terminar o imóvel e as atividades. O motor real roda sobre os dados já informados. Sem polígono, a simulação segue pela atividade, sem território. O resultado consolidado, o detalhe por atividade, as versões de regra e a data ficam na solicitação. A execução fica registrada. A mensagem informa que o resultado é orientativo e não impede o protocolo.

A simulação não muda a situação. Resultado pendente permanece pendente e não vira permitido nem não permitido. O veredito por atividade é o do motor. Sem data ou sem resultado gravado, não há simulação para exibir. Se o imóvel, a área ou as atividades mudam, a simulação anterior deixa de aparecer até uma nova execução.

Tendência não permitida não bloqueia o protocolo. Se o requerente confirma que quer protocolar mesmo assim, a ciência fica registrada. Se o resultado não é não permitido, essa confirmação não grava ciência. A decisão posterior do fluxo expresso calcula de novo e não reutiliza esta simulação. A simulação está disponível no rascunho; não há interruptor efetivo escondendo-a. Não gasta número de protocolo, não exige protocolo da Junta e não devolve sugestão textual do que revisar. Quem não pode editar o rascunho não simula.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Simular viabilidade antes de protocolar](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** requerente **quero** simular a viabilidade antes de protocolar**, de modo que** eu veja a tendência por atividade e decida se ajusto o pedido ou se protocolo mesmo assim

## História de Usuário: Simular viabilidade antes de protocolar

**Como** requerente **quero** um resultado orientativo no rascunho**, de modo que** a tendência desfavorável não me impeça de protocolar e não vire a decisão definitiva

## Pré-requisitos

- Rascunho do próprio requerente
- Dados já informados na solicitação

## Dependências

**Passadas**

- Imóvel, área e atividades informados no rascunho, no que o requerente já preencheu

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Simulação no rascunho

**Caminho no menu:** Serviços > Minhas solicitações, no rascunho

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Simular**
**Dado que** o requerente é dono do rascunho
**Quando** aciona a simulação
**Então** a solicitação guarda resultado, detalhe, versões e data, a execução fica registrada e a mensagem informa que o resultado não impede o protocolo

**Cenário 2: Tendência não permitida**
**Dado que** o resultado é não permitido
**Quando** o dono protocola confirmando que segue mesmo assim
**Então** o protocolo ocorre e a ciência fica registrada

**Cenário 3: Confirmação fora da tendência desfavorável**
**Dado que** o resultado não é não permitido
**Quando** o requerente envia a confirmação de seguir mesmo assim
**Então** o protocolo não grava essa ciência

**Cenário 4: Dados alterados**
**Dado que** o imóvel, a área ou as atividades mudam depois da simulação
**Quando** o rascunho é reaberto
**Então** a simulação anterior não é reexibida até nova execução

**Cenário 5: Quem não edita o rascunho**
**Dado que** o usuário não pode alterar aquele rascunho
**Quando** tenta simular
**Então** a simulação é negada

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
