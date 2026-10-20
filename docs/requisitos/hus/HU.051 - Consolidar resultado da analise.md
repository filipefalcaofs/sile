# HU.051 – CONSOLIDAR RESULTADO DA ANÁLISE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O motor combina enquadramento, Quadro 10 e Quadro 11A num único resultado: permitido, permitido com condições, não permitido ou pendente. A consulta de viabilidade copia esse resultado e não escolhe outro. Sem zona identificada, acrescenta aviso; o pendente já veio do motor. Na resposta automática, não permitido é emitido como indeferimento locacional antes da trava de Alto. Pendente vai à análise, sem deferir nem indeferir por omissão. Não há como forçar o resultado na tela do motor. O Quadro 7 e o Quadro 11 não participam desta precedência. Não há resultado permitido quando a zona não foi identificada.

A ordem é esta. Ramo da planilha que bloqueia: não permitido, sem condicionantes. Enquadramento de CNLU com grupo: se o Quadro 10 está proibido, não permitido; senão, pendente. Enquadramento não identificado: pendente. Quadro 10 indisponível ou não encontrado: pendente. Quadro 10 proibido: não permitido. Quadro 11A com "não": não permitido, via vedada. Quadro 11A com "R" ou texto de análise da CNLU: pendente. Com condicionante incidente: permitido com condições. Sem ela: permitido. Pendente sai sem condicionantes. O que não pode ser decidido vira pendente. Condicionante incidente não proíbe. Quadro 11A indisponível não aplica o veredito da via.

---

## Sumário

- [Épico: EP05 — Motor de Regras da LOUOS](#épico)
- [História de Usuário: Consolidar resultado da análise](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP05 — Motor de Regras da LOUOS

**Como** a SEDUR **quero** um veredito único do motor da LOUOS**, de modo que** a consulta e a resposta automática propaguem a mesma decisão

## História de Usuário: Consolidar resultado da análise

**Como** analista **quero** ver um único resultado do parecer locacional**, de modo que** eu saiba se o uso é permitido, permitido com condições, não permitido ou pendente

## Pré-requisitos

- Enquadramento, Quadro 10 e Quadro 11A já resolvidos, cada um com a sua situação
- A consolidação não tem formulário próprio: consome o que o motor já apurou

## Dependências

**Passadas**

- HU-046 — enquadramento da planilha
- HU-047 — Quadro 10
- HU-048 — Quadro 11A
- HU-049 — condicionantes incidentes

**Futuras**

- HU-052 — a fundamentação acompanha este resultado

---

## Interface de Usuário

### Tela 1 — Resultado do parecer

**Caminho no menu:** O resultado aparece em Consultar viabilidade e no processo

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Uso permitido**
**Dado que** o enquadramento e o Quadro 10 identificam uso permitido, a via não veda e não há condicionante incidente
**Quando** o motor consolida
**Então** o resultado é permitido

**Cenário 2: O que não pode ser decidido**
**Dado que** o Quadro 10 está indisponível ou o ramo não foi resolvido
**Quando** o motor consolida
**Então** o resultado é pendente e nenhuma permissão é inventada

**Cenário 3: Uso não permitido**
**Dado que** o Quadro 10 está proibido ou o Quadro 11A contém "não"
**Quando** o motor consolida
**Então** o resultado é não permitido

**Cenário 4: Resposta automática**
**Dado que** o resultado consolidado é não permitido ou pendente
**Quando** a resposta automática avalia o parecer
**Então** não permitido indefere o locacional antes da trava de Alto, e pendente vai à análise sem decidir por omissão

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
