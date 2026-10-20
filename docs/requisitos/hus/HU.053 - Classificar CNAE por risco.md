# HU.053 – CLASSIFICAR CNAE POR RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O sistema classifica a atividade em duas listas separadas: risco municipal e risco sanitário. Cada uma usa a versão vigente, ou a vigente na data consultada. O resultado diz se o caso segue no fluxo expresso ou vai para análise.

Na tela, o risco municipal aparece como Baixo, Médio risco ou Alto. Médio risco é o nome da SEDUR para o nível do meio. O risco sanitário também usa Baixo, Médio risco e Alto. O Médio risco sanitário não substitui o municipal.

Quando a planilha de tratamento reconhece o CNAE de sete dígitos, o nível municipal mostrado passa a ser o do ramo, e o encaminhamento segue o que a planilha definir. Sem ramo, vale a lista que a SEDUR deixou como a que decide o encaminhamento. Sem nível, ou com nível fora do mapa, o caso vai para análise. Uma exceção de risco ativa no caso também manda para análise e mostra o motivo.

CNAE sem classificação não recebe nível inventado. As frases de condicionante da lista municipal entram na fundamentação e não mudam o nível. O sistema ainda não oferece perguntas da vigilância sanitária na ficha do CNAE nem alteração do risco sanitário por resposta de condicionante.

---

## Sumário

- [Épico: EP06 — Classificação de Risco](#épico)
- [História de Usuário: Classificar CNAE por risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP06 — Classificação de Risco

**Como** a SEDUR **quero** classificar o CNAE pelo risco municipal e pelo risco sanitário**, de modo que** o encaminhamento siga a versão vigente, a planilha de tratamento ou uma exceção de risco

## História de Usuário: Classificar CNAE por risco

**Como** analista ou gestor **quero** ver o risco vigente do CNAE e se o caso segue no fluxo expresso ou vai para análise**, de modo que** a decisão fique amparada na versão usada

## Pré-requisitos

- Versão vigente de risco municipal e, quando houver, de risco sanitário
- CNAE informado; a pontuação é ignorada e valem os dígitos
- Mapa de encaminhamento com Baixo, Médio risco e Alto, cada um apontando para fluxo expresso ou análise
- Ramo da planilha de tratamento só é reconhecido com CNAE de sete dígitos
- Quem edita a ficha do CNAE é quem mantém o cadastro de CNAEs; quem consulta a lista pode vê-la

## Dependências

**Passadas**

- Cadastro de CNAEs e versão publicada de risco

**Futuras**

- Consulta prévia de viabilidade, que mostra esta classificação
- Fluxo expresso, que usa o encaminhamento na decisão

---

## Interface de Usuário

### Tela 1 — Classificar CNAE por risco

**Caminho no menu:** Regras > CNAEs

*[Print da tela](screenshots/gestao-cnaes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: CNAE com risco municipal vigente**
**Dado que** o CNAE tem classificação na versão municipal vigente
**Quando** o sistema classifica a atividade
**Então** mostra Baixo, Médio risco ou Alto, a versão usada e o encaminhamento para fluxo expresso ou análise

**Cenário 2: CNAE sem classificação na lista que decide**
**Dado que** não há versão ou registro do CNAE na lista que decide o encaminhamento e a planilha de tratamento não reconhece o ramo
**Quando** o sistema classifica a atividade
**Então** informa que não há classificação, não inventa nível e encaminha para análise

**Cenário 3: Risco sanitário classificado**
**Dado que** o risco sanitário está classificado
**Quando** o resultado é lido
**Então** o nível sanitário final permanece o da lista vigente e o sistema não o altera por resposta de condicionante

**Cenário 4: Exceção de risco no caso**
**Dado que** uma exceção de risco ativa se aplica ao caso
**Quando** o sistema classifica a atividade
**Então** o encaminhamento vai para análise e o motivo é o da exceção

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
