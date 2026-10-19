# HU.125 – SOLICITAÇÕES POR CNAE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O gestor vê o ranking das atividades com mais solicitações protocoladas no recorte. A contagem é de processos distintos: um processo com várias atividades conta uma vez em cada uma, e não várias vezes na mesma. A ordem é do maior volume para o menor, com desempate pela própria atividade. O limite padrão é de dez itens. Processo sem atividade não gera linha vazia. Só entra solicitação protocolada que passe nos filtros. Período invertido zera a série. A consulta fica na trilha. A exportação da tela baixa a lista de solicitações do recorte, não só o ranking.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Solicitações por CNAE](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** ver o ranking de atividades**, de modo que** eu entenda o perfil do que foi pedido no período

## História de Usuário: Solicitações por CNAE

**Como** gestor **quero** a contagem de processos distintos por atividade**, de modo que** o mesmo processo não se repita dentro de uma atividade

## Pré-requisitos

- Atribuição de consultar relatórios

## Dependências

**Passadas**

- Solicitações protocoladas com atividade informada

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Ranking de atividades

**Caminho no menu:** Indicadores > Indicadores de viabilidade

*[Print da tela](screenshots/gestao-indicadores.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Ranking**
**Dado que** há protocolos com atividade no recorte
**Quando** o gestor consulta
**Então** vê a atividade e o total, no máximo dez itens, do maior volume para o menor, e a consulta fica registrada

**Cenário 2: Várias atividades no mesmo processo**
**Dado que** um processo tem mais de uma atividade
**Quando** o ranking é montado
**Então** ele conta uma vez em cada atividade

**Cenário 3: Processo sem atividade**
**Dado que** o processo protocolado não tem atividade
**Quando** a série é montada
**Então** não surge linha vazia

**Cenário 4: Exportar**
**Dado que** o gestor pede o arquivo
**Quando** a exportação ocorre
**Então** o arquivo traz as solicitações do recorte, não só o ranking

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
