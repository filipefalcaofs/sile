# HU.048 – APLICAR QUADRO 11A
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O motor lê as condições de instalação do Quadro 11A para a classe viária oficial e o grupo de uso. O quadro condiciona a instalação e não autoriza o uso: a permissão da zona continua sendo o Quadro 10. Sem via identificada, ou com via sem classe oficial, a dimensão fica indisponível e a classe não é inferida. Sem versão ou sem linha, a dimensão fica não encontrada e a classe consultada permanece no resultado.

A busca prefere o subgrupo, depois o grupo, depois a linha da classe com grupo vazio. Depois de o Quadro 10 permitir ou condicionar, a condição "não" torna o resultado não permitido, com via vedada e sem condicionantes. A condição "R", ou o texto de análise particularizada pela CNLU, torna o resultado pendente. Um único "sim" não vira condicionante. Os outros textos da lista viram condicionante de via. Via vedada indefere na resposta automática antes da trava de Alto. Se o Quadro 10 identificou permissão e o 11A está indisponível, o veredito da via não entra e o fluxo segue para as condicionantes.

---

## Sumário

- [Épico: EP05 — Motor de Regras da LOUOS](#épico)
- [História de Usuário: Aplicar Quadro 11A](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP05 — Motor de Regras da LOUOS

**Como** a SEDUR **quero** aplicar o Quadro 11A quando a via tiver classe oficial**, de modo que** a instalação seja vedada, encaminhada à CNLU ou condicionada sem inventar a classe

## História de Usuário: Aplicar Quadro 11A

**Como** analista **quero** ver as condições de instalação da classe da via**, de modo que** o parecer registre vedação, análise da CNLU ou condição sem autorizar o uso pela via

## Pré-requisitos

- Via identificada com classe oficial entre VA I, VA II, VC I, VC II, VE, VL e VP
- Versão vigente do Quadro 11A com linha para a classe e o grupo
- A classe do imóvel vem do território, não de escolha do analista nesta etapa

## Dependências

**Passadas**

- HU-029 — versão vigente do Quadro 11A
- HU-041 — classe oficial da via
- HU-047 — o Quadro 10 já ter permitido ou condicionado, para o efeito da via entrar no parecer

**Futuras**

- HU-051 — o resultado único aplica a precedência da via

---

## Interface de Usuário

### Tela 1 — Quadro 11A no parecer

**Caminho no menu:** O resultado aparece em Consultar viabilidade e no processo. A manutenção fica em Regras > Motor de regras

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Linha encontrada**
**Dado que** a via tem classe oficial e existe linha na versão vigente
**Quando** o Quadro 11A é aplicado
**Então** a dimensão fica identificada, com a classe, as condições e a base legal

**Cenário 2: Via vedada**
**Dado que** alguma condição é "não" e o Quadro 10 não proibiu antes
**Quando** o parecer é consolidado
**Então** o resultado é não permitido, com o motivo de via vedada e sem condicionantes

**Cenário 3: Análise da CNLU**
**Dado que** alguma condição é "R" ou o texto de análise particularizada pela CNLU
**Quando** o parecer é consolidado
**Então** o resultado é pendente

**Cenário 4: Via sem classe**
**Dado que** a via não tem classe oficial
**Quando** o quadro é aplicado
**Então** a dimensão fica indisponível e nenhuma classe é criada

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
