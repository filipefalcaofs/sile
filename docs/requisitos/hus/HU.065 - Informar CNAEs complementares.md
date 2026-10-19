# HU.065 – INFORMAR CNAES COMPLEMENTARES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

No mesmo passo da atividade principal, o requerente pode informar nenhuma ou várias atividades complementares ativas, até o limite definido pela SEDUR. O padrão desse limite é 99. Não há quantidade fixa além desse limite e não há tela separada da atividade principal.

Cada complementar precisa ser CNAE ativo, sem repetição na lista e diferente da principal. A lista enviada substitui a anterior: complementar omitido sai do pedido. A intenção de incluir ou excluir segue a mesma regra da atividade principal. As restrições de escritório virtual valem para o conjunto da principal com as complementares. A simulação anterior é invalidada e o registro mostra os códigos complementares que ficaram.

O sistema ainda não oferece complementar obrigatório, nem gravação de complementar sem atividade principal no mesmo envio.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Informar CNAEs complementares](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente **quero** informar atividades complementares no rascunho**, de modo que** a simulação e a decisão considerem o conjunto de CNAEs do pedido

## História de Usuário: Informar CNAEs complementares

**Como** requerente dono do rascunho **quero** acrescentar CNAEs complementares ativos, dentro do limite**, de modo que** eles não repitam a atividade principal

## Pré-requisitos

- Rascunho da própria solicitação
- Atividade principal ativa no mesmo envio
- Complementares ativos, distintos e dentro do limite definido pela SEDUR

## Dependências

**Passadas**

- HU.064 — Informar atividade econômica

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Informar CNAEs complementares

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Complementares dentro do limite**
**Dado que** o rascunho tem atividade principal ativa
**Quando** o dono envia complementares ativos, dentro do limite e diferentes da principal
**Então** fica uma principal e as demais como secundárias

**Cenário 2: Sem complementares**
**Dado que** a lista de complementares está vazia ou foi omitida
**Quando** salva
**Então** permanece só a principal enviada

**Cenário 3: Lista inválida**
**Dado que** a quantidade passa do máximo, o CNAE está inativo, o código se repete ou a principal está entre as complementares
**Quando** envia
**Então** o sistema recusa e o conjunto anterior não muda

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
