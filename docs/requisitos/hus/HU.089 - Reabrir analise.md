# HU.089 – REABRIR ANÁLISE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

A análise reabre quando o requerente responde o convite ou quando o analista cancela o convite com parecer. Não há um botão chamado reabrir. O processo volta de em pendência para em análise. O responsável continua o mesmo: a resposta não redistribui o processo.

Na resposta, o motivo é Convite respondido pelo requerente. Depois da gravação, o analista atribuído recebe aviso interno. No padrão, esse aviso não vai por e-mail. Sem analista atribuído, a análise reabre e a falta de destinatário fica registrada. E-mail do analista vazio ou inválido impede também o aviso interno.

O cancelamento pelo analista também volta o processo à análise, com o parecer no motivo, e não usa o aviso da resposta. Convite expirado não reabre: o processo é indeferido. O sistema ainda não oferece reabrir análise já concluída, indeferida ou deferida, nem avisar o analista quando ele mesmo cancela o convite.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico)
- [História de Usuário: Reabrir análise](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** a SEDUR **quero** reabrir a análise ao receber a resposta do convite**, de modo que** o responsável continue o processo e seja avisado

## História de Usuário: Reabrir análise

**Como** analista **quero** cancelar o convite com parecer**, de modo que** a análise reabra sem esperar a resposta e sem o aviso da resposta do requerente

## Pré-requisitos

- Convite aberto e processo em pendência
- Resposta do requerente, ou parecer do analista no cancelamento, com até 2000 caracteres

## Dependências

**Passadas**

- HU.087 — Solicitar pendência
- HU.177 — Responder pendência

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Reabrir análise

**Caminho no menu:** Operação > Caixa de entrada

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Resposta do requerente**
**Dado que** a resposta é válida e há analista atribuído com e-mail utilizável
**Quando** o convite é respondido
**Então** o processo volta a em análise e o analista recebe o aviso interno de resposta

**Cenário 2: Sem analista atribuído**
**Dado que** não há analista atribuído
**Quando** o requerente responde
**Então** a análise reabre e a falta de destinatário fica registrada

**Cenário 3: Cancelamento pelo analista**
**Dado que** o analista cancela o convite com parecer
**Quando** confirma
**Então** a análise reabre e o aviso da resposta do requerente não é usado

**Cenário 4: Convite expirado**
**Dado que** a pendência expirou
**Quando** se espera a reabertura
**Então** o processo não volta para em análise por este caminho

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
