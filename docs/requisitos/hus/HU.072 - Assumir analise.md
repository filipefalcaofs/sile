# HU.072 – ASSUMIR ANÁLISE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O analista pega para si um processo da caixa do setor e passa a ser o responsável técnico. Analista Servidor, Analista Terceirizado e as chefias de análise podem assumir. Quem só distribui, como o Apoio, vê a caixa e não assume. O Administrador, no cadastro padrão, também não assume, porque não analisa processos.

Processo sem responsável pode ser assumido. Processo já atribuído a outra pessoa não pode, salvo quando está para conclusão: nesse caso, qualquer analista do setor pode pegá-lo. Assumir recalcula o prazo da etapa e fica registrado. Não é distribuir: não exige a atribuição de distribuir nem usa a lista de destinatários da chefia.

O sistema ainda não oferece assunção por quem só distribui ou por administrador sem a atribuição de analisar.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Assumir análise](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** o analista **quero** assumir um processo da caixa do meu setor**, de modo que** fique registrado que a análise está comigo

## História de Usuário: Assumir análise

**Como** analista que analisa processos **quero** pegar um processo sem responsável**, de modo que** outro analista não o assuma, salvo quando estiver para conclusão

## Pré-requisitos

- Atribuição de analisar processos
- Processo em análise na caixa do setor

## Dependências

**Passadas**

- HU.069 — Encaminhar para análise técnica
- HU.071 — Distribuir processo, que é ação distinta

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Assumir análise

**Caminho no menu:** Operação > Caixa do setor

*[Print da tela](screenshots/gestao-caixa.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Processo sem responsável**
**Dado que** o analista do setor assume um processo sem responsável
**Quando** confirma
**Então** passa a constar como atribuído e a assunção fica registrada, com o prazo da etapa recalculado

**Cenário 2: Processo já atribuído**
**Dado que** outro analista já é o responsável e o processo não está para conclusão
**Quando** um segundo analista tenta assumir
**Então** nada é gravado

**Cenário 3: Para conclusão**
**Dado que** o processo está para conclusão
**Quando** um analista do setor assume
**Então** pode pegá-lo mesmo que já haja responsável

**Cenário 4: Quem só distribui**
**Dado que** a pessoa só distribui processos e não analisa
**Quando** tenta assumir
**Então** a assunção não é concluída

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
