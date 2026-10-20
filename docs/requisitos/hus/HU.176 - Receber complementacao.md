# HU.176 – RECEBER COMPLEMENTAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O requerente responde o convite aberto no portal, na própria solicitação. A tela lista só os convites abertos. A resposta, de até 5000 caracteres, grava o texto, a data e a situação respondida, e devolve o processo à análise com o motivo Convite respondido pelo requerente. Outra pessoa não responde. Convite de outro processo não é encontrado.

Convite já respondido, expirado ou cancelado não aceita nova resposta. Processo fora de pendência também não. Nada é gravado e o portal mostra o erro. Não há tela da gestão para o analista colar a resposta no lugar do requerente. O analista só cancela o convite, com parecer, sem gravar texto do requerente.

O sistema ainda não oferece envio de documento como complementação nesta resposta, nem uma fila própria de complementações recebidas além da volta do processo à análise e do aviso ao analista atribuído.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Receber complementação](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** o requerente **quero** responder o convite no portal**, de modo que** a análise seja reaberta com o texto que enviei

## História de Usuário: Receber complementação

**Como** requerente da solicitação **quero** enviar a complementação do convite aberto**, de modo que** o processo volte à análise e convite encerrado não aceite outra resposta

## Pré-requisitos

- Requerente da solicitação, ou quem ele representa
- Convite aberto e processo em pendência
- Texto da resposta, até 5000 caracteres

## Dependências

**Passadas**

- HU.087 — Solicitar pendência

**Futuras**

- HU.089 — Reabrir análise, que avisa o analista atribuído

---

## Interface de Usuário

### Tela 1 — Receber complementação

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-solicitacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Resposta aceita**
**Dado que** o requerente autorizado responde um convite aberto
**Quando** envia o texto
**Então** o processo volta a em análise, com a resposta e a data gravadas

**Cenário 2: Sem acesso**
**Dado que** o usuário não tem acesso à solicitação
**Quando** tenta responder
**Então** o sistema recusa

**Cenário 3: Convite de outro processo**
**Dado que** o convite não pertence à solicitação
**Quando** a resposta é enviada
**Então** o convite não é encontrado

**Cenário 4: Convite encerrado**
**Dado que** a pendência já foi respondida, expirou ou foi cancelada, ou o processo não está em pendência
**Quando** tenta responder
**Então** nada é alterado e o erro é exibido

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
