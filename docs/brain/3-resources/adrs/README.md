# adrs/ — Architecture Decision Records

Registros de decisões arquiteturais no formato **Michael Nygard**. Um ADR captura **uma decisão**, **o contexto** em que foi tomada, **as alternativas** consideradas e **as consequências** esperadas.

## Por que ADRs

- Quem chega depois entende **por que** o código está assim.
- Decisões revertidas ficam rastreáveis (ADR antigo recebe status `superseded`).
- Reduz "arqueologia em git blame" para entender escolhas.
- Para agentes de IA, é a **fonte de verdade** sobre intenção arquitetural.

## Quando criar um ADR

Crie um ADR quando a decisão:

- Afeta múltiplos módulos ou times.
- É difícil de reverter depois (ex: escolha de banco, padrão de auth).
- Foi tomada após considerar 2+ alternativas.
- Estabelece um padrão a ser seguido daqui pra frente.

## Convenções

- Numeração sequencial: `0001-`, `0002-`, `0003-`...
- Título em kebab-case curto: `0001-escolha-de-orm.md`.
- Status do ADR: `proposto` → `aceito` → `superseded por NNNN` ou `descontinuado`.
- ADRs são **imutáveis** após `aceito`. Mudou de ideia? Crie um novo ADR que supersede o anterior.

## Como criar

```bash
NEXT=$(ls docs/brain/3-resources/adrs/ | grep -E '^[0-9]{4}-' | tail -1 | cut -d- -f1)
NEXT=$(printf "%04d" $((10#${NEXT:-0} + 1)))
cp docs/brain/3-resources/adrs/0000-template.md "docs/brain/3-resources/adrs/${NEXT}-titulo.md"
```

## Para os agentes de IA

Sempre **leia** os ADRs existentes antes de propor uma decisão arquitetural. Sempre **escreva** um novo ADR após uma decisão arquitetural ter sido tomada. Não repita decisões já registradas.
