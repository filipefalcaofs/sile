# HU.131 – CONSULTAR TEMPO DE EMISSÃO DA VIABILIDADE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O gestor vê os processos decididos no período e o tempo entre a abertura e a emissão da viabilidade, em minutos úteis. O tipo exibido é sempre viabilidade. Pedir revisão devolve lista vazia: revisão pela REDESIM não é simulada. Os filtros são o tipo de serviço ativo, o resultado deferida ou indeferida e o período. O filtro de serviço lista só os tipos ativos cadastrados. Não aparece opção de atividade que não esteja cadastrada. Tempo que não pôde ser calculado fica em branco na linha, sem virar zero. A lista pagina em 10, 15, 25 ou 50 itens. A exportação repete o mesmo recorte. Esta página não é o gráfico de tempo médio. Não se edita a decisão aqui. A consulta fica registrada.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico-ep15--relatórios-e-indicadores)
- [História de Usuário: Consultar tempo de emissão da viabilidade](#história-de-usuário-consultar-tempo-de-emissão-da-viabilidade)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Emissão da viabilidade](#tela-1--emissão-da-viabilidade)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor da SEDUR **quero** ver o tempo de cada viabilidade decidida no período**, de modo que** a emissão seja lida em minutos úteis, separada do tempo médio.

## História de Usuário: Consultar tempo de emissão da viabilidade

**Como** gestor autorizado a consultar relatórios **quero** filtrar por serviço, resultado e período**, de modo que** cada processo decidido mostre o tempo real ou fique em branco quando o tempo não existir.

## Pré-requisitos

- Gestor autorizado a consultar relatórios
- Processos já decididos no recorte

## Dependências

**Passadas**

- Os tipos de serviço do filtro são os cadastrados e ativos

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Emissão da viabilidade

**Caminho no menu:** Indicadores > Emissão da viabilidade

*[Print da tela](screenshots/gestao-emissao.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Processos decididos**
**Dado que** há processos decididos no período
**Quando** o gestor abre a lista
**Então** cada linha mostra o processo e o tempo entre abertura e emissão, em minutos úteis

**Cenário 2: Revisão**
**Dado que** o gestor pede o tipo revisão
**Quando** a lista é carregada
**Então** nenhuma linha é fabricada e o conjunto vem vazio

**Cenário 3: Serviços do filtro**
**Dado que** o gestor abre o filtro de serviço
**Quando** a lista de opções aparece
**Então** só entram os tipos de serviço ativos cadastrados

**Cenário 4: Exportar**
**Dado que** o recorte está aplicado
**Quando** o gestor exporta
**Então** a exportação repete o mesmo recorte

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
