# 4-archive/ — Archive

**Itens concluídos ou inativos** que ainda têm valor histórico. Quando um projeto termina, uma área é descontinuada ou um recurso ficou obsoleto, ele vem para cá em vez de ser apagado.

## Por que arquivar em vez de deletar

- Auditoria: por que tomamos aquela decisão em 2025?
- Onboarding: pessoas novas conseguem ver projetos passados.
- Reuso: ideia velha que pode renascer com contexto novo.

## Quando arquivar

- Projeto **concluído**: mover de `1-projects/` para cá.
- Projeto **cancelado**: mover de `1-projects/` para cá com nota explicando por que foi cancelado.
- Área **descontinuada**: mover de `2-areas/` para cá.
- Recurso **obsoleto**: mover de `3-resources/` para cá (ex: ADR superseded permanece nos ADRs com status atualizado, mas pesquisas e snippets desatualizados vêm para cá).

## Estrutura

Manter a mesma origem da pasta original como subpasta:

```
4-archive/
├── projects/
│   └── 2025-feature-x.md
├── areas/
│   └── compliance-pci.md
└── resources/
    └── pesquisa-graphql-2024.md
```

## Convenção

Prefixar com o **ano de arquivamento** ajuda a contextualizar: `2026-feature-checkout-v1.md`.

## NÃO arquive

- ADRs aceitos — esses ficam para sempre em `docs/brain/3-resources/adrs/`. Se uma decisão muda, crie ADR novo com status `superseded por NNNN` no antigo.
