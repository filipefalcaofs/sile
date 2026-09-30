# Login de gestão — central viva

**Data:** 2026-09-30
**Status:** aprovado para implementação
**Base:** `docs/superpowers/specs/2026-09-30-login-alinhamento-optico-design.md` (já implementado)

## Objetivo

Transformar o palco do login de gestão numa central de análise urbana em estado
de espera, sem alterar layout, identidade visual ou estrutura. A narrativa é
CENTRAL EM ESPERA → ATIVAÇÃO → IDENTIFICAÇÃO → AUTORIZAÇÃO → ENTRADA NO
CONSOLE, e cada movimento parece fazer parte do funcionamento da central.

## O que muda

### Central em espera (idle)

A cena ganha atividade constante e discreta, sempre em opacidade baixa e
movimento lento:

- **Varredura**: uma linha vertical fina percorre o palco da esquerda para a
  direita em ciclo de ~9 s, com um gradiente suave. É a central varrendo o
  território.
- **Pontos de análise**: oito pontos pequenos sobre a cidade, em posições
  fixas, que acendem e apagam em sequência (cada um com fase própria). São as
  consultas territoriais sendo processadas.
- **Leituras da central**: a linha de estado alterna entre três textos a cada
  ~4 s — "Central em espera", "Varredura territorial ativa", "Motor de regras
  em sincronia" — mantendo o formato `NN / 06`.
- Os três anéis continuam com a deriva que já existe.

### Ativação e identificação (session / secure / ready)

Quando o operador foca um campo:

- A varredura converge: em vez de percorrer o palco inteiro, ela passa a
  oscilar em torno da região da cena.
- Os pontos de análise mudam de branco para o azul de autorização e se
  alinham aos três anéis.
- O reconhecimento do identificador e a senha iniciada continuam como estão
  (selo, linha do operador, arco).

### Autorização e entrada (validating → docking)

Sem mudança de comportamento. A cidade ao fundo ganha um foco de luz que
acompanha o alinhamento: um brilho radial azul centrado na cena, que cresce
em `authorized` e `preparing` e some no docking.

## O que não muda

- Layout, tipografia, cores, textos e a estrutura do formulário.
- A máquina de estados, os tempos e a transição para o console.
- Abaixo de 1101px, só o formulário.
- `prefers-reduced-motion: reduce`: sem varredura, sem pontos, sem deriva.

## Implementação

Tudo no loop `requestAnimationFrame` existente, sem re-render por frame. Os
elementos novos são divs posicionadas por refs, como os anéis. Nenhuma
dependência nova.
