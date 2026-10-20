# HU.177 – RESPONDER PENDÊNCIA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O requerente envia a resposta do convite aberto no portal e a solicitação volta para análise. A resposta é obrigatória, com até 5000 caracteres. Convite de outro processo não é encontrado. Outra pessoa não responde. Resposta vazia é recusada. Fora do convite aberto, nada é gravado.

Todo dia, o sistema encerra o convite que venceu sem resposta. A pendência fica expirada e, se o processo ainda está em pendência, nasce decisão indeferida, sem autor humano, com o motivo do convite expirado. Não reavalia o enquadramento. O analista atribuído, ou quem abriu o convite, é avisado por e-mail e pelo aviso interno. Rodar de novo não gera segunda decisão para o mesmo convite. Prazo em branco, convite já respondido ou cancelado não entra.

O sistema ainda não oferece prorrogação do prazo pelo analista nem resposta com anexo. O analista cancela o convite por outro caminho, com parecer, sem texto do requerente.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico)
- [História de Usuário: Responder pendência](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** o requerente **quero** responder o convite no prazo**, de modo que** a solicitação volte à análise e o silêncio até o vencimento indefira o processo

## História de Usuário: Responder pendência

**Como** requerente **quero** enviar o texto da resposta**, de modo que** o convite vencido sem resposta encerre como indeferido, com o motivo do prazo

## Pré-requisitos

- Requerente da solicitação, ou quem ele representa
- Convite aberto e processo em pendência, para responder
- Prazo do convite já vencido, para o indeferimento automático

## Dependências

**Passadas**

- HU.087 — Solicitar pendência
- HU.088 — Notificar pendência

**Futuras**

- HU.089 — Reabrir análise, no aviso ao analista quando há resposta

---

## Interface de Usuário

### Tela 1 — Responder pendência

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-solicitacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Resposta no prazo**
**Dado que** o requerente autorizado responde um convite aberto
**Quando** envia o texto
**Então** a pendência fica respondida e o processo volta a em análise

**Cenário 2: Resposta inválida**
**Dado que** a resposta está vazia, ou o convite não está aberto, ou o processo não está em pendência
**Quando** envia
**Então** o sistema recusa e nada é gravado

**Cenário 3: Convite vencido**
**Dado que** o convite venceu sem resposta e o processo ainda está em pendência
**Quando** o prazo é aplicado
**Então** a pendência fica expirada, o processo fica indeferido, com a justificativa do convite expirado e sem autor humano, e o analista é avisado

**Cenário 4: Prazo aplicado de novo**
**Dado que** o convite já foi expirado
**Quando** a verificação diária roda outra vez
**Então** não nasce segunda decisão para o mesmo convite

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
