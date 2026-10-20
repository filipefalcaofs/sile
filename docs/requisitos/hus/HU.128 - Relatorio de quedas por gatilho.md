# HU.128 – RELATÓRIO DE QUEDAS POR GATILHO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O gestor vê a taxa de resposta do fluxo expresso, a série no tempo e o ranking dos motivos pelos quais o processo elegível não foi decidido por ele. O ranking é o que foi registrado nas quedas, não uma lista fixa reescrita nesta tela, e não é a lista genérica de indeferimentos. Sem base, a taxa não vira zero de desempenho quando não há percentual a mostrar. A exportação baixa as quedas do mesmo filtro, em CSV, XLSX ou PDF habilitados, e não a lista genérica de solicitações. A consulta fica registrada. O painel de entrada reutiliza a taxa e o ranking; esta é a tela dedicada. Não há ação aqui para alterar regra nem reenquadrar processo.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Relatório de quedas por gatilho](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** ver a taxa de resposta do fluxo expresso e os motivos de queda**, de modo que** eu saiba o que tirou o processo da decisão automática

## História de Usuário: Relatório de quedas por gatilho

**Como** gestor **quero** taxa, série e ranking no mesmo recorte**, de modo que** o arquivo exportado seja das quedas filtradas e não das solicitações em geral

## Pré-requisitos

- Atribuição de consultar relatórios

## Dependências

**Passadas**

- Processos elegíveis ao fluxo expresso e quedas já registradas

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Quedas do fluxo expresso

**Caminho no menu:** Indicadores > Quedas do expresso

*[Print da tela](screenshots/gestao-quedas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Recorte**
**Dado que** o gestor consulta o relatório
**Quando** a tela abre
**Então** vê taxa, série e ranking de motivos do mesmo recorte, e a consulta fica registrada

**Cenário 2: Sem base**
**Dado que** não há processos para a taxa
**Quando** o relatório é montado
**Então** a ausência não é exibida como zero de desempenho

**Cenário 3: Exportar**
**Dado que** o gestor pede o arquivo
**Quando** a exportação ocorre
**Então** o arquivo traz as quedas filtradas, não as solicitações genéricas

**Cenário 4: Sem alteração de regra**
**Dado que** o gestor está no relatório
**Quando** procura reenquadrar um processo ou mudar uma regra
**Então** esta tela não faz isso

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
