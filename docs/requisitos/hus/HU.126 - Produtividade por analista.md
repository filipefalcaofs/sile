# HU.126 – PRODUTIVIDADE POR ANALISTA
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

A produtividade mede decisões da análise técnica por analista. O nome e a visão de toda a equipe exigem a atribuição nominal. Sem ela, o gestor vê só o próprio recorte, sem identificação. Consultar relatórios, sozinho, não libera o nome. Quando a visão é nominal, a consulta fica registrada como dado pessoal. A exportação respeita o mesmo corte, inclusive no arquivo gerado depois. O recorte de pessoas não vem do filtro digitado: vem da atribuição e de quem está autenticado. Os demais filtros são os comuns dos relatórios.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Produtividade por analista](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** ver a produtividade da equipe**, de modo que** o nome do analista só apareça para quem pode ver a identificação

## História de Usuário: Produtividade por analista

**Como** gestor **quero** o volume decidido na análise técnica**, de modo que** sem a visão nominal eu veja só o meu recorte e sem nome

## Pré-requisitos

- Atribuição de consultar relatórios
- Atribuição nominal, para ver nomes e a equipe inteira

## Dependências

**Passadas**

- Decisões da análise técnica com responsável

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Produtividade

**Caminho no menu:** Indicadores > Produtividade

*[Print da tela](screenshots/gestao-produtividade.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Visão nominal**
**Dado que** o gestor pode ver a produtividade com nome
**Quando** abre a tela
**Então** a lista identifica os analistas do recorte e a consulta fica registrada como dado pessoal

**Cenário 2: Sem visão nominal**
**Dado que** o usuário só consulta relatórios
**Quando** abre a produtividade
**Então** não há nome e o conjunto fica no próprio usuário

**Cenário 3: Exportar**
**Dado que** o gestor pede o arquivo
**Quando** a exportação ocorre, na hora ou depois
**Então** o arquivo respeita o mesmo corte de nome e de pessoas

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
