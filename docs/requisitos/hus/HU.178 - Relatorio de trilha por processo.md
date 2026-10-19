# HU.178 – RELATÓRIO DE TRILHA POR PROCESSO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O gestor informa o número do protocolo e vê a história daquele processo: as mudanças dos dois eixos, os registros de atividade e a decisão, em ordem de data. Sem protocolo, a tela abre a busca e não consulta. Protocolo inexistente mostra a tela vazia e não cria processo. Imprimir protocolo inexistente não gera documento vazio: informa que não foi encontrado. O documento impresso identifica quem emitiu e a data de emissão, em folha A4, com o nome formado pelo protocolo. Esta impressão não é a exportação tabular dos outros relatórios. A trilha é só do protocolo informado, não a trilha geral da auditoria. Não há busca por nome ou CNPJ. Consulta com protocolo e impressão ficam registradas.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Relatório de trilha por processo](#história-de-usuário-relatório-de-trilha-por-processo)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Trilha por processo](#tela-1--trilha-por-processo)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** reconstruir o que ocorreu num processo**, de modo que** a prestação de contas use só os eventos daquele protocolo.

## História de Usuário: Relatório de trilha por processo

**Como** gestor autorizado a consultar relatórios **quero** informar o protocolo e imprimir a trilha**, de modo que** eu veja a história ordenada e o documento diga quem emitiu e quando.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Número de protocolo, para a consulta e para a impressão

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- A trilha geral da auditoria, com atividade, alteração e acesso, é outra tela

---

## Interface de Usuário

### Tela 1 — Trilha por processo

**Caminho no menu:** Relatórios > Trilha por processo

*[Print da tela](screenshots/gestao-trilha-rel.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Protocolo existente**
**Dado que** o protocolo existe
**Quando** o gestor consulta
**Então** a tela mostra o processo e os eventos em ordem de data

**Cenário 2: Protocolo desconhecido na tela**
**Dado que** o protocolo não existe
**Quando** o gestor consulta
**Então** a trilha fica vazia e nenhum processo é criado

**Cenário 3: Impressão de protocolo desconhecido**
**Dado que** o protocolo não existe
**Quando** o gestor pede a impressão
**Então** o documento não é gerado e a tela informa que não foi encontrado

**Cenário 4: Documento emitido**
**Dado que** o protocolo existe
**Quando** o gestor imprime
**Então** o documento identifica quem emitiu e a data de emissão, e a impressão fica registrada

**Cenário 5: Busca vazia**
**Dado que** o protocolo não foi informado
**Quando** a tela abre
**Então** só a busca aparece e nenhuma trilha é consultada

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
