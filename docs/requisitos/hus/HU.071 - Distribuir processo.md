# HU.071 – DISTRIBUIR PROCESSO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Quem analisa ou quem distribui vê a caixa do setor, com os processos em análise dos seus setores. Coordenador, Subcoordenador, Chefe de setor, Apoio e Administrador distribuem. Analista Servidor e Analista Terceirizado não distribuem. Quem só distribui não assume a análise.

As abas separam o que está para distribuir, o que já foi distribuído e o que está para conclusão. A distribuição, de um processo ou em lote, grava o responsável, a data e o setor, recalcula o prazo da etapa e registra cada processo. Falha de um item do lote não cancela os demais. O destinatário precisa analisar processos. Processo em vistoria vai para quem preenche a ficha de vistoria.

A redistribuição troca o responsável e, quando couber, o setor, registra o analista anterior e não recalcula o prazo. Não vale depois da conclusão, em convite ou em vistoria. Processo fora da caixa ou analista sem vínculo com o setor de destino não é gravado.

Apoio e Administrador, que distribuem para qualquer setor, enxergam todos os setores e podem entregar a analista de outro setor. O processo passa para o setor de destino. Sem essa atribuição, o analista precisa ser do setor do processo e o processo permanece nele. O sistema ainda não oferece distribuição automática por carga. A distribuição inteligente existe na caixa, e quem recebe continua sendo definido a partir da alocação informada.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Distribuir processo](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** a chefia **quero** distribuir e redistribuir processos da caixa do setor**, de modo que** o responsável fique definido sem tirar o processo do setor, salvo quando a entrega for para outro setor

## História de Usuário: Distribuir processo

**Como** gestor, chefia ou apoio que distribui processos **quero** atribuir um ou vários processos a um analista**, de modo que** o prazo seja recalculado na distribuição e preservado na redistribuição

## Pré-requisitos

- Processo em análise na caixa do setor
- Quem distribui processos, para distribuir e redistribuir
- Destinatário que analisa processos, ou que preenche vistoria quando o processo está em vistoria

## Dependências

**Passadas**

- HU.069 — Encaminhar para análise técnica

**Futuras**

- HU.072 — Assumir análise, que é outra ação e não exige distribuir

---

## Interface de Usuário

### Tela 1 — Distribuir processo

**Caminho no menu:** Operação > Caixa do setor

*[Print da tela](screenshots/gestao-caixa.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Quem só analisa**
**Dado que** a pessoa analisa processos e não distribui
**Quando** abre a caixa e tenta distribuir
**Então** pode ver a caixa, mas distribuir e redistribuir são recusados

**Cenário 2: Distribuição**
**Dado que** quem distribui escolhe um analista do setor
**Quando** confirma
**Então** o responsável é gravado, o prazo da etapa é recalculado e cada processo fica registrado

**Cenário 3: Lote com falha**
**Dado que** a distribuição é em lote e um item não pode ser gravado
**Quando** o lote é processado
**Então** a falha fica naquele item e os demais seguem

**Cenário 4: Redistribuição**
**Dado que** a situação operacional permite redistribuir
**Quando** o responsável é trocado
**Então** o analista anterior fica registrado e o prazo não é recalculado; fora das situações permitidas, a redistribuição é recusada

**Cenário 5: Entrega a outro setor**
**Dado que** Apoio ou Administrador entrega a analista de outro setor
**Quando** confirma
**Então** o processo passa para o setor desse analista

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
