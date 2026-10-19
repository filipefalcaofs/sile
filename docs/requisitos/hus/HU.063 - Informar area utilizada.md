# HU.063 – INFORMAR ÁREA UTILIZADA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

A área utilizada entra no mesmo salvamento do imóvel. É obrigatória, numérica e maior que zero. Não há um passo separado só da área. O sistema ainda não oferece tela exclusiva de área, nem teto ou faixa de área por CNAE ou zona neste passo.

A comparação é com o próprio desenho do imóvel, não com um lote oficial. Se a área declarada supera a do desenho além da tolerância definida pela SEDUR, o sistema grava o alerta, mostra a área declarada, a área do desenho, a tolerância e a divergência, e deixa o registro. Não impede salvar nem, por si só, protocolar. Área menor ou dentro da tolerância não gera alerta. Sem desenho medível, não há alerta.

No protocolo, área ausente ou menor ou igual a zero bloqueia a protocolização, com a pendência de área utilizada. O sistema ainda não oferece bloqueio do salvamento ou do protocolo só pela divergência entre a área declarada e o desenho.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Informar área utilizada](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente **quero** informar a área utilizada em metros quadrados**, de modo que** a análise e o protocolo usem a área do pedido

## História de Usuário: Informar área utilizada

**Como** requerente dono do rascunho **quero** registrar a área utilizada junto com o imóvel**, de modo que** eu seja alertado se ela exceder o desenho além da tolerância, sem perder o que já informei

## Pré-requisitos

- Rascunho da própria solicitação
- Desenho do imóvel no mesmo envio
- Área numérica maior que zero

## Dependências

**Passadas**

- HU.062 — Informar imóvel

**Futuras**

- HU.068 — Protocolar solicitação, que exige área maior que zero

---

## Interface de Usuário

### Tela 1 — Informar área utilizada

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Área gravada**
**Dado que** o rascunho tem área maior que zero
**Quando** o dono salva o imóvel
**Então** a área utilizada fica gravada e a simulação anterior deixa de valer

**Cenário 2: Área acima da tolerância**
**Dado que** a área declarada supera a do desenho além da tolerância
**Quando** salva
**Então** o registro permanece, a resposta traz o alerta e a divergência fica registrada

**Cenário 3: Área inválida**
**Dado que** a área é zero, negativa ou está ausente
**Quando** envia o passo
**Então** o sistema recusa

**Cenário 4: Protocolo sem área**
**Dado que** o rascunho não tem área positiva
**Quando** tenta protocolar
**Então** o protocolo é bloqueado por dados mínimos, antes de gerar número

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
