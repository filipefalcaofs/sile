# HU.099 – EXPORTAR AUDITORIA
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

O auditor baixa o mesmo recorte que filtrou na trilha, em CSV, XLSX ou PDF. A visão de acessos exporta os acessos. As visões de atividade e de alterações exportam a trilha correspondente. O arquivo não é outro conjunto de dados.

Na exportação direta, formato ausente ou desconhecido vira CSV. Na tela, só CSV, XLSX e PDF disparam o download. Formato fora dos habilitados não gera arquivo vazio: a exportação não conclui.

Até cinco mil linhas, ou até o limite definido para a entrega imediata, o arquivo volta na hora. Acima disso, o sistema avisa que o arquivo será gerado e não trava a tela com o volume inteiro. O download posterior segue os mesmos filtros. Quem baixa esse arquivo precisa ser o autor ou poder consultar relatórios. A exportação fica na auditoria como dado pessoal.

Alertas de abuso e o painel de proteção de dados não saem por esta exportação.

---

## Sumário

- [Épico: EP12 — Auditoria e Compliance](#épico)
- [História de Usuário: Exportar auditoria](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP12 — Auditoria e Compliance

**Como** auditor **quero** baixar a trilha filtrada**, de modo que** o controle interno receba o mesmo recorte da tela

## História de Usuário: Exportar auditoria

**Como** auditor **quero** exportar atividades ou acessos em planilha ou PDF**, de modo que** o arquivo não misture uma visão com a outra

## Pré-requisitos

- Atribuição de consultar a auditoria
- Filtros já aplicados na trilha, quando o recorte não for o padrão

## Dependências

**Passadas**

- Consulta da trilha de auditoria

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Exportar a trilha

**Caminho no menu:** Auditoria > Trilha de auditoria, na ação de exportar

*[Print da tela](screenshots/gestao-auditoria.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Mesmo recorte da tela**
**Dado que** o auditor filtrou a trilha
**Quando** exporta
**Então** o arquivo traz esse recorte, na visão escolhida, e a exportação fica registrada como dado pessoal

**Cenário 2: Formato não informado na exportação direta**
**Dado que** o formato não foi informado
**Quando** a exportação direta ocorre
**Então** o arquivo sai em CSV

**Cenário 3: Volume acima do limite**
**Dado que** o recorte passa do limite de entrega imediata
**Quando** o auditor pede o arquivo
**Então** o sistema não entrega o arquivo inteiro na hora e gera o mesmo recorte para download posterior

**Cenário 4: Formato não habilitado**
**Dado que** o formato pedido não está habilitado
**Quando** o auditor exporta
**Então** nenhum arquivo vazio é gerado e a exportação não conclui

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
