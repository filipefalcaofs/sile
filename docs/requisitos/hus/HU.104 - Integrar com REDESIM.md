# HU.104 – INTEGRAR COM REDESIM
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

A Junta Comercial envia a declaração do integrador federal. O sistema só confirma o recebimento depois de gravar o envelope. Sem essa gravação, o recebimento não é confirmado. Protocolo em branco é recusado. Declaração reconhecida sem os dados mínimos para protocolar também é recusada, com a lista do que falta.

Protocolo novo fica gravado, inclusive com CNPJ, função, registro na Junta, serviço e o corpo da declaração, e então o recebimento é confirmado. O mesmo protocolo enviado de novo é informado como já recebido e não abre outro recebimento. Se o registro da trilha falhar depois da gravação, a Junta ainda recebe a confirmação de que o envelope ficou armazenado.

A abertura do processo municipal ocorre em seguida. Se essa abertura falhar, o recebimento permanece, a pendência fica registrada e o processo não é dado como aberto. As perguntas usadas são as que já vieram na declaração, casadas com a planilha de tratamento. Não há formulário complementar hospedado à parte pela SEDUR.

A confirmação é do envelope recebido, não da consulta ao cadastro imobiliário nem ao mapa, que ocorrem na abertura. O registro do recebimento guarda protocolo, função, serviço, resultado e duração, sem o corpo da declaração e sem o CNPJ nesse registro de aplicação. A devolução do parecer à Junta é outra comunicação. O sistema ainda não consulta o protocolo BAP da Junta.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Integrar com REDESIM](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** gestor **quero** que a declaração da Junta só seja confirmada depois de gravada**, de modo que** um protocolo não armazenado não seja tratado como entregue

## História de Usuário: Integrar com REDESIM

**Como** gestor **quero** receber a declaração do integrador federal e abrir o processo municipal a partir dela**, de modo que** a confirmação à Junta corresponda ao que ficou armazenado

## Pré-requisitos

- Declaração enviada pela Junta, com protocolo
- Dados mínimos para protocolar, quando a declaração for reconhecida

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Recebimento da declaração

**Caminho no menu:** Não há tela da SEDUR para este recebimento. A Junta envia a declaração ao canal de integração. O acompanhamento fica em Auditoria > Log de integrações.

*[Print da tela](screenshots/gestao-integracoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Declaração nova e completa**
**Dado que** o protocolo ainda não foi recebido e a declaração tem os dados mínimos
**Quando** a Junta envia a declaração
**Então** o sistema grava o envelope e só então confirma o recebimento

**Cenário 2: Protocolo repetido**
**Dado que** o mesmo protocolo já foi gravado
**Quando** a Junta envia de novo
**Então** o sistema informa que já foi recebido e não abre outro recebimento

**Cenário 3: Declaração incompleta**
**Dado que** o protocolo está em branco ou faltam dados para protocolar
**Quando** a Junta envia a declaração
**Então** o sistema recusa e, no segundo caso, informa o que falta

**Cenário 4: A abertura do processo falha**
**Dado que** o envelope já foi gravado e confirmado
**Quando** a abertura do processo municipal falha
**Então** o recebimento permanece, a pendência fica registrada e o processo não é dado como aberto

**Cenário 5: Protocolo da Junta ainda sem consulta**
**Dado que** a declaração foi recebida
**Quando** se procura o vínculo do protocolo BAP
**Então** o sistema ainda não consulta este serviço e não inventa o número

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
