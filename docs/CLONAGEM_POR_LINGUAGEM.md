# Clonagem por linguagem e IDE

Escolha **duas coisas** antes de clonar:

1. **Linguagem / framework** (stack do projeto)
2. **IDE com IA** — `cursor`, `claude` ou `codex`

Cada repositório traz **somente** os arquivos da stack escolhida **e** da IDE escolhida — nada de `.cursor/` se você usa Claude, etc.

> Não clone o meta-repo [`repo-padrao`](https://github.com/filipefalcaofs/repo-padrao).

## Convenção de nome

```
repo-padrao-{stack}-{cursor|claude|codex}
```

| IDE | Suffix | O que vem no repo |
|---|---|---|
| **Cursor** | `-cursor` | `.cursor/rules/`, `.cursor/skills/`, Superpowers plugin, `.mcp.json` (Laravel/ABP) |
| **Claude Code** | `-claude` | `CLAUDE.md`, `.claude/skills/` (stack + Superpowers) |
| **Codex** | `-codex` | `AGENTS.md`, `.codex/skills/` (stack + Superpowers) |

Todos incluem `docs/brain/` (segundo cérebro) e rules universais **na forma da IDE escolhida**.

---

## Passo 1 — Escolha a stack

| Linguagem | Variantes (base do nome) | Guia |
|---|---|---|
| **Agnóstico** | `repo-padrao-base` | — |
| **PHP** | `repo-padrao-laravel-vue` · `-react` · `-svelte` | [LARAVEL.md](stacks/LARAVEL.md) |
| **Java** | `repo-padrao-jhipster-angular` · `-react` · `-vue` | [JHIPSTER.md](stacks/JHIPSTER.md) |
| **.NET (C#)** | `repo-padrao-abp-angular` · `-react` · `-blazor` · `-mvc` | [ABP.md](stacks/ABP.md) |
| **Python** | `repo-padrao-django` | [DJANGO.md](stacks/DJANGO.md) |
| **TypeScript** | `repo-padrao-nestjs` | [NESTJS.md](stacks/NESTJS.md) |
| **Go** | `repo-padrao-go-api` | [GO.md](stacks/GO.md) |

## Passo 2 — Escolha a IDE

Acrescente o suffix ao nome da stack:

```text
repo-padrao-laravel-vue  +  -cursor  →  repo-padrao-laravel-vue-cursor
repo-padrao-django       +  -claude  →  repo-padrao-django-claude
repo-padrao-base         +  -codex   →  repo-padrao-base-codex
```

---

## Exemplos de clone

### Cursor + Laravel + Inertia Vue

```bash
git clone https://github.com/filipefalcaofs/repo-padrao-laravel-vue-cursor.git meu-app
cd meu-app && rm -rf .git && git init
composer create-project laravel/laravel .
composer require laravel/boost --dev && php artisan boost:install
# Habilitar laravel-boost no MCP Settings (Cursor)
```

### Claude Code + Django

```bash
git clone https://github.com/filipefalcaofs/repo-padrao-django-claude.git meu-app
cd meu-app && rm -rf .git && git init
python -m venv .venv && source .venv/bin/activate
pip install django djangorestframework pytest-django
django-admin startproject config .
```

### Codex + Go API

```bash
git clone https://github.com/filipefalcaofs/repo-padrao-go-api-codex.git meu-api
cd meu-api && rm -rf .git && git init
go mod init github.com/seu-usuario/meu-api
```

---

## PHP — Laravel + Inertia

| Frontend | Cursor | Claude | Codex |
|---|---|---|---|
| Vue | [laravel-vue-cursor](https://github.com/filipefalcaofs/repo-padrao-laravel-vue-cursor) | [laravel-vue-claude](https://github.com/filipefalcaofs/repo-padrao-laravel-vue-claude) | [laravel-vue-codex](https://github.com/filipefalcaofs/repo-padrao-laravel-vue-codex) |
| React | [laravel-react-cursor](https://github.com/filipefalcaofs/repo-padrao-laravel-react-cursor) | [laravel-react-claude](https://github.com/filipefalcaofs/repo-padrao-laravel-react-claude) | [laravel-react-codex](https://github.com/filipefalcaofs/repo-padrao-laravel-react-codex) |
| Svelte | [laravel-svelte-cursor](https://github.com/filipefalcaofs/repo-padrao-laravel-svelte-cursor) | [laravel-svelte-claude](https://github.com/filipefalcaofs/repo-padrao-laravel-svelte-claude) | [laravel-svelte-codex](https://github.com/filipefalcaofs/repo-padrao-laravel-svelte-codex) |

---

## Java — JHipster

| Frontend | Cursor | Claude | Codex |
|---|---|---|---|
| Angular | [jhipster-angular-cursor](https://github.com/filipefalcaofs/repo-padrao-jhipster-angular-cursor) | […-claude](https://github.com/filipefalcaofs/repo-padrao-jhipster-angular-claude) | […-codex](https://github.com/filipefalcaofs/repo-padrao-jhipster-angular-codex) |
| React | [jhipster-react-cursor](https://github.com/filipefalcaofs/repo-padrao-jhipster-react-cursor) | […-claude](https://github.com/filipefalcaofs/repo-padrao-jhipster-react-claude) | […-codex](https://github.com/filipefalcaofs/repo-padrao-jhipster-react-codex) |
| Vue | [jhipster-vue-cursor](https://github.com/filipefalcaofs/repo-padrao-jhipster-vue-cursor) | […-claude](https://github.com/filipefalcaofs/repo-padrao-jhipster-vue-claude) | […-codex](https://github.com/filipefalcaofs/repo-padrao-jhipster-vue-codex) |

---

## .NET (C#) — ABP

| UI | Cursor | Claude | Codex |
|---|---|---|---|
| Angular | [abp-angular-cursor](https://github.com/filipefalcaofs/repo-padrao-abp-angular-cursor) | […-claude](https://github.com/filipefalcaofs/repo-padrao-abp-angular-claude) | […-codex](https://github.com/filipefalcaofs/repo-padrao-abp-angular-codex) |
| React | [abp-react-cursor](https://github.com/filipefalcaofs/repo-padrao-abp-react-cursor) | […-claude](https://github.com/filipefalcaofs/repo-padrao-abp-react-claude) | […-codex](https://github.com/filipefalcaofs/repo-padrao-abp-react-codex) |
| Blazor | [abp-blazor-cursor](https://github.com/filipefalcaofs/repo-padrao-abp-blazor-cursor) | […-claude](https://github.com/filipefalcaofs/repo-padrao-abp-blazor-claude) | […-codex](https://github.com/filipefalcaofs/repo-padrao-abp-blazor-codex) |
| MVC | [abp-mvc-cursor](https://github.com/filipefalcaofs/repo-padrao-abp-mvc-cursor) | […-claude](https://github.com/filipefalcaofs/repo-padrao-abp-mvc-claude) | […-codex](https://github.com/filipefalcaofs/repo-padrao-abp-mvc-codex) |

---

## Stacks secundárias

| Stack | Cursor | Claude | Codex |
|---|---|---|---|
| Django | [django-cursor](https://github.com/filipefalcaofs/repo-padrao-django-cursor) | [django-claude](https://github.com/filipefalcaofs/repo-padrao-django-claude) | [django-codex](https://github.com/filipefalcaofs/repo-padrao-django-codex) |
| NestJS | [nestjs-cursor](https://github.com/filipefalcaofs/repo-padrao-nestjs-cursor) | [nestjs-claude](https://github.com/filipefalcaofs/repo-padrao-nestjs-claude) | [nestjs-codex](https://github.com/filipefalcaofs/repo-padrao-nestjs-codex) |
| Go API | [go-api-cursor](https://github.com/filipefalcaofs/repo-padrao-go-api-cursor) | [go-api-claude](https://github.com/filipefalcaofs/repo-padrao-go-api-claude) | [go-api-codex](https://github.com/filipefalcaofs/repo-padrao-go-api-codex) |

---

## Agnóstico (sem stack)

| Cursor | Claude | Codex |
|---|---|---|
| [base-cursor](https://github.com/filipefalcaofs/repo-padrao-base-cursor) | [base-claude](https://github.com/filipefalcaofs/repo-padrao-base-claude) | [base-codex](https://github.com/filipefalcaofs/repo-padrao-base-codex) |

---

## Após clonar

1. `rm -rf .git && git init` (ou **Use this template** no GitHub)
2. Crie o projeto da stack na raiz (comandos nos guias em `docs/stacks/`)
3. Registre em `docs/brain/3-resources/adrs/0001-escolha-de-stack.md`

Catálogo no factory: [repos/README.md no meta-repo](https://github.com/filipefalcaofs/repo-padrao/blob/main/repos/README.md).
