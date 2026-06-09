# Manutenção do factory

Este documento é para quem mantém o meta-repo [`repo-padrao`](https://github.com/filipefalcaofs/repo-padrao) — **não** para quem inicia um projeto de aplicação.

## Fluxo padrão

1. Editar a stack em `stacks/<nome>/` (rules, skills, scripts).
2. Regenerar os standalone: `bash scripts/build-standalone-repos.sh --clean`
3. Commit no meta-repo.
4. Publicar no GitHub: `bash scripts/push-standalone-repos.sh`

## Scripts

| Script | Função |
|---|---|
| `scripts/build-standalone-repos.sh` | Gera/atualiza pastas em `repos/` |
| `scripts/build-standalone-repos.sh --clean` | Limpa `repos/repo-padrao-*` antes de gerar |
| `scripts/build-standalone-repos.sh repo-padrao-laravel-vue-cursor` | Gera um template específico (stack + IDE) |
| `scripts/push-standalone-repos.sh` | Init/commit/push dos 42 repos no GitHub |

Após `--clean`, o histórico git local de cada standalone é recriado — o script de push faz fallback com `git push --force` quando necessário.

## Stacks fonte

| Pasta | Linguagem | Script apply |
|---|---|---|
| `stacks/laravel/` | PHP | `apply-stack.sh` + `choose-inertia-adapter.sh` |
| `stacks/jhipster/` | Java | `apply-stack.sh` + `choose-frontend-framework.sh` |
| `stacks/abp/` | .NET (C#) | `apply-stack.sh` + `choose-ui-framework.sh` |
| `stacks/django/` | Python | `apply-stack.sh` |
| `stacks/nestjs/` | TypeScript | `apply-stack.sh` |
| `stacks/go-api/` | Go | `apply-stack.sh` |

## Rules upstream

ABP — sincronizar rules oficiais do [cursor-abp-plugin](https://github.com/abpframework/cursor-abp-plugin):

```bash
bash stacks/abp/scripts/sync-upstream-rules.sh
```

Laravel — conteúdo baseado em Laravel Boost; ver `stacks/laravel/README.md`.

## Pasta `repos/`

Cópias geradas dos **42 templates** (14 stacks × 3 IDEs). Nome: `repo-padrao-{stack}-{cursor|claude|codex}`.

Desenvolvedores devem clonar os repos GitHub, não copiar de `repos/` manualmente.

## Checklist antes de publicar

- [ ] `bash scripts/build-standalone-repos.sh --clean` sem erros
- [ ] Commit no meta-repo
- [ ] `bash scripts/push-standalone-repos.sh`
- [ ] Spot-check de um template (rules, Superpowers, `docs/CLONAGEM_POR_LINGUAGEM.md`)
