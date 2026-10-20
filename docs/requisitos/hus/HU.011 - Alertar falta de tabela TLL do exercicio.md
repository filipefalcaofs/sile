# HU.011 – ALERTAR FALTA DE TABELA TLL DO EXERCÍCIO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 20/10/2026 | Criação da HU para função já existente no sistema e contada, que nenhuma HU descrevia | 2.2 | Sudoeste Informática |

---

## Visão Geral

Em dezembro e janeiro, uma rotina diária confere se o exercício do ano corrente e o do ano seguinte têm tabela de valores da TLL vigente. Quando falta, cada gestor que recebe escalonamentos é avisado na central de notificações do sistema, com o link para Valores TLL. A rotina só avisa: não grava valor nem publica a tabela.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Alertar falta de tabela TLL do exercício](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** que os gestores sejam avisados quando falta a tabela TLL do exercício**, de modo que** a tabela seja publicada antes de o exercício começar sem valores vigentes

## História de Usuário: Alertar falta de tabela TLL do exercício

**Como** gestor da SEDUR que recebe escalonamentos **quero** ser avisado no sistema quando falta a tabela TLL do exercício**, de modo que** eu publique os valores da TLL a tempo

## Pré-requisitos

- Rotina agendada ativa no servidor
- Ao menos um perfil com a permissão `receber-escalonamento-sla`
- Central de notificações habilitada

## Dependências

**Passadas**

- HU-006 — o perfil que recebe a permissão de escalonamento
- HU-010 — a tabela TLL cujo exercício é conferido

**Futuras**

- HU-181 — o aviso aparece na central de notificações
- HU-010 — o gestor abre Valores TLL pelo link do aviso para gerar e publicar a tabela

---

## Interface de Usuário

### Tela 1 — Notificações da gestão

**Caminho no menu:** Sininho no topo da gestão > Notificações (`/gestao/notificacoes`)

*[Print da tela](screenshots/gestao-notificacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Aviso de exercício sem tabela**
**Dado que** que é dezembro ou janeiro e a tabela TLL de um exercício não foi publicada
**Quando** a rotina diária roda
**Então** o gestor vê em Notificações o aviso «Tabela TLL sem publicação», com o exercício e o link para Valores TLL

**Cenário 2: Aviso não se repete**
**Dado que** que o gestor já recebeu o aviso daquele exercício
**Quando** a rotina roda de novo no dia seguinte
**Então** nenhum aviso novo aparece para esse exercício

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
