# Portal do cidadão — serviços claros e de fácil acesso — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Todo serviço do portal ganha entrada visível no menu, no painel e na landing, alimentados por um catálogo único.

**Architecture:** O novo `resources/js/navigation/portal-nav.ts` (TS puro, testado no Vitest) é a fonte única de verdade, no mesmo padrão de `gestao-nav.ts`. Ele alimenta a sidebar (`portal-layout.tsx`), o painel e a landing. No backend, a única mudança é o filtro `status` em `SolicitacaoController@index`, para os indicadores do painel abrirem a lista filtrada. A consulta de viabilidade escolhe o shell (portal ou público) conforme o usuário logado.

**Tech Stack:** Laravel 13, Inertia v3 + React 19, Tailwind v4, Vitest 4 (`environment: node`), PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-10-13-portal-servicos-navegacao-design.md`

## Global Constraints

- Grupos da sidebar do portal: exatamente `inicio` → `servicos` → `conta`, nessa ordem.
- Os itens de menu não dependem de toggle. As páginas já degradam de forma comunicada. Nenhum shared prop novo.
- O termo de domínio "convite" se mantém (`AnalysisStatus::EmConvite`). Só se acrescenta explicação em linguagem simples.
- Sem Ctrl+K no portal. Os wizards de solicitação e de renovação não mudam.
- A gestão (`gestao-layout.tsx`, `gestao-nav.ts`) não muda de comportamento.
- Comentários e textos de UI em português, no estilo dos arquivos vizinhos. Sem marca de IA em código, commit ou PR.
- PHP: rodar `vendor/bin/pint --dirty --format agent` antes de cada commit que toque PHP.
- Não commitar arquivos alheios que já estão sujos no working tree (`.cursor/...publicar-softwell-semit*`, `tests/Feature/Email/EmailServerSeederTest.php`, `docs/contagem-pf/*`).

## Review Focus

1. **`?status=` inválido ou de outro formato** (`?status[]=x`, `?status=EM_ANALISE`): deve ser ignorado e listar tudo, nunca dar 500. Teste na Task 3.
2. **O filtro não pode vazar processos de outro requerente:** `?status=em_analise` lista só os do requerente efetivo. Teste na Task 3.
3. **A busca e a paginação precisam preservar o `status`** (o `useServerTable` reenvia `initialFilters`): ao digitar na busca com o filtro ativo, o filtro continua. Teste na Task 3 (backend com `search` + `status` combinados).
4. **A consulta acessada por anônimo continua pública** depois do layout dinâmico (sem auth, sem redirect para login). Teste na Task 5.
5. **Um item de serviço no menu sem ação correspondente no painel** (regressão futura de quem editar o catálogo). Coberto pela invariante de cobertura na Task 1.

---

## File Structure

| Arquivo | Ação | Responsabilidade |
|---|---|---|
| `resources/js/navigation/portal-nav.ts` | Create | Catálogo: grupos da sidebar, ações de serviço, hrefs dos indicadores, invariantes |
| `resources/js/navigation/portal-nav.test.ts` | Create | Testes do catálogo |
| `resources/js/navigation/portal-nav-icons.tsx` | Create | Chave de ícone → componente; monta `SidebarGroup[]` do portal |
| `resources/js/layouts/portal-layout.tsx` | Modify | Usa o catálogo; passa `logoHref` |
| `resources/js/components/app/app-shell.tsx` | Modify | Prop opcional `logoHref` |
| `resources/js/components/app/app-sidebar.tsx` | Modify | Logo usa `logoHref ?? homeHref` |
| `resources/js/components/app/sidebar-active.test.ts` | Modify | Casos do portal |
| `.cursor/rules/menu-navegacao.mdc` | Modify | Seção do portal aponta para `portal-nav.ts` |
| `app/Http/Controllers/Portal/SolicitacaoController.php` | Modify | Filtro `status` no `index` |
| `tests/Feature/Solicitacao/FiltroStatusSolicitacoesTest.php` | Create | Testes do filtro |
| `resources/js/pages/portal/solicitacoes/index.tsx` | Modify | Chip "Filtrando por" e repasse do `status` ao `useServerTable` |
| `resources/js/pages/portal/dashboard.tsx` | Modify | Nova ordem do painel e ações do catálogo |
| `resources/js/pages/portal/viabilidade/shell-da-consulta.ts` | Create | Função pura `shellDaConsulta` |
| `resources/js/pages/portal/viabilidade/shell-da-consulta.test.ts` | Create | Teste da função |
| `resources/js/pages/portal/viabilidade/consulta.tsx` | Modify | Layout dinâmico e link para o histórico |
| `tests/Feature/Viabilidade/ConsultaViabilidadePaginaTest.php` | Modify | Caso logado |
| `resources/js/pages/home.tsx` | Modify | Cards de `acoesPublicas()` e CTA para quem está logado |

---

### Task 0: Branch

- [ ] **Step 1: Criar a branch a partir da main sem levar as mudanças alheias**

```bash
git stash push -u -m "wip-alheio-antes-portal-nav"
git switch main
git pull --ff-only
git switch -c feat/portal-servicos-navegacao
git stash pop
```

Esperado: a branch nova tem os mesmos arquivos sujos de antes, que **não** serão commitados (veja Global Constraints). Se o `stash pop` der conflito, pare e avise o usuário.

---

### Task 1: Catálogo `portal-nav.ts`

**Files:**
- Create: `resources/js/navigation/portal-nav.ts`
- Test: `resources/js/navigation/portal-nav.test.ts`

**Interfaces:**
- Produces:
  - `type PortalNavIcon = 'grid' | 'bell' | 'search' | 'file' | 'check-circle' | 'list' | 'group' | 'user' | 'shield'`
  - `interface PortalNavItem { name: string; href: string; icon: PortalNavIcon }`
  - `interface PortalNavGroup { id: 'inicio' | 'servicos' | 'conta'; label: string; items: PortalNavItem[] }`
  - `interface PortalAcao { titulo: string; descricao: string; href: string; icon: PortalNavIcon; publica: boolean }`
  - `const PORTAL_NAV_GROUPS: PortalNavGroup[]`
  - `const PORTAL_ACOES: PortalAcao[]`
  - `function acoesPublicas(): PortalAcao[]`
  - `const FILTROS_SOLICITACOES: { readonly emAnalise: string; readonly convites: string; readonly todas: string }`
  - `function assertPortalNavHealth(groups?: readonly PortalNavGroup[], acoes?: readonly PortalAcao[]): string[]`

- [ ] **Step 1: Escrever o teste que falha**

`resources/js/navigation/portal-nav.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import {
    acoesPublicas,
    assertPortalNavHealth,
    FILTROS_SOLICITACOES,
    PORTAL_ACOES,
    PORTAL_NAV_GROUPS,
    type PortalAcao,
    type PortalNavGroup,
} from './portal-nav';

const hrefsDoGrupo = (id: string) =>
    PORTAL_NAV_GROUPS.find((group) => group.id === id)?.items.map((item) => item.href) ?? [];

describe('Catálogo de navegação do portal do cidadão', () => {
    it('mantém a ordem fixa dos grupos', () => {
        expect(PORTAL_NAV_GROUPS.map((group) => group.id)).toEqual(['inicio', 'servicos', 'conta']);
    });

    it('passa nas invariantes de organização', () => {
        expect(assertPortalNavHealth()).toEqual([]);
    });

    it('lista todos os serviços do cidadão no menu', () => {
        expect(hrefsDoGrupo('servicos')).toEqual([
            '/portal/viabilidade',
            '/portal/solicitacoes',
            '/portal/renovacao',
            '/portal/empresas',
            '/portal/procuracoes',
        ]);
    });

    it('põe painel e notificações em Início, e dados e acessos em Minha conta', () => {
        expect(hrefsDoGrupo('inicio')).toEqual(['/portal/painel', '/portal/notificacoes']);
        expect(hrefsDoGrupo('conta')).toEqual(['/portal/conta/perfil', '/portal/acessos']);
    });

    it('oferece no painel uma ação para cada serviço do menu', () => {
        for (const href of hrefsDoGrupo('servicos')) {
            const coberto = PORTAL_ACOES.some((acao) => acao.href === href || acao.href.startsWith(`${href}/`));

            expect(coberto, `sem ação para ${href}`).toBe(true);
        }
    });

    it('mostra na landing só as ações públicas, todas com link', () => {
        const publicas = acoesPublicas();

        expect(publicas.map((acao) => acao.href)).toEqual([
            '/portal/viabilidade',
            '/portal/solicitacoes/nova',
            '/portal/renovacao',
            '/portal/procuracoes',
        ]);
        expect(publicas.every((acao) => acao.href.startsWith('/portal/'))).toBe(true);
    });

    it('aponta os indicadores do painel para a lista já filtrada', () => {
        expect(FILTROS_SOLICITACOES.emAnalise).toBe('/portal/solicitacoes?status=em_analise');
        expect(FILTROS_SOLICITACOES.convites).toBe('/portal/solicitacoes?status=em_pendencia');
        expect(FILTROS_SOLICITACOES.todas).toBe('/portal/solicitacoes');
    });

    it('acusa serviço do menu sem ação no painel', () => {
        const semEmpresas: PortalAcao[] = PORTAL_ACOES.filter((acao) => acao.href !== '/portal/empresas');

        expect(assertPortalNavHealth(PORTAL_NAV_GROUPS, semEmpresas)).toContain(
            'Serviço "Minhas empresas" não tem ação no painel.',
        );
    });

    it('acusa grupo de item único, href duplicado e ícone repetido', () => {
        const quebrado: PortalNavGroup[] = [
            { id: 'inicio', label: 'Início', items: [{ name: 'Meu painel', href: '/portal/painel', icon: 'grid' }] },
            {
                id: 'servicos',
                label: 'Serviços',
                items: [
                    { name: 'A', href: '/portal/a', icon: 'file' },
                    { name: 'B', href: '/portal/a', icon: 'file' },
                ],
            },
            { id: 'conta', label: 'Minha conta', items: [] },
        ];

        const erros = assertPortalNavHealth(quebrado, []);

        expect(erros).toContain('Grupo "Início" tem um único item — absorva em um grupo existente.');
        expect(erros).toContain('Hrefs duplicados no menu: /portal/a.');
        expect(erros).toContain('Ícones repetidos no menu: file.');
    });
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `npx vitest run resources/js/navigation/portal-nav.test.ts`
Esperado: FAIL com "Failed to resolve import './portal-nav'".

- [ ] **Step 3: Implementar**

`resources/js/navigation/portal-nav.ts`:

```ts
/**
 * Catálogo único do portal do cidadão. Fonte de verdade da sidebar, do
 * "O que você quer fazer?" do painel e dos serviços da landing.
 *
 * Antes de incluir ou mover item: ler `.cursor/rules/menu-navegacao.mdc`.
 */

export type PortalNavIcon = 'grid' | 'bell' | 'search' | 'file' | 'check-circle' | 'list' | 'group' | 'user' | 'shield';

export interface PortalNavItem {
    name: string;
    href: string;
    icon: PortalNavIcon;
}

export interface PortalNavGroup {
    id: 'inicio' | 'servicos' | 'conta';
    label: string;
    items: PortalNavItem[];
}

/** Ação de serviço exibida como card (painel e, quando `publica`, landing). */
export interface PortalAcao {
    titulo: string;
    descricao: string;
    href: string;
    icon: PortalNavIcon;
    publica: boolean;
}

export const PORTAL_NAV_GROUPS: PortalNavGroup[] = [
    {
        id: 'inicio',
        label: 'Início',
        items: [
            { name: 'Meu painel', href: '/portal/painel', icon: 'grid' },
            { name: 'Notificações', href: '/portal/notificacoes', icon: 'bell' },
        ],
    },
    {
        id: 'servicos',
        label: 'Serviços',
        items: [
            { name: 'Consultar viabilidade', href: '/portal/viabilidade', icon: 'search' },
            { name: 'Minhas solicitações', href: '/portal/solicitacoes', icon: 'file' },
            { name: 'Renovar viabilidade', href: '/portal/renovacao', icon: 'check-circle' },
            { name: 'Minhas empresas', href: '/portal/empresas', icon: 'list' },
            { name: 'Procurações', href: '/portal/procuracoes', icon: 'group' },
        ],
    },
    {
        id: 'conta',
        label: 'Minha conta',
        items: [
            { name: 'Meus dados', href: '/portal/conta/perfil', icon: 'user' },
            { name: 'Histórico de acessos', href: '/portal/acessos', icon: 'shield' },
        ],
    },
];

export const PORTAL_ACOES: PortalAcao[] = [
    {
        titulo: 'Consultar viabilidade',
        descricao: 'Veja se a atividade pode funcionar no endereço, antes de fazer o pedido.',
        href: '/portal/viabilidade',
        icon: 'search',
        publica: true,
    },
    {
        titulo: 'Solicitar viabilidade',
        descricao:
            'Protocole o pedido e acompanhe a análise. Atividades de baixo risco têm resposta automática.',
        href: '/portal/solicitacoes/nova',
        icon: 'file',
        publica: true,
    },
    {
        titulo: 'Renovar viabilidade',
        descricao: 'Prorrogue uma viabilidade provisória emitida pelo portal.',
        href: '/portal/renovacao',
        icon: 'check-circle',
        publica: true,
    },
    {
        titulo: 'Gerenciar empresas',
        descricao: 'Cadastre suas empresas e mantenha as atividades (CNAEs) atualizadas.',
        href: '/portal/empresas',
        icon: 'list',
        publica: false,
    },
    {
        titulo: 'Procurações',
        descricao: 'Autorize alguém a agir em seu nome ou atue por quem autorizou você.',
        href: '/portal/procuracoes',
        icon: 'group',
        publica: true,
    },
];

/** Destinos dos indicadores do painel: a lista de solicitações já filtrada. */
export const FILTROS_SOLICITACOES = {
    emAnalise: '/portal/solicitacoes?status=em_analise',
    convites: '/portal/solicitacoes?status=em_pendencia',
    todas: '/portal/solicitacoes',
} as const;

export function acoesPublicas(): PortalAcao[] {
    return PORTAL_ACOES.filter((acao) => acao.publica);
}

/** Invariantes do catálogo. Usado pelos testes para impedir regressão. */
export function assertPortalNavHealth(
    groups: readonly PortalNavGroup[] = PORTAL_NAV_GROUPS,
    acoes: readonly PortalAcao[] = PORTAL_ACOES,
): string[] {
    const erros: string[] = [];

    const hrefs = groups.flatMap((group) => group.items.map((item) => item.href));
    const duplicados = hrefs.filter((href, index) => hrefs.indexOf(href) !== index);

    if (duplicados.length > 0) {
        erros.push(`Hrefs duplicados no menu: ${[...new Set(duplicados)].join(', ')}.`);
    }

    const icones = groups.flatMap((group) => group.items.map((item) => item.icon));
    const iconesRepetidos = icones.filter((icon, index) => icones.indexOf(icon) !== index);

    if (iconesRepetidos.length > 0) {
        erros.push(`Ícones repetidos no menu: ${[...new Set(iconesRepetidos)].join(', ')}.`);
    }

    for (const group of groups) {
        if (group.items.length === 1) {
            erros.push(`Grupo "${group.label}" tem um único item — absorva em um grupo existente.`);
        }
    }

    const servicos = groups.find((group) => group.id === 'servicos')?.items ?? [];

    for (const item of servicos) {
        const coberto = acoes.some((acao) => acao.href === item.href || acao.href.startsWith(`${item.href}/`));

        if (!coberto) {
            erros.push(`Serviço "${item.name}" não tem ação no painel.`);
        }
    }

    for (const acao of acoes) {
        if (!acao.href.startsWith('/portal/')) {
            erros.push(`Ação "${acao.titulo}" aponta para fora do portal.`);
        }
    }

    return erros;
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `npx vitest run resources/js/navigation/portal-nav.test.ts`
Esperado: PASS (9 testes).

- [ ] **Step 5: Commit**

```bash
git add resources/js/navigation/portal-nav.ts resources/js/navigation/portal-nav.test.ts
git commit -m "feat: catálogo único de navegação do portal do cidadão"
```

---

### Task 2: Sidebar do portal lê o catálogo e o logo leva ao painel

**Files:**
- Create: `resources/js/navigation/portal-nav-icons.tsx`
- Modify: `resources/js/layouts/portal-layout.tsx:1-37`
- Modify: `resources/js/components/app/app-shell.tsx` (interface `AppShellProps`, `ShellContent`)
- Modify: `resources/js/components/app/app-sidebar.tsx` (interface `AppSidebarProps`, destructuring, `<Link href={homeHref}>` na linha ~165)
- Modify: `resources/js/components/app/sidebar-active.test.ts`
- Modify: `.cursor/rules/menu-navegacao.mdc` (seção "Portal do cidadão" e linha 14)

**Interfaces:**
- Consumes: `PORTAL_NAV_GROUPS`, `PortalNavIcon` (Task 1)
- Produces:
  - `const PORTAL_NAV_ICONS: Record<PortalNavIcon, ComponentType<SVGProps<SVGSVGElement>>>`
  - `function portalSidebarGroups(): SidebarGroup[]`
  - Prop `logoHref?: string` em `AppShell` e `AppSidebar`

- [ ] **Step 1: Fixar com testes o comportamento ativo da sidebar no portal**

Acrescente ao fim de `resources/js/components/app/sidebar-active.test.ts` (antes do `});` final do arquivo, como novo `describe` no nível de cima):

```ts
describe('resolveActiveHref no portal do cidadão', () => {
    const portalHrefs = [
        '/portal/painel',
        '/portal/notificacoes',
        '/portal/viabilidade',
        '/portal/solicitacoes',
        '/portal/renovacao',
        '/portal/empresas',
        '/portal/procuracoes',
        '/portal/conta/perfil',
        '/portal/acessos',
    ];

    it('acende Consultar viabilidade no histórico de consultas', () => {
        expect(resolveActiveHref('/portal/viabilidade/historico', portalHrefs, '/portal')).toBe('/portal/viabilidade');
    });

    it('acende Minhas solicitações no wizard de nova solicitação', () => {
        expect(resolveActiveHref('/portal/solicitacoes/nova', portalHrefs, '/portal')).toBe('/portal/solicitacoes');
    });

    it('acende Minhas empresas no detalhe da empresa', () => {
        expect(resolveActiveHref('/portal/empresas/42', portalHrefs, '/portal')).toBe('/portal/empresas');
    });

    it('não acende nada na landing pública', () => {
        expect(resolveActiveHref('/portal', portalHrefs, '/portal')).toBeNull();
    });
});
```

Run: `npx vitest run resources/js/components/app/sidebar-active.test.ts`
Esperado: PASS. São testes de caracterização: a função já resolve esses casos, e o teste impede que o novo catálogo os quebre.

- [ ] **Step 2: Criar o mapa de ícones e o montador de grupos**

`resources/js/navigation/portal-nav-icons.tsx`:

```tsx
import type { ComponentType, SVGProps } from 'react';
import type { SidebarGroup } from '@/components/app/app-sidebar';
import {
    BellIcon,
    CheckCircleIcon,
    FileIcon,
    GridIcon,
    GroupIcon,
    ListIcon,
    SearchIcon,
    ShieldIcon,
    UserCircleIcon,
} from '@/components/icons';
import { PORTAL_NAV_GROUPS, type PortalNavIcon } from '@/navigation/portal-nav';

export const PORTAL_NAV_ICONS: Record<PortalNavIcon, ComponentType<SVGProps<SVGSVGElement>>> = {
    grid: GridIcon,
    bell: BellIcon,
    search: SearchIcon,
    file: FileIcon,
    'check-circle': CheckCircleIcon,
    list: ListIcon,
    group: GroupIcon,
    user: UserCircleIcon,
    shield: ShieldIcon,
};

/** Grupos da sidebar do portal montados a partir do catálogo único. */
export function portalSidebarGroups(): SidebarGroup[] {
    return PORTAL_NAV_GROUPS.map((group) => ({
        label: group.label,
        items: group.items.map((item) => {
            const Icon = PORTAL_NAV_ICONS[item.icon];

            return { name: item.name, href: item.href, icon: <Icon /> };
        }),
    }));
}
```

Se `npm run typecheck` reclamar que algum ícone não aceita `SVGProps` (eles usam `IconProps`), troque o tipo do `Record` para `ComponentType<{ className?: string }>`. O uso em `dashboard.tsx` e `home.tsx` só passa `className` e `aria-hidden`.

- [ ] **Step 3: Adicionar a prop `logoHref` no AppShell e no AppSidebar**

Em `app-shell.tsx`, na interface `AppShellProps`, logo depois de `homeHref: string;`:

```ts
    /** Destino do logo da sidebar; padrão `homeHref`. */
    logoHref?: string;
```

Em `ShellContent`, acrescente `logoHref,` na desestruturação dos parâmetros e repasse ao sidebar:

```tsx
            <AppSidebar
                groups={groups}
                homeHref={homeHref}
                logoHref={logoHref}
                subtitle={subtitle}
                variant={variant}
                collapsibleGroups={collapsibleGroups}
            />
```

Em `app-sidebar.tsx`, na interface `AppSidebarProps`, depois de `homeHref: string;`:

```ts
    /** Destino do logo; padrão `homeHref`. */
    logoHref?: string;
```

Acrescente `logoHref,` na desestruturação de `AppSidebar` e troque o link do logo:

```tsx
                <Link href={logoHref ?? homeHref}>
```

- [ ] **Step 4: Ligar o PortalLayout ao catálogo**

Em `resources/js/layouts/portal-layout.tsx`, substitua os imports de `SidebarGroup` e dos ícones e a constante `groups` (linhas 4-30) por:

```tsx
import { AlertIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import { ThemeProvider } from '@/contexts/theme-context';
import { portalSidebarGroups } from '@/navigation/portal-nav-icons';
import type { SharedProps } from '@/types';

interface PortalLayoutProps {
    children: ReactNode;
}

const groups = portalSidebarGroups();
```

Remova os imports duplicados de `Alert`, `ThemeProvider` e `SharedProps` que ficarem repetidos. Na linha do `AppShell`:

```tsx
            <AppShell
                groups={groups}
                homeHref="/portal"
                logoHref="/portal/painel"
                accountHref="/portal/conta/perfil"
                subtitle="Portal do Cidadão"
            >
```

- [ ] **Step 5: Atualizar a normativa de navegação**

Em `.cursor/rules/menu-navegacao.mdc`, troque a linha 14:

```
Portal do cidadão: `resources/js/navigation/portal-nav.ts` (Início / Serviços / Minha conta). Mesmas regras, catálogo menor. `portal-layout.tsx`, painel e landing **leem** esse catálogo; proibido lista local.
```

E a seção final "## Portal do cidadão" por:

```
## Portal do cidadão

Fonte de verdade: `resources/js/navigation/portal-nav.ts`. Grupos fixos, nessa ordem:

- **Início**: painel e notificações.
- **Serviços**: tudo o que o requerente faz (consultar, solicitar, renovar, empresas, procurações).
- **Minha conta**: dados pessoais e histórico de acessos.

Todo item de Serviços precisa de uma ação em `PORTAL_ACOES` (card do painel). Ações com `publica: true` aparecem na landing.
Tela-filha (histórico de consultas, comunicações, pendências) não entra no menu: link na tela-mãe.
`assertPortalNavHealth()` valida isso; `portal-nav.test.ts` tem que continuar verde.
```

- [ ] **Step 6: Verificar**

Run: `npx vitest run resources/js/navigation resources/js/components/app && npm run typecheck`
Esperado: tudo PASS e o `tsc` sem erros.

- [ ] **Step 7: Commit**

```bash
git add resources/js/navigation/portal-nav-icons.tsx resources/js/layouts/portal-layout.tsx resources/js/components/app/app-shell.tsx resources/js/components/app/app-sidebar.tsx resources/js/components/app/sidebar-active.test.ts .cursor/rules/menu-navegacao.mdc
git commit -m "feat: sidebar do portal com todos os serviços e logo levando ao painel"
```

---

### Task 3: Filtro `status` em Minhas solicitações

**Files:**
- Modify: `app/Http/Controllers/Portal/SolicitacaoController.php:65-119` (`index`)
- Create: `tests/Feature/Solicitacao/FiltroStatusSolicitacoesTest.php`
- Modify: `resources/js/pages/portal/solicitacoes/index.tsx` (props `filters`, `useServerTable`, bloco de alertas)

**Interfaces:**
- Produces: a prop Inertia `filters.status: string` (`''` quando sem filtro) e `filters.status_label: string | null`. A query `?status=<ViabilityRequestStatus value>` é aceita por `/portal/solicitacoes`.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Solicitacao/FiltroStatusSolicitacoesTest.php`:

```php
<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Filtro por estado em "Minhas solicitações": destino dos indicadores do
 * painel do cidadão. Valor inválido é ignorado; escopo do requerente mantido.
 */
class FiltroStatusSolicitacoesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function solicitacao(User $user, ViabilityRequestStatus $status, ?string $protocolo = null): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'requester_user_id' => $user->id,
            'status' => $status,
            'protocol_number' => $protocolo,
        ]);
    }

    public function test_filtra_pelo_estado_informado(): void
    {
        $user = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise, 'VIA-2026-000001');
        $this->solicitacao($user, ViabilityRequestStatus::EmPendencia, 'VIA-2026-000002');
        $this->solicitacao($user, ViabilityRequestStatus::Deferida, 'VIA-2026-000003');

        $this->actingAs($user)
            ->get('/portal/solicitacoes?status=em_pendencia')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/solicitacoes/index')
                ->has('solicitacoes.data', 1)
                ->where('solicitacoes.data.0.status.value', 'em_pendencia')
                ->where('filters.status', 'em_pendencia')
                ->where('filters.status_label', ViabilityRequestStatus::EmPendencia->publicLabel()));
    }

    public function test_filtro_nao_mostra_processos_de_outro_requerente(): void
    {
        $user = $this->portalUser();
        $outro = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise);
        $this->solicitacao($outro, ViabilityRequestStatus::EmAnalise);

        $this->actingAs($user)
            ->get('/portal/solicitacoes?status=em_analise')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('solicitacoes.data', 1));
    }

    public function test_estado_invalido_e_ignorado(): void
    {
        $user = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise);
        $this->solicitacao($user, ViabilityRequestStatus::Deferida);

        foreach (['/portal/solicitacoes?status=xpto', '/portal/solicitacoes?status=EM_ANALISE', '/portal/solicitacoes?status[]=em_analise'] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('solicitacoes.data', 2)
                    ->where('filters.status', '')
                    ->where('filters.status_label', null));
        }
    }

    public function test_combina_estado_com_busca(): void
    {
        $user = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise, 'VIA-2026-000010');
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise, 'VIA-2026-000020');
        $this->solicitacao($user, ViabilityRequestStatus::Deferida, 'VIA-2026-000011');

        $this->actingAs($user)
            ->get('/portal/solicitacoes?status=em_analise&search=000010')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('solicitacoes.data', 1)
                ->where('solicitacoes.data.0.protocol_number', 'VIA-2026-000010'));
    }

    public function test_sem_estado_lista_tudo(): void
    {
        $user = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise);
        $this->solicitacao($user, ViabilityRequestStatus::Deferida);

        $this->actingAs($user)
            ->get('/portal/solicitacoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('solicitacoes.data', 2)
                ->where('filters.status', ''));
    }
}
```

Se `solicitacoes.data.0.protocol_number` não existir no `SolicitacaoResumoResource`, confira o nome da chave em `app/Http/Resources/SolicitacaoResumoResource.php` e ajuste só essa asserção.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Solicitacao/FiltroStatusSolicitacoesTest.php`
Esperado: FAIL. `test_filtra_pelo_estado_informado` deve acusar "Property [filters.status] does not exist" ou `data` com 3 itens.

- [ ] **Step 3: Implementar o filtro no controller**

Em `SolicitacaoController::index`, logo depois de `$search = ...;`:

```php
        // Filtro por estado (destino dos indicadores do painel). Valor fora do
        // enum, ou não-string, é ignorado: lista tudo, nunca 500.
        $statusInput = $request->input('status');
        $status = is_string($statusInput) ? ViabilityRequestStatus::tryFrom($statusInput) : null;
```

Na query, depois do `->when($search !== '', ...)`:

```php
            ->when($status !== null, fn ($query) => $query->where('status', $status))
```

No array `filters` do `Inertia::render`:

```php
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'status' => $status?->value ?? '',
                'status_label' => $status?->publicLabel(),
            ],
```

O import de `ViabilityRequestStatus` já existe (é usado em `'editable'`). Atualize o PHPDoc do método acrescentando "filtro opcional por estado (`status`)".

- [ ] **Step 4: Rodar e ver passar**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact tests/Feature/Solicitacao/FiltroStatusSolicitacoesTest.php`
Esperado: PASS (5 testes).

- [ ] **Step 5: Front — repassar o filtro e mostrar o chip**

Em `resources/js/pages/portal/solicitacoes/index.tsx`, na interface `filters` de `SolicitacoesIndexProps`:

```ts
    filters: {
        search: string;
        sort: string;
        direction: SortDirection;
        per_page: number;
        status: string;
        status_label: string | null;
    };
```

No `useServerTable`, acrescente:

```ts
        initialFilters: filters.status ? { status: filters.status } : {},
```

Logo depois do bloco `{!solicitacaoEnabled && (...)}`, acrescente:

```tsx
            {filters.status_label && (
                <div className="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-theme-sm text-gray-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-gray-300">
                    <span>
                        Filtrando por: <strong className="font-semibold">{filters.status_label}</strong>
                    </span>
                    <button
                        type="button"
                        onClick={() => table.setFilter('status', '')}
                        className="font-medium text-brand-500 underline-offset-4 hover:underline dark:text-brand-400"
                    >
                        Limpar filtro
                    </button>
                </div>
            )}
```

- [ ] **Step 6: Verificar**

Run: `npm run typecheck`
Esperado: sem erros.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Portal/SolicitacaoController.php tests/Feature/Solicitacao/FiltroStatusSolicitacoesTest.php resources/js/pages/portal/solicitacoes/index.tsx
git commit -m "feat: filtro por estado em minhas solicitações"
```

---

### Task 4: Painel começa por "O que você quer fazer?"

**Files:**
- Modify: `resources/js/pages/portal/dashboard.tsx`
- Test (regressão): `tests/Feature/Portal/PainelCidadaoTest.php` (sem mudança; os props do backend não mudam)

**Interfaces:**
- Consumes: `PORTAL_ACOES`, `FILTROS_SOLICITACOES` (Task 1); `PORTAL_NAV_ICONS` (Task 2).

- [ ] **Step 1: Ajustar os imports e remover `quickActions`**

Em `dashboard.tsx`:
- Remova a interface `QuickAction` e a constante `quickActions` (linhas 79-84 e 123-148).
- Nos imports de ícones, remova `GroupIcon` e `ListIcon` se ficarem sem uso. Mantenha `AlertIcon`, `ArrowRightIcon`, `BellIcon`, `CheckCircleIcon`, `FileIcon` e `PencilIcon`. Acrescente `SearchIcon`.
- Acrescente:

```tsx
import { FILTROS_SOLICITACOES, PORTAL_ACOES } from '@/navigation/portal-nav';
import { PORTAL_NAV_ICONS } from '@/navigation/portal-nav-icons';
```

- [ ] **Step 2: Cabeçalho com saudação**

Troque `<PageHeader title="Meu painel" />` por:

```tsx
            <PageHeader title="Meu painel" />
            <p className="-mt-2 mb-6 text-base text-gray-600 dark:text-gray-300">
                Olá, {primeiroNome}. O que você precisa resolver hoje?
            </p>
```

E logo depois de `const { auth } = usePage<SharedProps>().props;`:

```tsx
    const primeiroNome = auth.user?.name.split(' ')[0] ?? 'cidadão';
```

Se o `PageHeader` já tiver margem inferior que deixe a frase longe, ajuste só `-mt-2` e `mb-6`. A conferência é visual, no Step 7.

- [ ] **Step 3: Item de convite em linguagem simples**

No `atencao.pendencias.map`, troque o conteúdo de `<div className="min-w-0">` por:

```tsx
                                                <div className="min-w-0">
                                                    <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                        Convite para responder — {pendencia.protocol_number ?? 'solicitação'}
                                                    </p>
                                                    <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                        A SEDUR pediu uma informação ou documento para seguir com a análise.
                                                    </p>
                                                    <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {pendencia.descricao}
                                                        {pendencia.due_at ? ` · responda até ${formatDate(pendencia.due_at)}` : ''}
                                                    </p>
                                                </div>
```

- [ ] **Step 4: Indicadores com destino filtrado e rótulos simples**

Substitua o array `possibleIndicators` e a linha `const indicators = ...` por:

```tsx
    const indicators: Indicator[] = [
        {
            key: 'convite',
            label: 'Convites para responder',
            value: numberFormat.format(indicadores.em_convite),
            note: 'aguardando a sua resposta',
            icon: <BellIcon className="size-6" aria-hidden="true" />,
            tone: 'warning',
            href: FILTROS_SOLICITACOES.convites,
        },
        {
            key: 'analise',
            label: 'Em análise',
            value: numberFormat.format(indicadores.em_analise),
            note: 'sendo analisadas pela SEDUR',
            icon: <SearchIcon className="size-6" aria-hidden="true" />,
            tone: 'brand',
            href: FILTROS_SOLICITACOES.emAnalise,
        },
        {
            key: 'processos',
            label: 'Total de solicitações',
            value: numberFormat.format(indicadores.processos),
            note: 'todas as suas solicitações',
            icon: <FileIcon className="size-6" aria-hidden="true" />,
            tone: 'info',
            href: FILTROS_SOLICITACOES.todas,
        },
    ];
```

Na grade dos indicadores, troque `xl:grid-cols-4` por `xl:grid-cols-3`.

- [ ] **Step 5: Bloco "O que você quer fazer?" logo depois do bloco de atenção**

Logo depois do `</div>` que fecha o `col-span-12` do bloco "Precisa da sua atenção" / "Tudo em dia", e antes do bloco dos indicadores, insira:

```tsx
                <section className="col-span-12" aria-labelledby="painel-acoes">
                    <h2 id="painel-acoes" className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">
                        O que você quer fazer?
                    </h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
                        {PORTAL_ACOES.map((acao) => {
                            const Icon = PORTAL_NAV_ICONS[acao.icon];

                            return (
                                <RevealLink
                                    key={acao.href}
                                    href={acao.href}
                                    className="group flex items-start gap-4 rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40 md:p-6"
                                >
                                    <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                                        <Icon className="size-6" aria-hidden="true" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center justify-between gap-2">
                                            <span className="text-base font-semibold text-gray-800 dark:text-white/90">
                                                {acao.titulo}
                                            </span>
                                            <ArrowRightIcon
                                                className="size-5 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-gray-500 dark:group-hover:text-brand-400"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span className="mt-1 block text-theme-sm text-gray-500 dark:text-gray-400">
                                            {acao.descricao}
                                        </span>
                                    </span>
                                </RevealLink>
                            );
                        })}
                    </div>
                </section>
```

- [ ] **Step 6: Lista recente com link do histórico; remover atalhos antigos e card "Minha conta"**

No `CardHeader` de "Minhas solicitações recentes", troque `actions` por:

```tsx
                            actions={
                                <div className="flex flex-wrap items-center gap-4">
                                    <Link
                                        href="/portal/viabilidade/historico"
                                        className="text-theme-sm font-medium text-gray-600 transition hover:text-brand-500 dark:text-gray-300 dark:hover:text-brand-400"
                                    >
                                        Minhas consultas de viabilidade
                                    </Link>
                                    <Link
                                        href={FILTROS_SOLICITACOES.todas}
                                        className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                    >
                                        Ver todas
                                        <ArrowRightIcon className="size-4" aria-hidden="true" />
                                    </Link>
                                </div>
                            }
```

Apague por inteiro os dois últimos blocos `col-span-12`: a grade `quickActions.map(...)` e o `<Card>` "Minha conta". Se `RevealLink` continuar em uso (no Step 5), mantenha o import.

- [ ] **Step 7: Verificar**

Run: `npm run typecheck && php artisan test --compact tests/Feature/Portal/PainelCidadaoTest.php`
Esperado: tsc sem erros e PainelCidadaoTest PASS (os props não mudaram).

Verificação visual: com `composer run dev` rodando, entre como `cidadao@sile.dev` (seed) em `/portal/painel`, nos temas claro e escuro, em 1440px e 375px. A ordem deve ser saudação → atenção → "O que você quer fazer?" (5 cards) → 3 indicadores → recentes. Os indicadores devem abrir a lista com o chip "Filtrando por".

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/portal/dashboard.tsx
git commit -m "feat: painel do cidadão começa pelos serviços e indicadores abrem a lista filtrada"
```

---

### Task 5: Consulta de viabilidade dentro do portal para quem está logado

**Files:**
- Create: `resources/js/pages/portal/viabilidade/shell-da-consulta.ts`
- Create: `resources/js/pages/portal/viabilidade/shell-da-consulta.test.ts`
- Modify: `resources/js/pages/portal/viabilidade/consulta.tsx` (imports, link do cabeçalho em ~243-250, `Consulta.layout` na linha 463)
- Modify: `tests/Feature/Viabilidade/ConsultaViabilidadePaginaTest.php`

**Interfaces:**
- Produces: `function shellDaConsulta(user: { id: number } | null | undefined): 'portal' | 'publico'`

- [ ] **Step 1: Escrever o teste que falha**

`resources/js/pages/portal/viabilidade/shell-da-consulta.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { shellDaConsulta } from './shell-da-consulta';

describe('shellDaConsulta', () => {
    it('usa o shell do portal para quem está logado', () => {
        expect(shellDaConsulta({ id: 7 })).toBe('portal');
    });

    it('usa o shell público para o visitante anônimo', () => {
        expect(shellDaConsulta(null)).toBe('publico');
        expect(shellDaConsulta(undefined)).toBe('publico');
    });
});
```

Run: `npx vitest run resources/js/pages/portal/viabilidade/shell-da-consulta.test.ts`
Esperado: FAIL com "Failed to resolve import './shell-da-consulta'".

- [ ] **Step 2: Implementar**

`resources/js/pages/portal/viabilidade/shell-da-consulta.ts`:

```ts
/**
 * A consulta é pública, mas quem está logado continua dentro do portal
 * (sidebar e serviços à mão) em vez de cair no shell público com "Entrar".
 */
export function shellDaConsulta(user: { id: number } | null | undefined): 'portal' | 'publico' {
    return user ? 'portal' : 'publico';
}
```

Run: `npx vitest run resources/js/pages/portal/viabilidade/shell-da-consulta.test.ts`
Esperado: PASS.

- [ ] **Step 3: Teste de backend da página logada**

Em `tests/Feature/Viabilidade/ConsultaViabilidadePaginaTest.php`, acrescente (e os imports `App\Models\User`, `Database\Seeders\RolesAndPermissionsSeeder` se faltarem):

```php
    public function test_pagina_continua_acessivel_para_cidadao_logado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($user)
            ->get('/portal/viabilidade')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/viabilidade/consulta')
                ->where('auth.user.id', $user->id));
    }
```

Run: `php artisan test --compact tests/Feature/Viabilidade/ConsultaViabilidadePaginaTest.php`
Esperado: PASS. É um teste de caracterização: prova que o shared prop `auth.user` chega à página, que é a entrada do layout dinâmico. O teste anônimo existente garante que a página continua pública.

- [ ] **Step 4: Layout dinâmico e link do histórico**

Em `consulta.tsx`, nos imports:
- Troque `import { Head, Link, useHttp } from '@inertiajs/react';` por `import { Head, Link, useHttp, usePage } from '@inertiajs/react';`.
- Troque `import type { ComponentType, ReactNode, SVGProps } from 'react';` por `import type { ComponentType, ReactElement, ReactNode, SVGProps } from 'react';`.
- Acrescente:

```tsx
import PortalLayout from '@/layouts/portal-layout';
import type { SharedProps } from '@/types';
import { shellDaConsulta } from './shell-da-consulta';
```

Em `Consulta`, logo no início do corpo:

```tsx
    const { auth } = usePage<SharedProps>().props;
```

Troque o `<p className="mt-4 ...">` que contém o link "Entre para salvar o histórico das suas consultas" por:

```tsx
                    <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        {auth.user ? (
                            <Link
                                href="/portal/viabilidade/historico"
                                className="inline-flex items-center gap-1 font-medium text-brand-500 underline-offset-4 hover:underline dark:text-brand-400"
                            >
                                Ver minhas consultas anteriores
                                <ArrowRightIcon className="size-4" aria-hidden="true" />
                            </Link>
                        ) : (
                            <Link
                                href="/portal/login"
                                className="inline-flex items-center gap-1 font-medium text-brand-500 underline-offset-4 hover:underline dark:text-brand-400"
                            >
                                Entre para salvar o histórico das suas consultas
                                <ArrowRightIcon className="size-4" aria-hidden="true" />
                            </Link>
                        )}
                    </p>
```

Mantenha o conteúdo interno do link anônimo exatamente como estava (texto e ícone). Se ele não tinha `ArrowRightIcon`, não acrescente.

O parágrafo de descrição termina em "— sem necessidade de login." Envolva só esse trecho para não aparecer a quem está logado:

```tsx
                        imobiliária{auth.user ? '.' : ' — sem necessidade de login.'}
```

Troque a última linha do arquivo:

```tsx
Consulta.layout = (page: ReactElement<SharedProps>) =>
    shellDaConsulta(page.props.auth?.user) === 'portal' ? (
        <PortalLayout>{page}</PortalLayout>
    ) : (
        <PublicShell>{page}</PublicShell>
    );
```

Dentro do `PortalLayout` o `main` já tem padding. Se o container da página (`mx-auto ... px-4 py-10 sm:px-6`) ficar com respiro duplo, troque por uma classe condicional: `auth.user ? 'w-full' : 'mx-auto w-full max-w-(--breakpoint-xl) px-4 py-10 sm:px-6'`.

- [ ] **Step 5: Verificar**

Run: `npx vitest run resources/js/pages/portal/viabilidade && npm run typecheck && php artisan test --compact tests/Feature/Viabilidade/ConsultaViabilidadePaginaTest.php`
Esperado: tudo PASS.

Visual: anônimo em `/portal/viabilidade` vê o shell público com "Entrar". Logado, vê a sidebar com "Consultar viabilidade" ativo e o link "Ver minhas consultas anteriores". Uma consulta completa funciona nos dois casos.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/portal/viabilidade/shell-da-consulta.ts resources/js/pages/portal/viabilidade/shell-da-consulta.test.ts resources/js/pages/portal/viabilidade/consulta.tsx tests/Feature/Viabilidade/ConsultaViabilidadePaginaTest.php
git commit -m "feat: consulta de viabilidade fica dentro do portal para quem está logado"
```

---

### Task 6: Landing com todos os serviços clicáveis

**Files:**
- Modify: `resources/js/pages/home.tsx` (imports; `ServiceItem` e `services` em 29-62; links "Entrar" em ~165, ~221, ~386, ~441; seção de cards em ~245-280)

**Interfaces:**
- Consumes: `acoesPublicas()` (Task 1), `PORTAL_NAV_ICONS` (Task 2).

- [ ] **Step 1: Trocar a lista local pelo catálogo**

Apague a interface `ServiceItem` e a constante `services`. Nos imports:

```tsx
import { Head, Link, usePage } from '@inertiajs/react';
import { acoesPublicas } from '@/navigation/portal-nav';
import { PORTAL_NAV_ICONS } from '@/navigation/portal-nav-icons';
import type { SharedProps } from '@/types';
```

Remova de `@/components/icons` os ícones que ficarem sem uso (`CheckCircleIcon`, `EyeIcon`, `FileIcon` e `GroupIcon`, se não aparecerem em outro lugar do arquivo; confira com busca). Remova `ComponentType` e `SVGProps` se ficarem sem uso.

Substitua o `services.map(...)` inteiro (do `{services.map((service) => {` até o `})}` correspondente) por:

```tsx
                    {acoesPublicas().map((acao) => {
                        const Icon = PORTAL_NAV_ICONS[acao.icon];

                        return (
                            <RevealLink
                                key={acao.href}
                                href={acao.href}
                                className="rounded-2xl border border-gray-200 bg-white p-6 transition hover:border-brand-200 hover:shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/30"
                            >
                                <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                                    <Icon className="size-6" aria-hidden="true" />
                                </span>
                                <h3 className="mt-5 text-base font-semibold text-gray-800 dark:text-white/90">{acao.titulo}</h3>
                                <p className="mt-2 text-theme-sm leading-6 text-gray-500 dark:text-gray-400">{acao.descricao}</p>
                                <span className="mt-4 inline-flex items-center gap-1 text-theme-sm font-medium text-brand-500 dark:text-brand-400">
                                    Acessar
                                    <ArrowRightIcon className="size-4" aria-hidden="true" />
                                </span>
                            </RevealLink>
                        );
                    })}
```

Se `Surface` ficar sem uso, remova do import de `@/components/motion`.

- [ ] **Step 2: "Entrar" vira "Ir para o meu painel" para quem está logado**

Em cada componente de `home.tsx` que renderiza um dos quatro links `href="/portal/login"` com texto "Entrar", obtenha o usuário:

```tsx
    const { auth } = usePage<SharedProps>().props;
```

E troque cada link para:

```tsx
                    <Link
                        href={auth.user ? '/portal/painel' : '/portal/login'}
                        className={/* className original, sem alteração */}
                    >
                        {auth.user ? 'Ir para o meu painel' : 'Entrar'}
                    </Link>
```

Preserve o `className` original de cada um dos quatro. Os botões de "Criar conta" (`/portal/register`) próximos a eles somem quando `auth.user` existe: envolva cada um em `{!auth.user && (...)}`.

- [ ] **Step 3: Verificar**

Run: `npm run typecheck && npx vitest run resources/js/navigation`
Esperado: sem erros.

Visual em `/portal`: anônimo vê 4 cards clicáveis (Consultar, Solicitar, Renovar, Procurações) e "Entrar". Logado vê "Ir para o meu painel" e nenhum "Criar conta". Clicar em "Solicitar viabilidade" como anônimo leva ao login e, depois de entrar, ao wizard.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/home.tsx
git commit -m "feat: landing lista os serviços do catálogo, todos clicáveis"
```

---

### Task 7: Verificação final

- [ ] **Step 1: Suíte JS e tipos**

Run: `npm test && npm run typecheck && npm run build`
Esperado: todos os testes PASS, tsc sem erros e build concluído.

- [ ] **Step 2: Testes PHP relacionados**

Run: `php artisan test --compact tests/Feature/Portal tests/Feature/Solicitacao/FiltroStatusSolicitacoesTest.php tests/Feature/Viabilidade/ConsultaViabilidadePaginaTest.php tests/Feature/Viabilidade/HistoricoConsultaPaginaTest.php tests/Feature/Procuration`
Esperado: PASS.

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Esperado: sem mudanças pendentes. Se houver, commite com `style: pint`.

- [ ] **Step 4: Roteiro manual (logado como `cidadao@sile.dev`, desktop e 375px, claro e escuro)**

| # | Ação | Esperado |
|---|---|---|
| 1 | Abrir `/portal/painel` | Sidebar: Início (2), Serviços (5), Minha conta (2) |
| 2 | Clicar no logo da sidebar | Volta ao painel, não à landing |
| 3 | Clicar em cada item da sidebar | Todas as 9 rotas abrem, com o item certo aceso |
| 4 | Abrir `/portal/viabilidade/historico` | "Consultar viabilidade" fica aceso |
| 5 | Clicar no indicador "Convites para responder" | Lista com o chip "Filtrando por"; "Limpar filtro" volta à lista completa |
| 6 | Digitar na busca com o filtro ativo | O chip continua e a URL mantém `status` |
| 7 | Clicar nos 5 cards de "O que você quer fazer?" | Todos abrem o serviço certo |
| 8 | Sair e abrir `/portal` | 4 cards clicáveis e "Entrar" |
| 9 | Anônimo em `/portal/viabilidade` | Shell público e consulta funciona |

- [ ] **Step 5: Perguntar ao usuário se roda a suíte inteira** (`php artisan test --compact`), conforme o AGENTS.md.
