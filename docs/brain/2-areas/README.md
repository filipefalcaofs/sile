# 2-areas/ — Areas

**Responsabilidades contínuas**, sem prazo de término. Áreas existem enquanto o projeto existir.

## O que entra aqui

- Qualidade (testes, cobertura, padrões)
- Segurança (autenticação, segredos, dependências)
- Performance (orçamentos, métricas, otimizações recorrentes)
- Infraestrutura (deploy, monitoramento, alertas)
- Observabilidade (logs, métricas, traces)
- Acessibilidade
- Compliance / LGPD
- DX (developer experience)
- Onboarding de novos integrantes

## O que NÃO entra

- "Adicionar testes ao módulo X" (tem prazo) → `1-projects/`
- "Como funciona o JWT" (referência externa) → `3-resources/`

## Estrutura sugerida

```
2-areas/
├── qualidade/
│   ├── README.md           ← padrões, SLAs, checklists
│   └── checklist-pr.md
├── seguranca.md
└── performance.md
```

## Como criar uma área

```bash
cp docs/brain/2-areas/_template.md docs/brain/2-areas/<nome-da-area>.md
```

Áreas tendem a ser revisadas a cada 1-3 meses para garantir que padrões e SLAs continuam realistas.
