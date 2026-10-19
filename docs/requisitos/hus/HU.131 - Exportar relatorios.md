# HU.131 – EXPORTAR RELATÓRIOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O gestor baixa o mesmo conjunto filtrado na tela, em CSV, XLSX ou PDF, e não só a página que está vendo. Isso vale para os relatórios e também para as listagens de atividades econômicas, usuários, perfis, dados do sistema, processos, trilha de auditoria e histórico de acessos, cada uma com os filtros da própria tela. Feriados, setores e os demais cadastros que não oferecem exportação não entram neste caminho. O PDF da trilha de um processo é outra ação.

Até cinco mil linhas, ou até o limite de entrega imediata, o arquivo volta na hora. Acima disso, o sistema avisa que o arquivo será gerado e o arquivo posterior respeita os mesmos filtros. Formato desconhecido não gera arquivo. Cada exportação e cada download entram na auditoria, e o download de dado pessoal fica marcado quando a lista é desse tipo.

O download posterior exige link válido. Sem esse link, o arquivo não é entregue. Quem baixa precisa ser o autor do arquivo ou poder consultar relatórios. Arquivo já removido pela retenção não é entregue, e não sai arquivo vazio no lugar. Arquivo em área pública também não é entregue. Na produtividade, o arquivo posterior mantém o mesmo sigilo de nome da tela.

---

## Sumário

- [Épico: EP15 — Relatórios e Indicadores](#épico)
- [História de Usuário: Exportar relatórios](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP15 — Relatórios e Indicadores

**Como** gestor **quero** baixar o conjunto que filtrei**, de modo que** o arquivo fora do console seja o mesmo recorte da tela

## História de Usuário: Exportar relatórios

**Como** gestor **quero** CSV, XLSX ou PDF do recorte**, de modo que** volume grande não trave a tela e o download posterior não alargue o filtro

## Pré-requisitos

- Atribuição de consultar relatórios na tela que exporta
- Link válido e autoria do arquivo, ou atribuição de consultar relatórios, para o download posterior

## Dependências

**Passadas**

- Tela de relatório ou listagem que oferece exportação

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Exportar o recorte

**Caminho no menu:** A ação de exportar nas telas de Indicadores, Relatórios, Auditoria > Trilha de auditoria e nas listagens que oferecem o arquivo

*[Print da tela](screenshots/gestao-indicadores.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Volume dentro do limite**
**Dado que** o formato está habilitado e o recorte cabe na entrega imediata
**Quando** o gestor exporta
**Então** recebe o arquivo do conjunto filtrado, não só da página atual, e a exportação fica na auditoria

**Cenário 2: Volume acima do limite**
**Dado que** o recorte passa do limite
**Quando** o gestor pede o arquivo
**Então** a tela não espera o arquivo inteiro e o arquivo posterior respeita os mesmos filtros

**Cenário 3: Arquivo ausente ou link inválido**
**Dado que** o arquivo já foi removido, está em área pública ou o link não é válido
**Quando** alguém tenta baixar
**Então** o arquivo não é entregue

**Cenário 4: Cadastro sem exportação**
**Dado que** a tela é a de feriados ou a de setores
**Quando** o administrador procura esta exportação
**Então** esses cadastros não saem por este caminho

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
