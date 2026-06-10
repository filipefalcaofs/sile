---
phase: 01-identidade-acesso-e-auditoria-transversal
plan: 03
subsystem: auth
tags: [spatie-permission, rbac, seeds, inertia, react, tailwind, routing, shared-props]

# Dependency graph
requires:
  - phase: 01-01
    provides: spatie/laravel-permission 8.0 instalado com migrations, Fortify com rotas de auth, SecurityHeaders no grupo web
  - phase: 01-02
    provides: 403 auditado via render callbacks (CA-04), RecordActivityAction com detecção de channel por nome de rota
provides:
  - RolesAndPermissionsSeeder idempotente — papéis cidadao/analista/gestor/administrador e permissões acessar-gestao, consultar-acessos-de-qualquer-conta, gerenciar-procuracoes-proprias (guard web)
  - User com HasRoles; UserFactory com states cidadao()/analista()/gestor()/administrador()
  - Rotas segregadas portal (prefix portal, name portal., auth+verified) e gestão (prefix gestao, name gestao., + permission:acessar-gestao)
  - Aliases role/permission do spatie em bootstrap/app.php
  - Landing pública home.tsx; layouts auth/portal/gestão pt-BR; dashboards mínimos portal/dashboard e gestao/dashboard
  - Props compartilhadas auth.user/auth.roles/auth.permissions/flash.status tipadas em SharedProps (resources/js/types/index.d.ts)
  - CA-04 ponta a ponta provado por teste (cidadão em /gestao → 403 + activity_log bloqueado)
affects: [01-04, 01-05, 01-06, 01-07, 01-08, 01-09, fase-2-administracao, fase-10-analise, fase-12-auditoria]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Papéis fixos da fase seedados por firstOrCreate + syncPermissions com forgetCachedPermissions antes e depois"
    - "Factory states por papel via afterCreating(assignRole) — papéis precisam estar seedados antes"
    - "Ambientes segregados por arquivo de rota (portal.php/gestao.php) requeridos em web.php, segregação por middleware de permissão (não por guard)"
    - "Props compartilhadas do Inertia tipadas em SharedProps extends PageProps; layouts consomem via usePage<SharedProps>()"

key-files:
  created:
    - database/seeders/RolesAndPermissionsSeeder.php
    - routes/portal.php
    - routes/gestao.php
    - app/Http/Controllers/Portal/DashboardController.php
    - app/Http/Controllers/Gestao/DashboardController.php
    - resources/js/pages/home.tsx
    - resources/js/layouts/auth-layout.tsx
    - resources/js/layouts/portal-layout.tsx
    - resources/js/layouts/gestao-layout.tsx
    - resources/js/pages/portal/dashboard.tsx
    - resources/js/pages/gestao/dashboard.tsx
    - resources/js/types/index.d.ts
    - tests/Feature/Authorization/RolesAndPermissionsSeederTest.php
    - tests/Feature/Routing/EnvironmentAccessTest.php
  modified:
    - app/Models/User.php
    - database/seeders/DatabaseSeeder.php
    - database/factories/UserFactory.php
    - bootstrap/app.php
    - routes/web.php
    - app/Http/Middleware/HandleInertiaRequests.php
    - .gitignore

key-decisions:
  - "Papéis travados: cidadao/analista/gestor/administrador num único guard web; contador e procurador não são papéis (poder vem de dados — procuração/vínculo)"
  - "SharedProps extends PageProps (@inertiajs/core) para satisfazer o constraint de usePage<T>() do adapter React v3"
  - "Welcome.tsx removida; landing é home.tsx (component 'home', kebab/minúsculo, case-sensitive no CI)"

patterns-established:
  - "Segregação portal × gestão: permission:acessar-gestao na gestão, nomes portal.*/gestao.* alimentam o channel da auditoria"
  - "Testes de ambiente seedam RolesAndPermissionsSeeder no setUp e usam factory states por papel"

# Metrics
duration: 8min
completed: 2026-06-10
---

# Fase 1 Plano 03: Perfis, Permissões e Ambientes Portal × Gestão — Resumo

**RBAC da Fase 1 seedado (4 papéis, 3 permissões, guard web) com factory states, dois ambientes segregados por permissão (portal.*/gestao.*) com landing pública, layouts e dashboards Inertia pt-BR, props auth/flash compartilhadas e tipadas, e CA-04 provado ponta a ponta (403 do cidadão na gestão auditado em activity_log)**

## Performance

- **Duração:** 8 min
- **Início:** 2026-06-10T01:52:29Z
- **Término:** 2026-06-10T02:00:30Z
- **Tasks:** 2 (ambas TDD Red-Green)
- **Arquivos modificados:** 21

## Realizações

- `RolesAndPermissionsSeeder` idempotente cria `cidadao`, `analista`, `gestor`, `administrador` e as permissões `acessar-gestao`, `consultar-acessos-de-qualquer-conta`, `gerenciar-procuracoes-proprias` (com `forgetCachedPermissions` antes e depois — Pitfall 2 da pesquisa); `DatabaseSeeder` deixou de criar o Test User do esqueleto
- `User` com `HasRoles`; `UserFactory` ganhou states `cidadao()`, `analista()`, `gestor()`, `administrador()` — base de todos os testes de autorização dos planos seguintes
- Ambientes segregados: `/portal` (auth+verified) e `/gestao` (auth+verified+`permission:acessar-gestao`), nomes `portal.dashboard`/`gestao.dashboard` confirmados por `route:list` — a detecção de `channel` da auditoria (01-02) passa a distinguir gestão automaticamente
- CA-04 ponta a ponta: cidadão em `/gestao` recebe 403 e a tentativa fica em `activity_log` com `log_name=seguranca`, `event=acesso-negado`, `result=bloqueado` — provado por request HTTP real no teste
- Landing pública `home` (substituiu `Welcome`), layouts `auth-layout` (pronto para 01-04/05/06), `portal-layout` e `gestao-layout` com header, nome do usuário, botão Sair (POST /logout) e flash status; dashboards mínimos navegáveis
- Props compartilhadas `auth.user`, `auth.roles`, `auth.permissions` e `flash.status` no `HandleInertiaRequests`, tipadas em `SharedProps` (`resources/js/types/index.d.ts`)
- Suíte completa verde: 32 testes / 104 asserções (11 novos); `npm run typecheck` e `npm run build` exit 0; pint limpo

## Estado das rotas (route:list)

```
GET|HEAD portal .. portal.dashboard › Portal\DashboardController
GET|HEAD gestao .. gestao.dashboard › Gestao\DashboardController
```

Grupo portal usa `['auth', 'verified']` — `lgpd.accepted` entra no plano 01-05 conforme previsto; bloqueio de não-verificados (MustVerifyEmail) é CA do plano 01-04.

## Commits por Task

Cada fase TDD foi commitada atomicamente:

1. **Task 1 RED: teste de papéis, permissões e seeds** - `fad4017` (test)
2. **Task 1 GREEN: seeder + HasRoles + factory states + DatabaseSeeder** - `8a445ac` (feat)
3. **Task 2 RED: teste dos ambientes portal e gestão** - `3290a34` (test)
4. **Task 2 GREEN: rotas segregadas + landing + layouts + dashboards + props** - `5a42f5c` (feat)

_REFACTOR não gerou commits próprios: pint só reordenou imports de bootstrap/app.php antes do commit GREEN da Task 2._

## Arquivos Criados/Modificados

- `database/seeders/RolesAndPermissionsSeeder.php` - Seed idempotente de papéis e permissões da Fase 1
- `database/seeders/DatabaseSeeder.php` - Chama RolesAndPermissionsSeeder (Test User removido)
- `database/factories/UserFactory.php` - States por papel via afterCreating(assignRole)
- `app/Models/User.php` - Trait HasRoles
- `bootstrap/app.php` - Aliases role/permission (SecurityHeaders e callbacks de 403 preservados)
- `routes/web.php` - Landing `home` + require portal.php/gestao.php
- `routes/portal.php` / `routes/gestao.php` - Ambientes segregados
- `app/Http/Controllers/{Portal,Gestao}/DashboardController.php` - Controllers invocáveis
- `app/Http/Middleware/HandleInertiaRequests.php` - Props auth/flash compartilhadas
- `resources/js/pages/home.tsx` - Landing pública pt-BR (Entrar / Criar conta)
- `resources/js/layouts/{auth,portal,gestao}-layout.tsx` - Layouts pt-BR com dark mode
- `resources/js/pages/{portal,gestao}/dashboard.tsx` - Dashboards mínimos
- `resources/js/types/index.d.ts` - SharedProps tipando props compartilhadas
- `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` (4 testes) / `tests/Feature/Routing/EnvironmentAccessTest.php` (7 testes)
- `.gitignore` - Ignora public/fonts-manifest.dev.json (artefato de build)

## Decisões Tomadas

- `SharedProps` estende `PageProps` de `@inertiajs/core` — satisfaz o constraint genérico de `usePage<T>()` no adapter React v3; import type-only (apagado no bundle, alias `@/` só no tsconfig)
- Papéis conforme decisão travada da pesquisa: contador/procurador NÃO viraram papéis; um único guard `web`
- `Welcome.tsx` removida no mesmo commit que criou `home.tsx` — sem página órfã

## Desvios do Plano

### Correções automáticas

**1. [Rule 3 - Higiene de repositório] Artefato de build não ignorado**

- **Encontrado em:** Task 2 (após `npm run build`)
- **Problema:** O plugin de fontes do laravel-vite-plugin gera `public/fonts-manifest.dev.json`, que não estava no `.gitignore` — sujaria o `git status` de todos os planos seguintes com risco de commit acidental de artefato de build
- **Correção:** Linha `/public/fonts-manifest.dev.json` adicionada ao bloco Laravel do `.gitignore`
- **Arquivos:** .gitignore
- **Verificação:** `git status` limpo após build
- **Commit:** 5a42f5c

---

**Total de desvios:** 1 correção automática (higiene)
**Impacto no plano:** Nenhum scope creep; plano executado conforme escrito em todo o resto.

## Problemas Encontrados

Nenhum.

## Portões de Autenticação

Nenhum — execução 100% local (artisan, phpunit, npm, git).

## Configuração Manual Necessária

Nenhuma — sem serviços externos neste plano.

## Prontidão para o Próximo Plano

- 01-04 (cadastro + confirmação de e-mail) tem tudo: papel `cidadao` seedado, factory states, `auth-layout` para as telas, rotas Fortify ativas, auditoria transversal
- `verified` no portal ainda deixa passar usuários não verificados até o `User implements MustVerifyEmail` do 01-04 (comportamento previsto e documentado no plano)
- `lgpd.accepted` entra no 01-05 e atualizará os grupos de rota e os testes de ambiente
- Canal `gestao` da auditoria agora detectável (rotas `gestao.*` existem)

---
*Fase: 01-identidade-acesso-e-auditoria-transversal*
*Concluído em: 2026-06-10*
