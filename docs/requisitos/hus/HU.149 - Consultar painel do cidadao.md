# HU.149 – CONSULTAR PAINEL DO CIDADÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 20/10/2026 | Criação da HU para função já existente no sistema e contada, que nenhuma HU descrevia | 2.2 | Sudoeste Informática |

---

## Visão Geral

O cidadão abre o Meu painel logo depois de entrar no portal. A tela mostra quantos processos estão em análise, quantos têm convite para responder e o total, lista o que depende dele (convites abertos e rascunhos a protocolar) e traz as últimas solicitações. Quando o usuário atua em nome de outra pessoa, o painel é o do representado. A tela só consulta.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Consultar painel do cidadão](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** a SEDUR **quero** que o cidadão veja em uma tela o andamento dos seus pedidos**, de modo que** ele responda rápido ao que a análise pede

## História de Usuário: Consultar painel do cidadão

**Como** cidadão com conta no portal **quero** abrir o Meu painel e ver indicadores, o que precisa da minha atenção e minhas solicitações recentes**, de modo que** eu saiba o que fazer em seguida sem procurar em cada solicitação

## Pré-requisitos

- Cidadão autenticado no portal
- Em representação, procuração vigente (HU-147)

## Dependências

**Passadas**

- HU-002 — entrada no portal
- HU-147 — vínculo de procurador, quando o painel é do representado

**Futuras**

- HU-177 — o botão Responder leva à resposta do convite
- HU-171 — o botão Retomar e protocolar leva ao protocolo da solicitação

---

## Interface de Usuário

### Tela 1 — Meu painel

**Caminho no menu:** Portal > Meu painel (`/portal/painel`)

*[Print da tela](screenshots/portal-painel.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Painel com indicadores e atenção**
**Dado que** há um cidadão com processos em análise, um convite aberto e um rascunho
**Quando** abre o Meu painel
**Então** vê os indicadores, o convite com prazo e o rascunho para retomar

**Cenário 2: Painel sem pendências**
**Dado que** há um cidadão sem convites abertos nem rascunhos
**Quando** abre o Meu painel
**Então** vê «Nada precisa de você agora» e as solicitações recentes

**Cenário 3: Painel sem solicitações**
**Dado que** há um cidadão que ainda não criou solicitações
**Quando** abre o Meu painel
**Então** vê indicadores zerados e «Você ainda não tem solicitações»

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
