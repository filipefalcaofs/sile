# HU.081 – APLICAR CONDICIONANTES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O analista lança condicionantes na ficha, no rascunho, para o conjunto do processo e para cada atividade. Ficha finalizada não aceita essa gravação. Quem publica a condicionante na decisão é quem também conclui a análise, no deferimento.

Se a ficha tem condicionante, o consolidado do deferimento é permitido com condições. Sem condicionante, é permitido. Deferimento de sede de escritório virtual, com a ficha marcada e o CNAE que a caracteriza, também sai como permitido com condições, acrescenta o texto de condicionante da sede, trava a inscrição e marca escritório virtual. Indeferimento não usa permitido com condições: o consolidado indeferido é não permitido.

Na renovação, informar que as condicionantes não foram atendidas troca o resultado para indeferido, e o indeferimento continua exigindo justificativa. Desativado, cassado, revogado e indeferimento de sede ativa desvinculam a inscrição. Deferimento não desvincula.

O sistema ainda não oferece cadastro avulso de condicionante depois da decisão, sem nova revisão da ficha, nem conferência de cumprimento de condicionante fora da renovação.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Aplicar condicionantes](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** o analista **quero** lançar condicionantes na ficha**, de modo que** o deferimento saia como permitido com condições quando houver condicionante gravada

## História de Usuário: Aplicar condicionantes

**Como** analista que analisa processos **quero** registrar condicionantes no rascunho da ficha**, de modo que** quem conclui a análise as publique na decisão de deferimento

## Pré-requisitos

- Ficha ainda não finalizada, para gravar
- Quem analisa, para o rascunho
- Quem também conclui a análise, para publicar no deferimento

## Dependências

**Passadas**

- HU.078 — Emitir parecer
- HU.079 — Deferir solicitação

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Aplicar condicionantes

**Caminho no menu:** Operação > Caixa de entrada

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Deferimento com condicionante**
**Dado que** a ficha tem condicionante e quem pode concluir defere
**Quando** a decisão é gravada
**Então** o consolidado fica permitido com condições

**Cenário 2: Deferimento sem condicionante**
**Dado que** a ficha não tem condicionante
**Quando** defere
**Então** o consolidado fica permitido, salvo sede de escritório virtual com o CNAE que a caracteriza

**Cenário 3: Renovação com condicionante não atendida**
**Dado que** a renovação informa que as condicionantes não foram atendidas
**Quando** a decisão é gravada
**Então** o resultado não é deferimento

**Cenário 4: Sem analisar**
**Dado que** a pessoa não analisa processos
**Quando** tenta gravar a ficha
**Então** o sistema recusa

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
