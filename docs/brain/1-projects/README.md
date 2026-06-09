# 1-projects/ — Projects

Iniciativas com **objetivo claro** e **prazo definido**. Quando termina, vai para `4-archive/`.

## O que entra aqui

- Features ativas (uma pasta ou nota por feature)
- Sprints/milestones em andamento
- Refactors com escopo fechado
- Spikes técnicos com data de término

## O que NÃO entra

- "Melhorar performance" (sem prazo) → `2-areas/`
- "Documentação geral do sistema de pagamentos" → `3-resources/`
- Ideias soltas → `inbox/`

## Estrutura sugerida

Para cada projeto, criar uma pasta ou um arquivo:

```
1-projects/
├── feature-checkout-v2/
│   ├── README.md          ← visão geral, status, próximos passos
│   ├── decisoes.md        ← decisões locais (não-arquiteturais)
│   └── notas/
└── refactor-auth.md       ← projetos pequenos podem ser arquivo único
```

## Como criar uma entrada

Use o template:

```bash
cp docs/brain/1-projects/_template.md docs/brain/1-projects/<nome-do-projeto>.md
```

Ao concluir, mova aprendizados relevantes para `3-resources/` e arquive em `4-archive/`.
