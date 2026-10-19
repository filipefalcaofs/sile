# HU-039 — Aplicar Quadro 10

## Épica
**EP05 — Motor de Regras da LOUOS**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar, na versão vigente do Quadro 10, se o grupo de uso é permitido, permitido condicionado ou proibido na zona identificada.

## História de Usuário
**Como** sistema,  
**quero** ler a permissão da zona para o grupo enquadrado,  
**para** consolidar o parecer sem inventar permissão quando a zona ou a regra faltam.

## Atores e permissões
- O motor não tem tela própria. A manutenção das linhas é o rascunho do domínio `louos_quadro10`, permissão `manter-louos`, publicação por quatro olhos.
- O cadastro de zonas (`/gestao/louos/zonas`) é a lista que a publicação do rascunho confere: zona citada no quadro precisa existir e estar ativa.

## Fluxo evidenciado
1. O motor não busca a zona. Usa `territory.zona` já identificado.
2. Zona ausente ou com status diferente de `identificado`: dimensão `indisponivel`, sem ler `louos_quadro10_permissoes`. O motivo é o da zona ou o texto de zona pendente.
3. Enquadramento não identificado e sem sinalização CNLU com grupo: dimensão `indisponivel`. Com CNLU e grupo, o Quadro 10 é consultado mesmo assim.
4. Sem versão do domínio: `nao_encontrado`.
5. A busca usa a grafia oficial da zona e, se não achar, o nome original. Não inventa zona.
6. Com subgrupo, prefere a linha daquele subgrupo. Se existem linhas de subgrupo e a do subgrupo não existe, não cai na regra geral do grupo. Sem subgrupo, a regra geral do grupo só vale se os subgrupos não tiverem permissões diferentes. Caso contrário, `nao_encontrado` e o Quadro 10 não é aplicado.
7. Permissões do enum: `permitido`, `permitido_condicionado`, `proibido`. A importação também aceita os sinais S, N, S(c), S(a) e S(b).
8. Se a linha tem observação `(a)` ou `(b)` e a permissão não é proibida, a allow-list RT-05-A ou RT-05-B decide: fora da lista a permissão vira `proibido`; sem CODLOG e sem bairro a dimensão fica `indisponivel`; na lista a permissão da linha permanece.
9. O motivo identificado diz o grupo, a permissão e a zona. Não descreve outras zonas.

## Regras de negócio evidenciadas
- Proibido no consolidado vira `nao_permitido`.
- Permitido condicionado entra como condicionante de zona e pode levar o consolidado a `permitido_com_condicoes`.
- Zona ou regra ausente deixam o consolidado `pendente`. O motor não escolhe permitido nem não permitido nesse caso.
- Observação lida do campo `observacao` da linha. O código reconhece `(a)` e `(b)`. Outro texto não dispara allow-list.

## Validações
- Zona, grupo e subgrupo vazios não geram permissão.
- Subgrupos com permissões divergentes impedem usar uma única regra de grupo.

## Critérios de aceite
**Dado** que a zona está identificada e existe linha do grupo na versão vigente,  
**Quando** o Quadro 10 é aplicado,  
**Então** a dimensão fica `identificado` com permissão, referência de condicionante e base legal da linha.

**Dado** que a zona não está identificada,  
**Quando** o motor enquadra,  
**Então** o Quadro 10 fica `indisponivel` e a tabela não é consultada.

**Dado** que a linha tem observação (a) ou (b) e o CODLOG ou o bairro está fora da allow-list,  
**Quando** o Quadro 10 é aplicado,  
**Então** a permissão devolvida é `proibido`.

## Divergências da redação anterior
A redação anterior não descrevia a degradação sem zona, a precedência do subgrupo nem as observações (a) e (b).

## Evidências
- `LouosEnquadramentoService::enquadrarQuadro10`
- `LouosQuadro10Permissao`, `Quadro10Permissao`
- `Quadro10Zona`
- `TerritorialRuleService::observacaoQuadro10Permite`
- `RuleDomain::LouosQuadro10`

## Não evidenciado
- Consulta do Quadro 10 a partir de zona digitada livremente, sem a dimensão territorial identificada.
- Observação (c) do Quadro 10. O motor só reconhece `(a)` e `(b)`.
