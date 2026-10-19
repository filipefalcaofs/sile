# HU-044 — Consolidar resultado da análise

## Épica
**EP05 — Motor de Regras da LOUOS**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Combinar enquadramento, Quadro 10 e Quadro 11A num único resultado: permitido, permitido com condições, não permitido ou pendente.

## História de Usuário
**Como** sistema,  
**quero** um veredito único do motor da LOUOS,  
**para** a consulta e o fluxo expresso propagarem a mesma decisão, sem recalcular.

## Atores e permissões
- O consolidado é produzido por `LouosEnquadramentoService::consolidar`. Quem consulta a viabilidade ou decide o expresso só lê o resultado.
- Não há permissão para “forçar” o consolidado na tela do motor.

## Fluxo evidenciado
A ordem no código é esta:

1. Ramo da planilha com `bloqueia`: `nao_permitido`, sem condicionantes, motivo do ramo.
2. Enquadramento não identificado, mas sinalizado CNLU com grupo: se o Quadro 10 está `proibido`, `nao_permitido`; senão `pendente` com o motivo de CNLU.
3. Enquadramento não identificado: `pendente`.
4. Quadro 10 `indisponivel` ou `nao_encontrado`: `pendente`, com o motivo da dimensão.
5. Quadro 10 `proibido`: `nao_permitido`.
6. Quadro 11A com token `nao`: `nao_permitido`, motivo de via vedada.
7. Quadro 11A com token `r` ou texto de análise da CNLU: `pendente`.
8. Com condicionante incidente: `permitido_com_condicoes`. Sem ela: `permitido`.

O retorno traz `resultado`, `fundamentacao`, `condicionantes` e `motivo`. Pendente sai com condicionantes vazias.

A consulta de viabilidade copia esse resultado. Não escolhe outro veredito. Sem zona identificada, acrescenta aviso; o pendente já veio do motor.

No expresso, `nao_permitido` é emitido como indeferimento locacional antes do gate de alto risco. `pendente` vai à análise, sem deferir nem indeferir por omissão.

## Regras de negócio evidenciadas
- O que não pode ser decidido vira `pendente`.
- Condicionante incidente não proíbe.
- Quadro 11A indisponível não aplica o veredito de via. Se o Quadro 10 identificou permissão, o fluxo segue para as condicionantes.
- Os valores do enum são `permitido`, `permitido_com_condicoes`, `nao_permitido` e `pendente`.

## Validações
- Não há validação de formulário nesta consolidação. Ela consome as dimensões já resolvidas.

## Critérios de aceite
**Dado** que o enquadramento e o Quadro 10 identificam uso permitido, a via não veda e não há condicionante incidente,  
**Quando** o motor consolida,  
**Então** o resultado é `permitido`.

**Dado** que o Quadro 10 está indisponível ou o ramo não foi resolvido,  
**Quando** o motor consolida,  
**Então** o resultado é `pendente` e nenhuma permissão é inventada.

**Dado** que o Quadro 10 está proibido ou o Quadro 11A contém `nao`,  
**Quando** o motor consolida,  
**Então** o resultado é `nao_permitido`.

## Divergências da redação anterior
A redação anterior não fixava a precedência bloqueio, CNLU, ausência de ramo, zona, proibição, via e condicionante.

## Evidências
- `LouosEnquadramentoService::consolidar`
- `ResultadoViabilidade`
- `ConsultaViabilidadeService` (propaga o consolidado)
- `FluxoExpressoService` (não permitido e pendente)

## Não evidenciado
- Quarto quadro (Quadro 7 ou Quadro 11) participando desta precedência.
- Consolidado permitido quando a zona não foi identificada.
