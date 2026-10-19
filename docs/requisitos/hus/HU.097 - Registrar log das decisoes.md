# HU.097 – REGISTRAR LOG DAS DECISÕES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Cada decisão de viabilidade, seja do fluxo expresso ou da análise técnica, fica registrada com o desfecho, a versão das regras usada naquela ficha e o responsável. Não há tela só para “registrar o log”: o registro nasce no momento da decisão.

No fluxo expresso, o registro ocorre sem analista. Na análise técnica, o analista autenticado no console é quem fica associado à decisão. O indeferimento por falta de atuação na Junta e o indeferimento por prazo de convite também geram esse registro. Se a decisão do fluxo expresso falha, o que fica registrado é a falha, e não um desfecho inventado.

A sede de escritório virtual só entra no registro técnico quando o desfecho é deferido, a ficha marcou sede e o processo tem a atividade que exige esse tratamento. Na renovação deferida, ficam o tipo e a validade do documento de viabilidade; um número novo de produto não é gerado na renovação. A versão das regras é a que estava na ficha no momento da decisão, e não uma leitura posterior do motor.

---

## Sumário

- [Épico: EP12 — Auditoria e Compliance](#épico)
- [História de Usuário: Registrar log das decisões](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP12 — Auditoria e Compliance

**Como** auditor **quero** que toda decisão de viabilidade fique registrada**, de modo que** o desfecho, a versão das regras e o responsável possam ser conferidos depois

## História de Usuário: Registrar log das decisões

**Como** auditor **quero** ver gravados o deferimento e o indeferimento, automáticos ou da análise**, de modo que** a fundamentação não se perca depois da decisão

## Pré-requisitos

- A decisão técnica exige analista autenticado e ficha de análise já existente
- A decisão do fluxo expresso não depende de analista
- A consulta posterior dessa trilha é de quem acompanha a auditoria

## Dependências

**Passadas**

- Decisão do fluxo expresso ou conclusão da análise técnica

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Registro da decisão

**Caminho no menu:** Não há tela própria. O registro aparece na trilha de auditoria e no processo decidido.

*[Print da tela](screenshots/gestao-auditoria.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Decisão técnica concluída**
**Dado que** o analista concluiu o deferimento ou o indeferimento
**Quando** a decisão é gravada
**Então** a trilha guarda o desfecho, o protocolo, a versão das regras da ficha e o analista

**Cenário 2: Decisão do fluxo expresso**
**Dado que** o fluxo expresso deferiu ou indeferiu
**Quando** a decisão é gravada
**Então** a trilha guarda o mesmo tipo de registro, sem atribuir um analista que não atuou

**Cenário 3: Falha ao decidir no fluxo expresso**
**Dado que** o fluxo expresso não conseguiu concluir a decisão
**Quando** a falha é registrada
**Então** a trilha guarda a falha e não inventa deferimento nem indeferimento

**Cenário 4: Sede de escritório virtual**
**Dado que** o desfecho técnico é deferido, a ficha marcou sede e o processo tem a atividade que exige esse tratamento
**Quando** a decisão é gravada
**Então** a sede de escritório virtual entra no registro

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
