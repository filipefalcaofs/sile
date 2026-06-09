# pesquisas/ — Notas de pesquisa técnica

Investigações sobre bibliotecas, padrões, comparações de soluções, leituras técnicas relevantes ao projeto.

## Quando usar

- Comparação entre 2+ libs/abordagens antes de escolher uma.
- Estudo aprofundado de uma tecnologia (ex: "como WebSockets escalam horizontalmente").
- Resumo de doc técnica externa que será consultada várias vezes.

## Estrutura sugerida

```
pesquisas/
├── 2026-01-comparativo-orm.md
├── 2026-02-estrategias-cache.md
└── nextjs-app-router.md
```

Prefixar com `YYYY-MM-` ajuda a ordenar cronologicamente. Quando uma pesquisa **leva a uma decisão**, ela deve ser **referenciada por um ADR** em `../adrs/`.

## Template mínimo

```markdown
# <Tema>

- **Data:** YYYY-MM-DD
- **Motivação:** <por que estamos pesquisando isso>
- **TL;DR:** <conclusão em 1-2 frases>

## Resumo

<corpo>

## Fontes

- <link 1>
- <link 2>
```
