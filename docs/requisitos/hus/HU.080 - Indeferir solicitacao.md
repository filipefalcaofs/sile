# HU.080 – INDEFERIR SOLICITAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Quem analisa e também conclui a análise indefere o processo em análise com justificativa visível ao requerente. É preciso ao menos uma atividade marcada como indeferida na ficha. O texto é o parecer da conclusão, senão o da ficha, senão as justificativas das atividades. Sem texto, nada é gravado. A situação fica indeferida, sem número de TVL, com consolidado não permitido.

Analista Terceirizado não indefere. Apoio não indefere. Processo já deferido não é indeferido por esta conclusão. Renovação com condicionantes não atendidas força indeferimento e ainda exige justificativa.

Convite aberto com prazo vencido é indeferido sem usuário: o motivo vem do texto padrão de convite expirado, sem CNAE e sem versão de regra. O analista atribuído, ou quem abriu o convite, é avisado. Uma segunda passagem não trata de novo o convite que já deixou de estar aberto. O sistema ainda não oferece envio do indeferimento à SEFAZ. O parecer à Junta, com o canal indisponível, fica registrado como bloqueado, sem marcar enviado. O PDF de indeferimento existe para decisão indeferida e é emitido por quem emite o TVL, em endereço temporário e reservado.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Indeferir solicitação](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** quem conclui a análise **quero** indeferir com o motivo visível ao requerente**, de modo que** a análise encerre sem TVL e o convite vencido sem resposta também indefira

## História de Usuário: Indeferir solicitação

**Como** analista que analisa e conclui **quero** indeferir a partir da ficha**, de modo que** não exista indeferimento humano sem justificativa

## Pré-requisitos

- Processo em análise, para o indeferimento humano
- Ao menos uma atividade marcada como indeferida e um texto de justificativa
- Quem analisa e quem conclui a análise
- Convite aberto com prazo vencido, para o indeferimento sem resposta

## Dependências

**Passadas**

- HU.078 — Emitir parecer
- HU.087 — Solicitar pendência, para o convite que pode vencer

**Futuras**

- HU.177 — Responder pendência, que também trata o vencimento

---

## Interface de Usuário

### Tela 1 — Indeferir solicitação

**Caminho no menu:** Operação > Caixa de entrada

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Sem concluir a análise**
**Dado que** a pessoa não conclui a análise
**Quando** tenta indeferir
**Então** o sistema recusa

**Cenário 2: Indeferimento com parecer**
**Dado que** há atividade indeferida e parecer
**Quando** indefere
**Então** o processo fica indeferido, o motivo fica preenchido e não há número de TVL

**Cenário 3: Sem justificativa**
**Dado que** não há texto na conclusão, na ficha nem nas atividades
**Quando** tenta indeferir
**Então** nada é gravado

**Cenário 4: Convite vencido**
**Dado que** o convite venceu sem resposta e o processo ainda está em pendência
**Quando** o prazo é aplicado
**Então** nasce decisão indeferida sem autor humano, com o motivo do convite expirado, e o analista é avisado

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
