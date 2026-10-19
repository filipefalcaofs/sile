# HU.140 – PRÉ-ANALISAR PROCESSO PELO MOTOR
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quando o processo é encaminhado à análise, o mesmo motor da solicitação grava a primeira revisão da ficha com o que souber, sem marcar a escolha do analista e sem decidir o processo. Não há botão isolado de pré-analisar. Abrir a ficha também faz essa preparação se ainda não houver revisão utilizável. Quem abre a ficha humana precisa analisar processos.

Se a primeira revisão já existe e não é rascunho vazio, ela é mantida e o motor não roda de novo. Rascunho vazio é substituído. A primeira revisão nasce em rascunho, com o registro do motor, as versões de regra e o resultado por atividade. A sugestão fica guardada para comparar depois. A escolha do analista nasce em branco e o parecer nasce em branco. Condicionantes, vagas e justificativa por atividade vêm preenchidas quando o motor as traz.

Se o motor falha, a primeira revisão fica em modo manual, com o motor indisponível e a ficha vazia, sem resultado inventado. Veredito de localização ainda pendente não esvazia a ficha: entra o que o motor souber, com situação de análise, sem inventar deferimento. A execução fica na auditoria. Duas preparações ao mesmo tempo não duplicam a primeira revisão.

Atualizar o rascunho automático só regrava ficha intocada: rascunho, sem analista, sem parecer e única revisão. Ficha assumida ou preenchida não é substituída. Se o motor está indisponível nessa atualização, a ficha permanece como está. Recalcular de propósito é abrir nova revisão na ficha.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Pré-analisar processo pelo motor](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista **quero** receber a ficha já preenchida com o veredito do motor**, de modo que** eu confirme ou altere sem que a sugestão venha marcada como minha escolha

## História de Usuário: Pré-analisar processo pelo motor

**Como** analista **quero** que a primeira revisão nasça no encaminhamento à análise**, de modo que** falha do motor deixe a ficha manual, sem resultado inventado

## Pré-requisitos

- Processo encaminhado à análise, ou abertura da ficha sem revisão utilizável
- Atribuição de analisar processos, para abrir a ficha humana

## Dependências

**Passadas**

- Encaminhamento à análise técnica

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Primeira revisão da ficha

**Caminho no menu:** Não há item próprio. O efeito aparece na ficha, em Operação > Caixa de entrada, depois do encaminhamento à análise.

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Primeiro encaminhamento**
**Dado que** o processo acaba de ir para a análise e ainda não tem revisão
**Quando** a preparação ocorre
**Então** nasce a primeira revisão com a sugestão do motor e a escolha do analista em branco

**Cenário 2: Encaminhamento repetido**
**Dado que** a primeira revisão já tem conteúdo
**Quando** o processo é encaminhado de novo ou a ficha é reaberta
**Então** a revisão não é duplicada e o motor não roda outra vez

**Cenário 3: Falha do motor**
**Dado que** o motor não consegue preparar a ficha
**Quando** a preparação ocorre
**Então** a revisão fica manual, com o motor indisponível, sem resultado inventado

**Cenário 4: Ficha já preenchida**
**Dado que** o analista já assumiu ou preencheu a ficha
**Quando** uma atualização automática é tentada
**Então** a ficha não é substituída

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
