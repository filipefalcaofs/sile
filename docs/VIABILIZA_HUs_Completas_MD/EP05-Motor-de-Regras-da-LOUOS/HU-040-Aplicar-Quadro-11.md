# HU-040 — Aplicar Quadro 11

## Épica
**EP05 — Motor de Regras da LOUOS**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Aplicar um Quadro 11 distinto do Quadro 11A. Esse quadro não existe no motor.

## História de Usuário
**Como** sistema,  
**quero** aplicar as condições do Quadro 11,  
**para** condicionar a instalação pela via.

O código não faz essa distinção. A condição pela via implementada é a do Quadro 11A (HU-041).

## Atores e permissões
Não há permissão, rota ou domínio de regra para um Quadro 11 separado.

## Fluxo evidenciado
1. `LouosEnquadramentoService::enquadrar` chama `enquadrarCondicoesVia` uma vez, com `RuleDomain::LouosQuadro11a`.
2. `RuleDomain` tem `louos_quadro10` e `louos_quadro11a`. Não tem `louos_quadro11`.
3. A tabela lida é `LouosQuadro11CondicaoVia`, ligada à versão do 11A. O importador se chama `LouosQuadro11ImportService`, mas grava nessa tabela do 11A.
4. O comentário do método fala em “Quadros 11 e 11A” e diz que o domínio distinguiria o quadro. Na chamada real só o 11A é passado.
5. O cadastro de classes de via (`/gestao/louos/vias`) valida as classes citadas no rascunho do Quadro 11A.

## Regras de negócio evidenciadas
- Nenhuma regra de permissão, vedação ou condição é lida de um Quadro 11 próprio.
- O veredito pela via (Não, R, Sim e texto de análise da CNLU) está implementado sobre o resultado do Quadro 11A, documentado na HU-041.

## Validações
Não há validação de entrada exclusiva do Quadro 11.

## Critérios de aceite
**Dado** que o motor enquadra uma atividade,  
**Quando** as dimensões devolvidas são lidas,  
**Então** existem enquadramento, Quadro 10 e Quadro 11A. Não existe dimensão Quadro 11.

**Dado** que se procura o domínio de versão `louos_quadro11`,  
**Quando** o enum `RuleDomain` é lido,  
**Então** o valor não existe.

## Divergências da redação anterior
A redação anterior tratava o Quadro 11 como quadro aplicado pelo motor. O código aplica somente o Quadro 11A.

## Evidências
- `LouosEnquadramentoService::enquadrar` (única chamada, domínio `LouosQuadro11a`)
- `RuleDomain` (sem `louos_quadro11`)
- `LouosQuadro11ImportService` e `LouosQuadro11CondicaoVia`

## Não evidenciado
- Tabela, versão, tela ou veredito próprios do Quadro 11.
- Diferença de efeito entre Quadro 11 e Quadro 11A. Só o 11A produz `classe_via` e `condicoes`.
