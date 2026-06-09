# Projetos Laravel com repo-padrao

> **Linguagem:** PHP  
> **Clone direto:** [`repos/repo-padrao-laravel-vue`](../../repos/repo-padrao-laravel-vue) · [`-react`](../../repos/repo-padrao-laravel-react) · [`-svelte`](../../repos/repo-padrao-laravel-svelte) — stack já aplicada, sem scripts pós-clone.

Guia para aplicações [Laravel 12/13](https://laravel.com/docs/13.x) com [Laravel Boost](https://laravel.com/docs/13.x/ai).

## Visão geral

O repo-padrao combina:

1. **Disciplina universal** — TDD, Superpowers, pt-BR, commits convencionais (`AGENTS.md`)
2. **Stack Laravel** — rules e skills baseadas no Laravel Boost
3. **MCP laravel-boost** — introspecção da app, docs versionadas, schema de banco

### Guidelines vs Skills

| | Guidelines (rules `.mdc`) | Skills |
|---|---|---|
| Quando carregam | Ao abrir arquivos PHP/Blade/routes/tests | Sob demanda, por domínio |
| Escopo | Convenções base Laravel | Padrões detalhados (queries, segurança, Pest…) |
| Onde | `.cursor/rules/laravel-*.mdc` | `.cursor/skills/*/SKILL.md` |

## Fluxo recomendado

### 1. Bootstrap

```bash
git clone https://github.com/filipefalcaofs/repo-padrao.git meu-laravel
cd meu-laravel
rm -rf .git && git init
composer create-project laravel/laravel .
# ou: copiar app Laravel existente para a raiz
```

### 2. Escolher adapter Inertia (obrigatório)

O template traz **Vue, React e Svelte disponíveis**, mas instala **apenas um** por projeto:

```bash
./stacks/laravel/scripts/choose-inertia-adapter.sh
```

| Opção | Adapter npm | Skill instalada |
|---|---|---|
| 1 (padrão) | `@inertiajs/vue3` | `inertia-vue-development` |
| 2 | `@inertiajs/react` | `inertia-react-development` |
| 3 | `@inertiajs/svelte` | `inertia-svelte-development` |

Não interativo:

```bash
./stacks/laravel/scripts/choose-inertia-adapter.sh --adapter vue --yes
```

Salva `.laravel-stack.json` na raiz. Para trocar depois, rode o script novamente.

Aplicar stack completo em projeto Laravel existente (rules + skills + escolha Inertia):

```bash
bash stacks/laravel/scripts/apply-stack.sh . --adapter vue
```

### 3. .gitignore Laravel

```bash
cat docs/templates-linguagem/gitignore-php-laravel.txt >> .gitignore
```

### 4. Laravel Boost

```bash
composer require laravel/boost --dev
php artisan boost:install
```

No instalador, selecionar **Cursor**, **guidelines**, **skills** e **MCP**.

Se preferir config manual:

```bash
cp stacks/laravel/.mcp.json.example .mcp.json
cp stacks/laravel/boost.json.example boost.json
```

Habilitar `laravel-boost` em **MCP Settings** no Cursor.

### 5. Segundo cérebro

Registrar stack no ADR:

```bash
cp docs/brain/3-resources/adrs/0000-template.md \
   docs/brain/3-resources/adrs/0001-stack-laravel.md
```

### 6. Verificação

```bash
php artisan test --compact
php artisan route:list
```

No Cursor, confirmar que `laravel-boost` aparece em MCP Settings e que as rules `laravel-core`, `laravel-boost` e `laravel-inertia` estão ativas ao editar arquivos `.php` ou `resources/js/`.

## Stack frontend: Inertia.js

Adapter definido em `.laravel-stack.json`. Pacotes Composer (todos):

```bash
composer require inertiajs/inertia-laravel
php artisan inertia:middleware
```

Pacotes npm conforme adapter escolhido:

| Adapter | Comando |
|---|---|
| Vue | `npm install @inertiajs/vue3 vue @vitejs/plugin-vue` |
| React | `npm install @inertiajs/react react react-dom @vitejs/plugin-react` |
| Svelte | `npm install @inertiajs/svelte svelte @sveltejs/vite-plugin-svelte` |

## Skills incluídas

### Backend (sempre)

| Skill | Ativar quando |
|---|---|
| `laravel-best-practices` | Controllers, models, migrations, jobs, queries, segurança |
| `laravel-boost` | Debug, schema, rotas, logs, `search-docs` |
| `pest-testing` | Qualquer trabalho em `tests/` |

### Frontend Inertia (uma por projeto)

Após `choose-inertia-adapter.sh`, só a skill do adapter escolhido fica em `.cursor/skills/`:

| Adapter | Skill |
|---|---|
| Vue | `inertia-vue-development` |
| React | `inertia-react-development` |
| Svelte | `inertia-svelte-development` |

Rule `laravel-inertia.mdc` lê `.laravel-stack.json` para saber qual skill usar.

### Catálogo completo (em `stacks/laravel/.cursor/skills/`)

Vue, React e Svelte ficam no stack fonte; o script copia apenas a escolhida.

### Opcionais

| Skill | Ativar quando |
|---|---|
| `livewire-development` | Componentes Livewire (não Inertia) |
| `tailwindcss-development` | Classes Tailwind em Blade/Vue/React |

Invocar explicitamente quando relevante: `/inertia-vue-development`, `/laravel-best-practices`, `/pest-testing`.

## Customização

### Guidelines próprias

Criar em `.ai/guidelines/` — incluídas no próximo `boost:install` ou `boost:update`.

### Override de guideline Boost

Mesmo path do Boost. Exemplo: `.ai/guidelines/inertia-vue/2/forms.blade.php`.

### Skills próprias

`.ai/skills/minha-skill/SKILL.md` → `php artisan boost:update`.

## Projetos sem Laravel

Remova o stack Laravel para evitar ruído:

```bash
rm .cursor/rules/laravel-*.mdc
rm -rf .cursor/skills/laravel-best-practices \
       .cursor/skills/laravel-boost \
       .cursor/skills/pest-testing \
       .cursor/skills/inertia-vue-development \
       .cursor/skills/inertia-react-development \
       .cursor/skills/inertia-svelte-development
rm .cursor/rules/laravel-inertia.mdc
```

## Referências

- [stacks/laravel/README.md](../../stacks/laravel/README.md)
- [Laravel AI — 13.x](https://laravel.com/docs/13.x/ai)
- [Laravel Boost — 13.x](https://laravel.com/docs/13.x/boost)
- [COMO_USAR.md](../COMO_USAR.md)
