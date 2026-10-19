# HU.048 – APLICAR REGRA DE BAIXO RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Baixo é o nome de tela do menor nível de risco municipal. No mapa padrão, esse nível segue no fluxo expresso. Quem mantém os parâmetros pode trocar o destino para análise. A regra não calcula porte nem área: ela só lê o nível já classificado.

Médio risco não é baixo risco. Alto também não. Uma exceção de risco ativa, ou a falta de classificação, tira o caso do fluxo expresso mesmo que o mapa diga o contrário. A falta de classificação vai para análise antes do mapa. Se a planilha de tratamento reconhecer o ramo, o encaminhamento é o do ramo, e não o do nível Baixo.

Entre os três níveis municipais, Baixo é o menos restritivo quando o sistema escolhe o CNAE mais severo do pedido. O sistema ainda não oferece, nesta regra, o deferimento automático. Ela só marca se o caso pode seguir no fluxo expresso. Quem emite a decisão é o fluxo expresso.

---

## Sumário

- [Épico: EP06 — Classificação de Risco](#épico)
- [História de Usuário: Aplicar regra de baixo risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP06 — Classificação de Risco

**Como** a SEDUR **quero** encaminhar o risco Baixo pelo mapa vigente**, de modo que** o fluxo expresso só receba o caso quando o mapa e as exceções permitirem

## História de Usuário: Aplicar regra de baixo risco

**Como** analista ou gestor **quero** que o CNAE classificado como Baixo siga o mapa de encaminhamento**, de modo que** uma exceção ou a falta de classificação não deixe o caso no fluxo expresso por engano

## Pré-requisitos

- CNAE classificado como Baixo na versão municipal vigente
- Mapa de encaminhamento com o destino do nível Baixo, fluxo expresso ou análise
- Quem altera o mapa é quem mantém os parâmetros do sistema
- A classificação é feita pelo sistema; não há um botão separado de baixo risco

## Dependências

**Passadas**

- HU.047 — Classificar CNAE por risco

**Futuras**

- Fluxo expresso, que emite a decisão quando o caso é elegível

---

## Interface de Usuário

### Tela 1 — Aplicar regra de baixo risco

**Caminho no menu:** Regras > CNAEs

*[Print da tela](screenshots/gestao-cnaes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Baixo elegível ao fluxo expresso**
**Dado que** o CNAE está como Baixo, a lista que decide é a municipal, o mapa aponta fluxo expresso e não há exceção de risco nem ramo da planilha
**Quando** o sistema classifica a atividade
**Então** o encaminhamento é fluxo expresso e o motivo cita o nível Baixo

**Cenário 2: Exceção de risco com nível Baixo**
**Dado que** uma exceção de risco ativa se aplica ao caso
**Quando** o CNAE está como Baixo
**Então** o encaminhamento vai para análise e o motivo é o da exceção

**Cenário 3: Mapa alterado para análise**
**Dado que** o mapa do nível Baixo foi alterado para análise, ou o nível não está no mapa
**Quando** o sistema classifica a atividade
**Então** o encaminhamento vai para análise

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
