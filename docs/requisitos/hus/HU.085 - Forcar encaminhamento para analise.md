# HU.085 – FORÇAR ENCAMINHAMENTO PARA ANÁLISE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Quem está autorizado pesquisa um protocolo e o envia à análise, em qualquer situação do pedido, sede ou abrigado, sem depender do fluxo expresso. A pesquisa é pelo número exato. Vazio ou inexistente informa que não foi encontrado e não muda o processo. A prévia mostra protocolo, serviço, situação, requerente e, se já houver, a situação da análise. Enviar de novo, quando a análise já começou, avisa que já está em análise e não registra outro envio. O primeiro envio coloca o processo para distribuir, grava a etapa e o prazo de distribuição e, em seguida, dispara a pré-análise. A situação do pedido em si não muda. A chefia, além de analisar, pode forçar uma situação da análise que o menu comum não oferece. Sem essa autorização extra, a mudança só aceita o que o menu da situação atual lista. Situação dirigida por evento, como convite respondido ou expirado, não entra nesse menu. Transição recusada volta com erro e não ocorre. Com a autorização de forçar, a transição é gravada mesmo fora do menu e o registro marca que foi forçada. O motivo é opcional, até 2000 caracteres. Situação de encaminhado exige setor e usuário desse setor. Situação para conclusão pode gravar o destinatário, que precisa estar autorizado a concluir a análise. As duas ações são independentes: enviar à análise não usa a autorização de forçar. Na configuração inicial, analista servidor, analista terceirizado, chefias e administrador enviam à análise; o apoio não envia. Forçar está nas chefias (coordenador, subcoordenador e chefe de setor). Analista, apoio e administrador não forçam na configuração inicial.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico-ep10--análise-técnica-sedur)
- [História de Usuário: Forçar encaminhamento para análise](#história-de-usuário-forçar-encaminhamento-para-análise)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Enviar para análise](#tela-1--enviar-para-análise)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
  - [Tela 2 — Forçar a situação da análise](#tela-2--forçar-a-situação-da-análise)
  - [Comportamento Esperado Tela 2](#comportamento-esperado-tela-2)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista da SEDUR **quero** colocar o processo na fila da análise por envio manual**, de modo que** o eixo da análise comece sem depender do fluxo expresso.

## História de Usuário: Forçar encaminhamento para análise

**Como** usuário autorizado a enviar para análise **quero** pesquisar um protocolo e enviá-lo**, de modo que** a chefia autorizada possa, à parte, forçar uma situação que o menu comum não oferece.

## Pré-requisitos

- Autorização para enviar o processo à análise, na tela de envio
- Autorização para analisar e autorização extra para forçar, na mudança de situação
- Protocolo existente, para o envio

## Dependências

**Passadas**

- Depois do envio, a pré-análise do processo é disparada

**Futuras**

- O encaminhamento automático do fluxo expresso, que muda a situação do pedido para em análise, é outro fluxo

---

## Interface de Usuário

### Tela 1 — Enviar para análise

**Caminho no menu:** Não há item de menu. A tela é aberta por quem está autorizado a enviar o processo à análise.

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Sem autorização**
**Dado que** a pessoa não está autorizada a enviar para análise
**Quando** abre a tela
**Então** o acesso é negado

**Cenário 2: Protocolo inexistente**
**Dado que** o número não existe ou está em branco
**Quando** a pesquisa é feita
**Então** o processo não muda e a mensagem informa que não foi encontrado

**Cenário 3: Primeiro envio**
**Dado que** a análise ainda não começou
**Quando** o envio é confirmado
**Então** o processo fica para distribuir, com prazo de distribuição, a situação do pedido não muda e a pré-análise é disparada em seguida

**Cenário 4: Segundo envio**
**Dado que** a análise desse processo já começou
**Quando** o envio é tentado de novo
**Então** não nasce outra tramitação e o aviso informa que já está em análise

### Tela 2 — Forçar a situação da análise

**Caminho no menu:** Na ficha do processo, para quem analisa. Forçar só aparece para a chefia autorizada.

#### Comportamento Esperado Tela 2

**Cenário 1: Menu comum**
**Dado que** a pessoa analisa e não tem autorização para forçar
**Quando** pede uma situação fora do menu da situação atual
**Então** a mudança não ocorre e a tela mostra o erro

**Cenário 2: Chefia força a transição**
**Dado que** a chefia analisa e está autorizada a forçar
**Quando** pede uma situação fora do menu, com ou sem motivo
**Então** a transição é gravada e o registro marca que foi forçada

**Cenário 3: Encaminhado sem pessoa do setor**
**Dado que** a situação pedida é encaminhado
**Quando** o usuário informado não pertence ao setor
**Então** a transição não ocorre

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
