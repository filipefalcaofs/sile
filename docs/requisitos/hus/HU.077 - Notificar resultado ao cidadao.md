# HU.077 – NOTIFICAR RESULTADO AO CIDADÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quando a resposta automática defere ou indefere, o requerente é avisado por e-mail e pelo aviso interno do portal, se o aviso estiver ligado. O e-mail informa o protocolo e o resultado e diz que o documento oficial segue pelos canais da Junta e, no deferimento, também da SEFAZ. Não anexa TVL nem documento de indeferimento.

Sem requerente ou sem e-mail, nada é enviado e a ausência de destinatário fica registrada. Com o aviso desligado, a decisão permanece e o envio não ocorre, também com registro. Falha de destinatário não inventa envio. O aviso interno traz título, mensagem, protocolo, resultado e o caminho para a consulta da solicitação no portal.

O sistema ainda não oferece, neste aviso, anexo do TVL, envio do parecer à Junta nem aviso de que o processo foi só encaminhado à análise. A ida à análise não dispara este aviso.

---

## Sumário

- [Épico: EP09 — Fluxo Expresso](#épico)
- [História de Usuário: Notificar resultado ao cidadão](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP09 — Fluxo Expresso

**Como** o requerente **quero** ser avisado do deferimento ou do indeferimento da resposta automática**, de modo que** eu saiba que a decisão terminou e onde acompanhar o resultado

## História de Usuário: Notificar resultado ao cidadão

**Como** requerente com e-mail **quero** receber o aviso do resultado**, de modo que** o documento oficial não venha anexado e a decisão continue registrada mesmo se o aviso estiver desligado

## Pré-requisitos

- Decisão de deferimento ou indeferimento já gravada
- Requerente com e-mail
- Aviso de resultado da resposta automática ligado

## Dependências

**Passadas**

- HU.074 — Deferir automaticamente
- HU.075 — Indeferir automaticamente

**Futuras**

- HU.069 — Consultar protocolo, para onde o aviso interno aponta

---

## Interface de Usuário

### Tela 1 — Notificar resultado ao cidadão

**Caminho no menu:** Início > Notificações

*[Print da tela](screenshots/portal-notificacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Aviso enviado**
**Dado que** houve deferimento ou indeferimento da resposta automática, o requerente tem e-mail e o aviso está ligado
**Quando** o resultado é comunicado
**Então** o requerente recebe o protocolo e o resultado, e o e-mail não anexa documento

**Cenário 2: Aviso desligado**
**Dado que** o aviso de resultado está desligado
**Quando** a decisão ocorre
**Então** não há entrega, a decisão permanece e o desligamento fica registrado

**Cenário 3: Requerente sem e-mail**
**Dado que** o requerente não tem e-mail
**Quando** a decisão ocorre
**Então** não há entrega e a falta de destinatário fica registrada

**Cenário 4: Aviso interno**
**Dado que** o aviso interno é montado
**Quando** o requerente abre as notificações
**Então** o caminho leva à consulta da própria solicitação

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
