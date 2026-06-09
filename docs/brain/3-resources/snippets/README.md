# snippets/ — Trechos úteis

Comandos, blocos de código, configs e receitas que valem a pena ter à mão.

## Quando usar

- Receita que você pesquisou uma vez e vai usar de novo (ex: comando docker para reiniciar o stack inteiro).
- Bloco de código padrão para uma tarefa recorrente (ex: setup de teste com mock de auth).
- Workaround para um bug conhecido de uma dependência.

## Estrutura sugerida

```
snippets/
├── docker-comandos-uteis.md
├── git-recovery.md
├── sql-queries-debug.md
└── ci-github-actions.md
```

## Template

```markdown
# <Nome do snippet>

**Quando usar:** <contexto em uma frase>

\`\`\`<linguagem>
<código>
\`\`\`

**Notas:**
- <gotcha 1>
- <gotcha 2>
```

Snippet que vira padrão da casa deve ser promovido para uma área (`2-areas/`) ou virar template em algum lugar do projeto.
