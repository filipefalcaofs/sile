# HU.052 – REGISTRAR FUNDAMENTAÇÃO LEGAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O parecer cita a base legal só do que o motor chegou a aplicar, mais o motivo quando o resultado fica pendente. Enquadramento identificado inclui o texto-base da LOUOS e o subgrupo, ou "enquadramento de uso" se o subgrupo vier vazio. Quadro 10 identificado inclui a base legal da linha, quando preenchida, e o texto da LOUOS com "Quadro 10". Quadro 11A identificado faz o mesmo com "Quadro 11A". Base legal vazia da linha não gera item extra; o rótulo do quadro ainda entra se a dimensão foi identificada.

Dimensão que não está identificada não contribui com quadro. Se o resultado está pendente, o motivo da pendência entra na lista e o quadro que ficou indisponível não é citado como regra aplicada. Entradas repetidas são removidas. A lista fica registrada junto com o CNAE, a área, a situação do enquadramento, o resultado e as versões. Nas regras territoriais que indeferem, o fundamento é o da própria regra, não esta lista. Não há citação do Quadro 7 nem do Quadro 11. A fundamentação sanitária não entra neste parecer. Os textos-base são os cadastrados; esta função não confere, à parte, a vigência da lei.

---

## Sumário

- [Épico: EP05 — Motor de Regras da LOUOS](#épico)
- [História de Usuário: Registrar fundamentação legal](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP05 — Motor de Regras da LOUOS

**Como** a SEDUR **quero** citar só a base legal do que foi aplicado**, de modo que** o parecer não mencione quadro que não chegou a ser usado

## História de Usuário: Registrar fundamentação legal

**Como** analista **quero** ler a fundamentação do parecer locacional**, de modo que** eu veja o enquadramento, os quadros aplicados e, na pendência, o motivo em vez da regra faltante

## Pré-requisitos

- Parecer já consolidado
- Textos-base da LOUOS cadastrados e, quando houver, base legal na linha da versão usada

## Dependências

**Passadas**

- HU-051 — o resultado consolidado
- HU-046, HU-047 e HU-048 — dimensões que podem entrar na lista

**Futuras**

- A manutenção dos textos-base fica em Configuração > Textos decisórios, fora desta função

---

## Interface de Usuário

### Tela 1 — Fundamentação do parecer

**Caminho no menu:** O resultado aparece em Consultar viabilidade e no processo

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Tudo identificado**
**Dado que** enquadramento, Quadro 10 e Quadro 11A estão identificados
**Quando** a fundamentação é montada
**Então** a lista cita o enquadramento, o Quadro 10, o Quadro 11A e as bases legais não vazias das linhas

**Cenário 2: Pendência sem zona**
**Dado que** o resultado fica pendente porque a zona não foi identificada
**Quando** a fundamentação é montada
**Então** o motivo da pendência entra na lista e o Quadro 10 não é citado como aplicado

**Cenário 3: Indeferimento por regra de lugar**
**Dado que** uma regra territorial indefere o processo
**Quando** o texto do indeferimento é montado
**Então** o fundamento usado é o da própria regra, e não a lista do parecer consolidado

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
