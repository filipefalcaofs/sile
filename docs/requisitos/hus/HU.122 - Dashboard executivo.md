# HU.122 – DASHBOARD EXECUTIVO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O painel de entrada do console mostra o movimento da operação para quem acompanha indicadores e não está na cadeira de analista. A janela é de 7, 30 ou 90 dias; outro valor usa 30 dias. Os números saem das mesmas fontes dos relatórios: protocolos do período e do período anterior, variação, decisões por fluxo, estoque, atrasados, saldo, pico, projeção de 30 dias, taxa do fluxo expresso e a meta, série, tempo até a decisão, cumprimento de prazo, ações, etapas, envelhecimento da fila e carga de setores e analistas.

Indicador sem amostra não aparece como zero de desempenho. Taxa do expresso e variação ficam sem número quando não há base. A projeção não vira número se a janela é zero. A carga com nome do analista só entra para quem pode ver a produtividade nominal; sem isso, a carga sai sem identificação.

Analista servidor ou analista terceirizado que analisa processos vai para a caixa de entrada, mesmo que também acompanhe relatórios. Sem relatórios e com análise de processos, a entrada também é a caixa. Sem isso e com distribuição de processos, a entrada é a caixa do setor. Servidor sem papel vê o pedido de acesso, não um número fictício. O painel não substitui a caixa de quem opera o processo.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Dashboard executivo](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** ver o movimento da operação ao entrar no console**, de modo que** eu acompanhe fila, prazo e resposta do fluxo expresso

## História de Usuário: Dashboard executivo

**Como** gestor **quero** os indicadores da janela escolhida**, de modo que** quem analisa processo vá para a fila de trabalho e não para este painel

## Pré-requisitos

- Acesso ao console e termo de proteção de dados aceito
- Atribuição de consultar relatórios, para ver os números
- Não estar no papel de analista que é encaminhado à caixa de entrada

## Dependências

**Passadas**

- Processos, decisões e prazos já registrados

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Painel da operação

**Caminho no menu:** Operação > Painel

*[Print da tela](screenshots/gestao-painel.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Gestor**
**Dado que** o gestor consulta relatórios e não está no papel de analista
**Quando** entra no console
**Então** vê os indicadores da janela de 7, 30 ou 90 dias, ou de 30 dias se a janela não for uma dessas

**Cenário 2: Analista**
**Dado que** o usuário é analista servidor ou analista terceirizado que analisa processos
**Quando** entra no console
**Então** abre a caixa de entrada, e não o painel

**Cenário 3: Sem amostra**
**Dado que** o período não tem base para a taxa do fluxo expresso
**Quando** o painel é montado
**Então** a taxa não é fabricada nem exibida como zero de desempenho

**Cenário 4: Nome do analista**
**Dado que** o gestor não tem a visão nominal da produtividade
**Quando** a carga de analistas aparece
**Então** ela sai sem nome

**Cenário 5: Servidor sem papel**
**Dado que** o servidor não tem papel no console
**Quando** entra
**Então** vê o pedido de acesso e não um indicador fictício

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
