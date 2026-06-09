# Inertia (Laravel)

- Inertia cria SPAs client-side sem a complexidade de um SPA tradicional, reutilizando padrões server-side do Laravel.
- Pages em `resources/js/Pages/` (ou path em `vite.config.js`). Rotas usam `Inertia::render()` — não Blade para páginas da SPA.
- Sempre usar `search-docs` antes de implementar (docs versionadas).

## Skills por adapter

| Pacote | Skill |
|---|---|
| `@inertiajs/vue3` | `inertia-vue-development` |
| `@inertiajs/react` | `inertia-react-development` |
| `@inertiajs/svelte` | `inertia-svelte-development` |

Ativar a skill do adapter **instalado no projeto**. Stack padrão repo-padrao: **Vue 3**.

## Inertia v2 (padrão)

- Deferred props, infinite scroll (`WhenVisible`), polling, prefetch.
- Com deferred props, incluir skeleton/loading state.
- Formulários: `<Form>` (v2.1+) ou `useForm` — ver skill do framework.
