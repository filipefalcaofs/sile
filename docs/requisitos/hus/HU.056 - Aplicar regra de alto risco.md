# HU.056 – APLICAR REGRA DE ALTO RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Alto é o nome de tela do maior nível de risco municipal. No mapa padrão, esse nível vai para análise. Alto risco, por si, não indefere o pedido. O indeferimento pelo local, quando a atividade não é permitida, ocorre antes e não depende do risco.

No fluxo expresso, depois desse indeferimento locacional, o alto risco que não esteja num ramo da planilha marcado como fluxo expresso vai para análise. O processo não é deferido nem indeferido só por ser alto. A planilha prevalece: um ramo marcado como fluxo expresso segue nesse fluxo mesmo quando o texto do ramo fala em alto risco.

Uma exceção de risco ativa também manda para análise, com o motivo da exceção, antes dessa verificação. As frases de condicionante da linha municipal entram na fundamentação e não mudam o nível para Alto. O sistema ainda não oferece reclassificação para Alto por pergunta da vigilância sanitária. O nível sanitário final permanece o da lista. Alterar o mapa para mandar Alto ao fluxo expresso mudaria o encaminhamento da classificação; o fluxo expresso ainda separa o alto risco que não veio de ramo expresso da planilha.

---

## Sumário

- [Épico: EP06 — Classificação de Risco](#épico)
- [História de Usuário: Aplicar regra de alto risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP06 — Classificação de Risco

**Como** a SEDUR **quero** encaminhar o Alto risco à análise no mapa padrão**, de modo que** a decisão automática não defira esse caso fora de um ramo da planilha marcado como fluxo expresso

## História de Usuário: Aplicar regra de alto risco

**Como** analista **quero** que o CNAE de Alto risco vá para análise técnica**, de modo que** o fluxo expresso só o decida quando a planilha declarar esse fluxo

## Pré-requisitos

- Nível Alto gravado na versão municipal, na ficha do CNAE ou na importação do decreto
- Destino do Alto no mapa de encaminhamento, com valor fluxo expresso ou análise
- Alto sanitário só usa o mesmo mapa quando a lista que decide é a sanitária

## Dependências

**Passadas**

- HU.053 — Classificar CNAE por risco

**Futuras**

- Fluxo expresso, que aplica o indeferimento locacional antes da ida à análise por alto risco

---

## Interface de Usuário

### Tela 1 — Aplicar regra de alto risco

**Caminho no menu:** Regras > CNAEs

*[Print da tela](screenshots/gestao-cnaes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Alto risco no mapa padrão**
**Dado que** o nível que decide é Alto, o mapa padrão está vigente e não há ramo da planilha marcado como fluxo expresso
**Quando** o sistema classifica a atividade
**Então** o encaminhamento vai para análise

**Cenário 2: Local não permitido**
**Dado que** o resultado da LOUOS é não permitido
**Quando** o fluxo expresso decide
**Então** o indeferimento pelo local é emitido antes da verificação de alto risco

**Cenário 3: Alto risco sem veto do local**
**Dado que** não há veto do local e o processo tem alto risco fora do ramo da planilha marcado como fluxo expresso
**Quando** o fluxo expresso decide
**Então** o processo vai para análise e não é deferido nem indeferido por esse motivo

**Cenário 4: Importação com valor diferente de Alto**
**Dado que** a célula do decreto não é o nível Alto
**Quando** a importação municipal lê a linha
**Então** essa célula não vira Alto

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
