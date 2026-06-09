# brain — Segundo Cérebro do Projeto

Esta pasta é o **segundo cérebro** do projeto, baseado na metodologia **Building a Second Brain (BASB)** de Tiago Forte. Ela existe para que conhecimento não fique disperso entre commits, threads, slacks e cabeças — tudo que importa mora aqui, organizado para ser encontrado.

## Por que existe

Código mostra **o que** o sistema faz. O `brain/` mostra **por que** ele faz, **quais alternativas** foram descartadas, **o que aprendemos** e **o que ainda está aberto**. Sem isso, cada nova pessoa (humana ou agente de IA) repete decisões já tomadas e refaz pesquisa já feita.

## Os dois pilares: PARA + CODE

### PARA — como organizar (Tiago Forte)

| Pasta | Significado | Quando usar |
|---|---|---|
| `1-projects/` | **Projects** — iniciativas com objetivo claro e prazo | Features ativas, sprints, releases, refactors com escopo definido |
| `2-areas/` | **Areas** — responsabilidades contínuas, sem prazo | Qualidade, segurança, performance, infraestrutura, observabilidade |
| `3-resources/` | **Resources** — referências e conhecimento reutilizável | ADRs, pesquisas, snippets, links, documentação técnica externa |
| `4-archive/` | **Archive** — itens concluídos ou inativos | Projetos finalizados, áreas descontinuadas, recursos obsoletos |

A regra de ouro: **mova itens entre pastas conforme o estado deles muda**. Um projeto vira archive ao terminar. Uma pesquisa que vira responsabilidade contínua vai para areas.

### CODE — como processar

1. **Capture** — capture rápido em `inbox/`. Não pare para organizar agora.
2. **Organize** — em sessões periódicas, mova itens do `inbox/` para a pasta PARA correta.
3. **Distill** — destile a essência: TL;DR no topo, decisões em ADR, aprendizados em notas curtas.
4. **Express** — transforme conhecimento em entregáveis: PRs, docs, releases, posts, ADRs.

## Fluxo do dia a dia

```
[Ideia/leitura/pesquisa]
        ↓
   inbox/<nota-rapida>.md       ← Capture (zero atrito)
        ↓
[Sessão de organização semanal]
        ↓
1-projects/  2-areas/  3-resources/   ← Organize
        ↓
[Trabalho ativo no projeto]
        ↓
3-resources/adrs/NNNN-decisao.md      ← Distill (decisões)
        ↓
PR / commit / release / docs          ← Express
        ↓
4-archive/                            ← Quando concluído
```

## Manutenção

- **Semanal:** processar `inbox/` (esvaziar para 0).
- **Por release:** revisar `1-projects/` e arquivar o que terminou; destilar aprendizados para `3-resources/`.
- **Trimestral:** revisar `2-areas/` (padrões ainda fazem sentido?) e `3-resources/` (algo virou obsoleto?).

## Para os agentes de IA

Antes de qualquer decisão arquitetural, **leia** `docs/brain/3-resources/adrs/`. Após tomar uma decisão arquitetural relevante, **registre** um novo ADR usando o template em `docs/brain/3-resources/adrs/0000-template.md`. Isso vale para Cursor, Claude Code e Codex.

## Convenções de nome

- **ADRs:** `NNNN-titulo-em-kebab-case.md` (numeração sequencial, começando em `0001`).
- **Notas em geral:** `kebab-case.md` (sem espaços, sem acentos no nome de arquivo).
- **Daily notes:** `YYYY-MM-DD.md` em `daily/`.
- **TL;DR no topo** de toda nota com mais de 20 linhas.
