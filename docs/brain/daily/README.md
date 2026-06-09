# daily/ — Notas diárias (opcional)

Notas curtas de cada dia trabalhado no projeto. Útil para reconstruir contexto após pausas, retrospectivas e hand-offs.

## Convenção

- Um arquivo por dia: `YYYY-MM-DD.md`.
- Use o template em `_template.md`.
- Não precisa ser longo — bullets bastam.
- Se o dia não teve trabalho relevante, pule. Sem culpa.

## Como criar

```bash
cp docs/brain/daily/_template.md "docs/brain/daily/$(date +%F).md"
```

## Quando consultar

- Voltando de férias / pausa longa.
- Descobrindo "quando exatamente isso quebrou?".
- Preparando uma retrospectiva.
- Onboarding (mostrar o último mês de daily como contexto vivo).

## Promoção para outras pastas

- Aprendizado relevante? → mover para `docs/brain/3-resources/`.
- Decisão arquitetural surgida? → virar ADR em `docs/brain/3-resources/adrs/`.
- Item de trabalho persistente? → virar projeto em `docs/brain/1-projects/`.
