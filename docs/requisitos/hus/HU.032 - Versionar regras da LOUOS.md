# HU.032 – VERSIONAR REGRAS DA LOUOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador guarda o Quadro 10, o Quadro 11A, a planilha de tratamento e as regras territoriais em versões. A decisão e a consulta usam a versão resolvida: a da simulação, a vigente numa data ou a vigente agora, e não a última linha editada fora de versão. Publicar Quadro 10, Quadro 11A, planilha de tratamento e regras territoriais exige outra pessoa, diferente de quem elaborou o rascunho. A versão anterior permanece. Ativar uma versão antiga troca a vigente sem apagar o histórico. Descartar o rascunho não altera a vigente.

A simulação reexecuta o motor de verdade na versão em rascunho, compara o resultado com o da vigente e informa quantos cenários mudariam. Não publica, não avisa o cidadão e não grava decisão. A amostra padrão reúne cinquenta combinações, se outro tamanho não estiver definido, montadas com CNAEs ativos e uma zona do Quadro 10 vigente. Não simula processos já protocolados. Zona citada no Quadro 10 e classe citada no Quadro 11A precisam existir e estar ativas. Recarregar as regras territoriais de partida substitui as regras da versão vigente e não cria uma segunda versão. Não há versionamento do Quadro 7 nem de um Quadro 11 separado. Quem consulta o Motor de regras é o administrador, o analista terceirizado e o coordenador. O cidadão não consulta. Publicar, importar, editar rascunho e simular é só o administrador.

---

## Sumário

- [Épico: EP05 — Motor de Regras da LOUOS](#épico)
- [História de Usuário: Versionar regras da LOUOS](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP05 — Motor de Regras da LOUOS

**Como** a SEDUR **quero** publicar regras em versão nova, com outra pessoa**, de modo que** a decisão use a vigente e ainda possa ser reproduzida numa data ou numa simulação

## História de Usuário: Versionar regras da LOUOS

**Como** administrador **quero** editar um rascunho e publicar uma versão nova, com outra pessoa publicando**, de modo que** a decisão use a versão vigente e ainda seja reproduzível numa data ou numa simulação

## Pré-requisitos

- Consulta do Motor de regras: administrador, analista terceirizado ou coordenador
- Publicar, ativar, importar, editar rascunho e simular: administrador
- Quem publica não pode ser o autor do rascunho, no Quadro 10, no Quadro 11A, na planilha de tratamento e nas regras territoriais
- Zona do Quadro 10 e classe de via do Quadro 11A existentes e ativas

## Dependências

**Passadas**

- HU-028 e HU-029 — rascunho dos quadros
- HU-027 — planilha de tratamento
- Cadastro de zonas e de vias ativos

**Futuras**

- HU-046, HU-047 e HU-048 — o motor lê a versão resolvida

---

## Interface de Usuário

### Tela 1 — Versões do Motor de regras

**Caminho no menu:** Regras > Motor de regras. A simulação fica em Regras > Simulação de regras

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Publicação por outra pessoa**
**Dado que** existe rascunho do Quadro 10, do Quadro 11A, da planilha de tratamento ou das regras territoriais, e quem publica é outro usuário
**Quando** a publicação é confirmada
**Então** nasce uma versão nova e a anterior deixa de ser a vigente, sem ser apagada

**Cenário 2: Autor não publica o próprio rascunho**
**Dado que** o autor tenta publicar o próprio rascunho
**Quando** a publicação é submetida
**Então** ela é recusada

**Cenário 3: Simulação não altera a vigente**
**Dado que** um rascunho é simulado
**Quando** a simulação termina
**Então** o relatório conta as divergências de resultado e a versão vigente permanece a mesma

**Cenário 4: Decisão numa data ou numa versão indicada**
**Dado que** o enquadramento recebe uma data ou uma versão indicada
**Quando** o motor resolve o quadro
**Então** usa a versão daquela data ou a versão indicada, não a vigente atual

**Cenário 5: Zona ou classe desconhecida**
**Dado que** o rascunho cita zona ou classe de via que não existe ou não está ativa
**Quando** outra pessoa tenta publicar
**Então** a publicação é bloqueada e a linha não vira ausência silenciosa de regra

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
