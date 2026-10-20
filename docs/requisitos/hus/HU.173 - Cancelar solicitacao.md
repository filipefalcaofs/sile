# HU.173 – CANCELAR SOLICITAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O requerente cancela a própria solicitação informando o motivo, enquanto ela está em rascunho ou protocolada. O motivo é obrigatório, com até 1000 caracteres, e fica na mudança de situação. A situação passa a cancelada, com a data, o motivo e quem cancelou. O texto público é Cancelada a pedido do requerente.

A lista só oferece cancelar quando a situação está entre as que a SEDUR deixou canceláveis. O padrão é rascunho e protocolada. Fora disso, nada muda e o bloqueio fica registrado. Situação deferida ou indeferida não é cancelável por este caminho, mesmo que a lista administrável seja ampliada: o cancelamento do requerente continua restrito a rascunho e protocolada.

O sistema ainda não oferece cancelamento pelo analista neste passo, nem estorno de DAM, baixa de guia ou comunicação à Junta.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Cancelar solicitação](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente **quero** cancelar a minha solicitação com motivo**, de modo que** um pedido que não deve seguir seja interrompido

## História de Usuário: Cancelar solicitação

**Como** requerente dono da solicitação **quero** cancelar rascunho ou protocolada**, de modo que** o motivo fique registrado e a situação deferida ou indeferida não seja cancelada por este caminho

## Pré-requisitos

- Requerente efetivo da solicitação
- Situação rascunho ou protocolada
- Motivo preenchido, com até 1000 caracteres

## Dependências

**Passadas**

- HU.164 — Criar solicitação de viabilidade

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Cancelar solicitação

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-solicitacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cancelamento aceito**
**Dado que** o dono tem um rascunho ou uma solicitação protocolada e informa o motivo
**Quando** cancela
**Então** a situação fica cancelada, o motivo e o autor ficam gravados e a lista confirma o cancelamento

**Cenário 2: Motivo inválido**
**Dado que** o motivo está vazio ou tem mais de 1000 caracteres
**Quando** envia
**Então** o sistema recusa

**Cenário 3: Situação que não pode ser cancelada**
**Dado que** a situação não está entre as canceláveis
**Quando** o cancelamento é pedido
**Então** nada é cancelado, o bloqueio fica registrado e o usuário recebe o erro

**Cenário 4: Outra pessoa**
**Dado que** outro usuário tenta cancelar, ou a situação já está decidida
**Quando** aciona o cancelamento
**Então** o sistema recusa antes de alterar a solicitação

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
