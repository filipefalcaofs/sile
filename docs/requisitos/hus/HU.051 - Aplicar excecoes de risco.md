# HU.051 – APLICAR EXCEÇÕES DE RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Uma exceção de risco ativa tira o caso do mapa e manda para análise, mesmo quando o mapa diria fluxo expresso. Quem mantém os gatilhos de risco liga ou desliga cada exceção e edita o motivo. Desligar não apaga o registro: o sistema deixa de aplicá-la. A tela não cria exceção nova fora das já previstas.

Na carga inicial há três exceções, ativas na criação: enquadramento ausente, zona especial e dados do processo. Se o tipo de imóvel exige análise, ou se o valor informado não é reconhecido, entra a exceção de dados do processo. Tipo de imóvel omitido não aciona essa exceção.

A consulta prévia não envia a zona especial encontrada no mapa como exceção de risco. Essa exceção só vale se já vier indicada no caso e estiver ativa. O sistema ainda não oferece mudança de nível por resposta de condicionante. O risco sanitário permanece o da lista, e as frases da linha municipal só entram na fundamentação.

Outras situações do fluxo expresso, como regra de lugar, divergência de zona ou empate de porções, também podem mandar o caso à análise. Elas não passam por esta lista de exceções.

---

## Sumário

- [Épico: EP06 — Classificação de Risco](#épico)
- [História de Usuário: Aplicar exceções de risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP06 — Classificação de Risco

**Como** a SEDUR **quero** aplicar as exceções que mandam o caso à análise**, de modo que** um contexto marcado como exceção não permaneça no fluxo expresso

## História de Usuário: Aplicar exceções de risco

**Como** gestor **quero** ligar ou desligar as exceções de risco e ver o motivo**, de modo que** o encaminhamento mude sem apagar o cadastro da exceção

## Pré-requisitos

- Exceção cadastrada e ativa
- Quem mantém os gatilhos de risco altera motivo, liga e desliga
- Tipo de imóvel informado quando a exceção de dados do processo depender dele

## Dependências

**Passadas**

- HU.047 — Classificar CNAE por risco

**Futuras**

- Fluxo expresso, que também encaminha por regra de lugar, divergência de zona ou empate, fora desta lista

---

## Interface de Usuário

### Tela 1 — Aplicar exceções de risco

**Caminho no menu:** Regras > Gatilhos de risco

*[Print da tela](screenshots/gestao-gatilhos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Tipo de imóvel que exige análise**
**Dado que** a exceção de dados do processo está ativa e o tipo de imóvel exige análise ou não é reconhecido
**Quando** o sistema classifica a atividade
**Então** o encaminhamento vai para análise e a exceção aparece entre as acionadas

**Cenário 2: Risco sanitário sem reclassificação**
**Dado que** o sistema recebe o risco sanitário de um CNAE
**Quando** o resultado sanitário é lido
**Então** o nível não muda por resposta de condicionante

**Cenário 3: Restrição vista na consulta prévia**
**Dado que** a consulta prévia identifica restrição no ponto
**Quando** o risco é classificado por essa consulta
**Então** a zona especial não entra sozinha como exceção de risco

**Cenário 4: Exceção desligada**
**Dado que** a exceção foi desligada na tela
**Quando** o sistema classifica um caso que antes a acionaria
**Então** ela deixa de alterar o encaminhamento e o cadastro permanece

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
