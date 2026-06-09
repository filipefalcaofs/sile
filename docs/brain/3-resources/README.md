# 3-resources/ — Resources

**Conhecimento reutilizável**: ADRs, pesquisas, snippets, links, documentação técnica externa relevante.

## Subestrutura

| Pasta | Para que serve |
|---|---|
| `adrs/` | Architecture Decision Records (decisões arquiteturais com contexto) |
| `pesquisas/` | Notas de pesquisa técnica (libs, padrões, comparações) |
| `snippets/` | Trechos úteis de código, comandos, configs |

## Quando usar resources

- Algo que vai ser **consultado mais de uma vez** ao longo do projeto.
- Conhecimento que **não muda com frequência**.
- Conteúdo que **independe** de um projeto ou área específica.

## Quando NÃO usar

- Está no escopo de um projeto ativo? → mora em `1-projects/<projeto>/notas/`
- É um padrão recorrente da área? → mora em `2-areas/<area>/`
- É captura recente sem destilação? → fica em `inbox/` até ser processado

## Regra dos ADRs

**Toda decisão arquitetural relevante vira um ADR.** Inclui escolha de framework, padrão de autenticação, formato de mensageria, decisão de single-page vs SSR, etc. Não vira ADR: escolhas locais de implementação que não afetam o resto do sistema.

Comece toda decisão pelo template em `adrs/0000-template.md`.
