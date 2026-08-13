# Sidebar do console de Gestão colapsável — Plano de Implementação

> **Para executores agênticos:** SUB-SKILL OBRIGATÓRIA: use superpowers:subagent-driven-development (recomendado) ou superpowers:executing-plans para implementar este plano tarefa a tarefa. Os passos usam checkbox (`- [ ]`) para rastreamento.

**Goal:** Tornar cada categoria do menu lateral do console de Gestão (SEDUR) colapsável, com estado lembrado em localStorage, e reorganizar o menu em 6 categorias mais didáticas — sem alterar o Portal do Cidadão.

**Architecture:** A lógica de estado (resolver categoria ativa, persistência, toggles) fica isolada em funções puras em `sidebar-collapse.ts` (testadas com vitest, como já se faz em `sidebar-active.ts`). O `app-sidebar.tsx` ganha o prop `collapsibleGroups` que ativa o acordeão (cabeçalho `<button>` com `aria-expanded`/chevron); o portal mantém cabeçalhos estáticos. A definição do grupo "Auditoria e compliance", hoje hardcoded no componente, migra para `gestao-layout.tsx`, que passa a definir as 6 categorias na ordem final.

**Tech Stack:** React 19, Inertia v3, TypeScript, Tailwind v4, vitest (ambiente `node`, sem testing-library).

**Nota de sequência:** as Tarefas 3 e 4 são acopladas — a 3 remove a injeção do grupo de compliance do componente e a 4 o recoloca no layout. Execute-as em sequência antes da verificação visual. Sem testes de componente, nenhuma suíte quebra nesse intervalo; `typecheck` e `build` passam em cada etapa.

**Nota sobre commits:** os passos de commit seguem o padrão TDD do projeto. Conforme as regras do repositório, só efetive os commits com autorização do usuário (ou agrupe ao final, conforme ele preferir).

---

## Task 1: Lógica pura de colapso (`sidebar-collapse.ts`)

**Files:**
- Create: `resources/js/components/app/sidebar-collapse.ts`
- Test: `resources/js/components/app/sidebar-collapse.test.ts`

- [ ] **Step 1: Escrever o teste que falha**

Criar `resources/js/components/app/sidebar-collapse.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import {
    readClosedGroups,
    resolveActiveGroup,
    STORAGE_KEY,
    toggleClosed,
    withGroupOpen,
    writeClosedGroups,
} from './sidebar-collapse';

function createMemoryStorage(initial: Record<string, string> = {}): Storage {
    const map = new Map<string, string>(Object.entries(initial));
    const storage: Storage = {
        get length() {
            return map.size;
        },
        clear: () => map.clear(),
        getItem: (key) => (map.has(key) ? (map.get(key) as string) : null),
        key: (index) => Array.from(map.keys())[index] ?? null,
        removeItem: (key) => {
            map.delete(key);
        },
        setItem: (key, value) => {
            map.set(key, value);
        },
    };

    return storage;
}

const groups = [
    { label: 'Análise técnica', items: [{ href: '/gestao/processos/fila' }, { href: '/gestao/processos' }] },
    { label: 'Regras do licenciamento', items: [{ href: '/gestao/cnaes' }, { href: '/gestao/risco' }] },
];

describe('resolveActiveGroup', () => {
    it('retorna o label da categoria que contém o item ativo', () => {
        expect(resolveActiveGroup(groups, '/gestao/cnaes')).toBe('Regras do licenciamento');
    });

    it('retorna null quando nenhum item corresponde ao href ativo', () => {
        expect(resolveActiveGroup(groups, '/gestao/inexistente')).toBeNull();
    });

    it('retorna null quando não há href ativo', () => {
        expect(resolveActiveGroup(groups, null)).toBeNull();
    });
});

describe('readClosedGroups / writeClosedGroups', () => {
    it('faz round-trip da lista de categorias fechadas no storage', () => {
        const storage = createMemoryStorage();
        writeClosedGroups(['Administração', 'Auditoria e compliance'], storage);
        expect(readClosedGroups(storage)).toEqual(['Administração', 'Auditoria e compliance']);
    });

    it('retorna lista vazia quando não há nada salvo', () => {
        expect(readClosedGroups(createMemoryStorage())).toEqual([]);
    });

    it('retorna lista vazia quando o conteúdo salvo é inválido', () => {
        const storage = createMemoryStorage({ [STORAGE_KEY]: 'isto-não-é-json' });
        expect(readClosedGroups(storage)).toEqual([]);
    });

    it('ignora valores não-string dentro do array salvo', () => {
        const storage = createMemoryStorage({ [STORAGE_KEY]: JSON.stringify(['Administração', 42, null]) });
        expect(readClosedGroups(storage)).toEqual(['Administração']);
    });

    it('retorna lista vazia e não lança quando o storage está indisponível', () => {
        expect(readClosedGroups(null)).toEqual([]);
        expect(() => writeClosedGroups(['Administração'], null)).not.toThrow();
    });
});

describe('toggleClosed', () => {
    it('adiciona a categoria quando ela está aberta', () => {
        expect(toggleClosed([], 'Administração')).toEqual(['Administração']);
    });

    it('remove a categoria quando ela já está fechada', () => {
        expect(toggleClosed(['Administração', 'Início'], 'Administração')).toEqual(['Início']);
    });

    it('não muta o array original', () => {
        const original = ['Início'];
        toggleClosed(original, 'Administração');
        expect(original).toEqual(['Início']);
    });
});

describe('withGroupOpen', () => {
    it('remove a categoria informada da lista de fechadas', () => {
        expect(withGroupOpen(['Início', 'Administração'], 'Administração')).toEqual(['Início']);
    });

    it('é no-op quando o label é null', () => {
        expect(withGroupOpen(['Início'], null)).toEqual(['Início']);
    });

    it('não muta o array original', () => {
        const original = ['Início', 'Administração'];
        withGroupOpen(original, 'Administração');
        expect(original).toEqual(['Início', 'Administração']);
    });
});
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `npx vitest run resources/js/components/app/sidebar-collapse.test.ts`
Expected: FAIL — "Failed to resolve import './sidebar-collapse'" (o módulo ainda não existe).

- [ ] **Step 3: Implementar o módulo mínimo**

Criar `resources/js/components/app/sidebar-collapse.ts`:

```ts
/**
 * Estado de colapso das categorias da sidebar (console de Gestão).
 *
 * O acordeão é "livre com memória": várias categorias podem ficar abertas
 * ao mesmo tempo e a preferência é guardada em localStorage. Persistimos a
 * lista de categorias FECHADAS (não as abertas): assim o padrão é tudo
 * aberto e categorias novas adicionadas no futuro nascem abertas, sem
 * precisar migrar o valor salvo.
 */
export const STORAGE_KEY = 'sile.sidebar.gestao.closedGroups';

interface MenuGroupLike {
    label: string;
    items: ReadonlyArray<{ href: string }>;
}

/**
 * Label da categoria que contém o item atualmente ativo, ou `null`.
 * Usado para abrir a categoria da página atual ao navegar.
 */
export function resolveActiveGroup(groups: ReadonlyArray<MenuGroupLike>, activeHref: string | null): string | null {
    if (!activeHref) {
        return null;
    }

    for (const group of groups) {
        if (group.items.some((item) => item.href === activeHref)) {
            return group.label;
        }
    }

    return null;
}

function resolveStorage(storage?: Storage | null): Storage | null {
    if (storage !== undefined) {
        return storage;
    }

    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

/** Lê a lista de categorias fechadas; tolera storage ausente ou conteúdo inválido. */
export function readClosedGroups(storage?: Storage | null): string[] {
    const target = resolveStorage(storage);
    if (!target) {
        return [];
    }

    try {
        const raw = target.getItem(STORAGE_KEY);
        if (!raw) {
            return [];
        }

        const parsed: unknown = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.filter((value): value is string => typeof value === 'string');
    } catch {
        return [];
    }
}

/** Grava a lista de categorias fechadas; no-op silencioso se o storage não estiver disponível. */
export function writeClosedGroups(labels: ReadonlyArray<string>, storage?: Storage | null): void {
    const target = resolveStorage(storage);
    if (!target) {
        return;
    }

    try {
        target.setItem(STORAGE_KEY, JSON.stringify(labels));
    } catch {
        // Storage cheio ou indisponível: a preferência não persiste, mas a navegação segue funcionando.
    }
}

/** Alterna uma categoria entre aberta/fechada, sem mutar o array recebido. */
export function toggleClosed(closed: ReadonlyArray<string>, label: string): string[] {
    return closed.includes(label) ? closed.filter((item) => item !== label) : [...closed, label];
}

/** Garante que uma categoria esteja aberta (removida da lista de fechadas), sem mutar o array recebido. */
export function withGroupOpen(closed: ReadonlyArray<string>, label: string | null): string[] {
    if (label === null) {
        return [...closed];
    }

    return closed.filter((item) => item !== label);
}
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `npx vitest run resources/js/components/app/sidebar-collapse.test.ts`
Expected: PASS (todos os blocos `describe`).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/app/sidebar-collapse.ts resources/js/components/app/sidebar-collapse.test.ts
git commit -m "feat: adiciona lógica de colapso de categorias da sidebar"
```

---

## Task 2: Propagar `collapsibleGroups` pelo `AppShell`

**Files:**
- Modify: `resources/js/components/app/app-shell.tsx`

- [ ] **Step 1: Adicionar o prop à interface e repassá-lo**

Em `resources/js/components/app/app-shell.tsx`, a interface `AppShellProps` e a função `ShellContent` passam a aceitar e repassar `collapsibleGroups`. Resultado final do arquivo:

```tsx
import type { ReactNode } from 'react';
import AppHeader from '@/components/app/app-header';
import AppSidebar from '@/components/app/app-sidebar';
import type { SidebarGroup, SidebarVariant } from '@/components/app/app-sidebar';
import Backdrop from '@/components/app/backdrop';
import { SidebarProvider, useSidebar } from '@/contexts/sidebar-context';

interface AppShellProps {
    groups: SidebarGroup[];
    homeHref: string;
    logoutHref?: string;
    subtitle?: string;
    variant?: SidebarVariant;
    collapsibleGroups?: boolean;
    children: ReactNode;
}

function ShellContent({ groups, homeHref, logoutHref, subtitle, variant, collapsibleGroups, children }: AppShellProps) {
    const { isExpanded, isHovered, isMobileOpen } = useSidebar();

    return (
        <div className="min-h-screen">
            <AppSidebar
                groups={groups}
                homeHref={homeHref}
                subtitle={subtitle}
                variant={variant}
                collapsibleGroups={collapsibleGroups}
            />
            <Backdrop />
            <div
                className={`transition-all duration-300 ease-in-out ${
                    isExpanded || isHovered ? 'lg:ml-[290px]' : 'lg:ml-[90px]'
                } ${isMobileOpen ? 'ml-0' : ''}`}
            >
                <AppHeader homeHref={homeHref} logoutHref={logoutHref ?? '/portal/logout'} />
                <div id="conteudo" className="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">{children}</div>
            </div>
        </div>
    );
}

/**
 * Estrutura de página do TailAdmin (sidebar + backdrop + header +
 * container de conteúdo) compartilhada pelos layouts de gestão e do
 * portal.
 */
export default function AppShell(props: AppShellProps) {
    return (
        <SidebarProvider>
            <ShellContent {...props} />
        </SidebarProvider>
    );
}
```

- [ ] **Step 2: Verificar tipos**

Run: `npm run typecheck`
Expected: PASS (sem erros). O comportamento não muda: `collapsibleGroups` é opcional e ninguém o passa ainda.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/app/app-shell.tsx
git commit -m "feat: repassa prop collapsibleGroups pelo AppShell"
```

---

## Task 3: Acordeão no `app-sidebar.tsx` e remoção da injeção de compliance

**Files:**
- Modify: `resources/js/components/app/app-sidebar.tsx`

- [ ] **Step 1: Reescrever o componente**

Substituir todo o conteúdo de `resources/js/components/app/app-sidebar.tsx` por:

```tsx
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import Logo, { LogoMark } from '@/components/app/logo';
import { ChevronDownIcon, HorizontalDotsIcon } from '@/components/icons';
import { useSidebar } from '@/contexts/sidebar-context';
import { resolveActiveHref } from '@/components/app/sidebar-active';
import {
    readClosedGroups,
    resolveActiveGroup,
    toggleClosed,
    withGroupOpen,
    writeClosedGroups,
} from '@/components/app/sidebar-collapse';
import type { SharedProps } from '@/types';

export interface SidebarItem {
    name: string;
    href: string;
    icon: ReactNode;
    visible?: boolean;
}

export interface SidebarGroup {
    label: string;
    items: SidebarItem[];
}

export type SidebarVariant = 'light' | 'console';

interface AppSidebarProps {
    groups: SidebarGroup[];
    homeHref: string;
    subtitle?: string;
    variant?: SidebarVariant;
    collapsibleGroups?: boolean;
}

/**
 * Sidebar do TailAdmin adaptada: recebe grupos de navegação com headings
 * por props — já filtrados por permissão pelo layout — e marca o item
 * ativo comparando com a URL atual do Inertia.
 *
 * Variantes: `light` (portal do cidadão) e `console` (gestão SEDUR). Com
 * `collapsibleGroups`, cada categoria vira um acordeão com estado lembrado
 * em localStorage; a categoria da página atual abre sozinha ao navegar. No
 * modo só-ícone (barra recolhida sem hover) o acordeão não atua e todos os
 * ícones aparecem, como antes.
 */
export default function AppSidebar({
    groups,
    homeHref,
    subtitle,
    variant = 'light',
    collapsibleGroups = false,
}: AppSidebarProps) {
    const { isExpanded, isMobileOpen, isHovered, setIsHovered } = useSidebar();
    const { url } = usePage<SharedProps>();

    const currentPath = url.split('?')[0] ?? '';

    const isConsole = variant === 'console';

    const visibleGroups = groups
        .map((group) => ({ ...group, items: group.items.filter((item) => item.visible !== false) }))
        .filter((group) => group.items.length > 0);

    const activeHref = resolveActiveHref(
        currentPath,
        visibleGroups.flatMap((group) => group.items.map((item) => item.href)),
        homeHref,
    );
    const isActive = (href: string) => href === activeHref;

    const activeGroup = resolveActiveGroup(visibleGroups, activeHref);
    const [closedGroups, setClosedGroups] = useState<string[]>(() =>
        collapsibleGroups ? readClosedGroups() : [],
    );

    useEffect(() => {
        if (!collapsibleGroups || !activeGroup) {
            return;
        }

        setClosedGroups((prev) => {
            if (!prev.includes(activeGroup)) {
                return prev;
            }

            const next = withGroupOpen(prev, activeGroup);
            writeClosedGroups(next);
            return next;
        });
    }, [collapsibleGroups, activeGroup]);

    const handleToggleGroup = (label: string) => {
        setClosedGroups((prev) => {
            const next = toggleClosed(prev, label);
            writeClosedGroups(next);
            return next;
        });
    };

    const showText = isExpanded || isHovered || isMobileOpen;

    const surfaceStyles = isConsole
        ? 'bg-gray-950 border-white/[0.06]'
        : 'bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 border-gray-200';

    const headingStyles = isConsole ? 'text-white/30' : 'text-gray-400';

    const itemStyles = (active: boolean) => {
        if (isConsole) {
            return active ? 'bg-brand-500/15 text-white' : 'text-gray-400 hover:bg-white/5 hover:text-white';
        }

        return active ? 'menu-item-active' : 'menu-item-inactive';
    };

    const iconStyles = (active: boolean) => {
        if (isConsole) {
            return active ? 'text-brand-400' : 'text-gray-500 group-hover:text-gray-300';
        }

        return active ? 'menu-item-icon-active' : 'menu-item-icon-inactive';
    };

    return (
        <aside
            className={`fixed mt-16 flex flex-col lg:mt-0 top-0 px-5 left-0 h-screen transition-all duration-300 ease-in-out z-50 border-r ${surfaceStyles}
        ${isExpanded || isMobileOpen ? 'w-[290px]' : isHovered ? 'w-[290px]' : 'w-[90px]'}
        ${isMobileOpen ? 'translate-x-0' : '-translate-x-full'}
        lg:translate-x-0`}
            onMouseEnter={() => !isExpanded && setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <div className={`py-8 flex ${!isExpanded && !isHovered ? 'lg:justify-center' : 'justify-start'}`}>
                <Link href={homeHref}>
                    {showText ? (
                        <Logo
                            subtitle={subtitle}
                            markClassName="size-9"
                            textClassName={
                                isConsole
                                    ? 'text-2xl font-semibold tracking-tight text-white'
                                    : 'text-2xl font-semibold tracking-tight text-gray-900 dark:text-white'
                            }
                            subtitleClassName={
                                isConsole ? 'text-theme-xs text-white/40' : 'text-theme-xs text-gray-500 dark:text-gray-400'
                            }
                            markColorClassName={isConsole ? 'text-brand-400' : 'text-brand-500'}
                        />
                    ) : (
                        <span className={isConsole ? 'text-brand-400' : 'text-brand-500'}>
                            <LogoMark className="size-9" />
                        </span>
                    )}
                </Link>
            </div>
            <div className="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
                <nav className="mb-6">
                    <div className="flex flex-col gap-6">
                        {visibleGroups.map((group, index) => {
                            const collapsible = collapsibleGroups && showText;
                            const open = collapsible ? !closedGroups.includes(group.label) : true;
                            const listId = `sidebar-group-${index}`;

                            return (
                                <div key={group.label}>
                                    {collapsible ? (
                                        <button
                                            type="button"
                                            onClick={() => handleToggleGroup(group.label)}
                                            aria-expanded={open}
                                            aria-controls={listId}
                                            className={`mb-4 flex w-full items-center justify-between text-xs uppercase leading-[20px] transition-colors ${headingStyles}`}
                                        >
                                            <span>{group.label}</span>
                                            <ChevronDownIcon
                                                aria-hidden
                                                className={`size-4 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
                                            />
                                        </button>
                                    ) : (
                                        <h2
                                            className={`mb-4 text-xs uppercase flex leading-[20px] ${headingStyles} ${
                                                !isExpanded && !isHovered ? 'lg:justify-center' : 'justify-start'
                                            }`}
                                        >
                                            {showText ? group.label : <HorizontalDotsIcon className="size-6" />}
                                        </h2>
                                    )}
                                    <ul id={listId} hidden={!open} className="flex flex-col gap-4">
                                        {group.items.map((item) => (
                                            <li key={item.href}>
                                                <Link
                                                    href={item.href}
                                                    className={`menu-item group ${itemStyles(isActive(item.href))}`}
                                                >
                                                    <span className={`menu-item-icon-size ${iconStyles(isActive(item.href))}`}>
                                                        {item.icon}
                                                    </span>
                                                    {showText && <span className="menu-item-text">{item.name}</span>}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            );
                        })}
                    </div>
                </nav>
            </div>
        </aside>
    );
}
```

Mudanças relevantes em relação à versão anterior: removidos o `complianceGroup` hardcoded, o `sourceGroups` e o uso de `permissions`/`props` (o componente não conhece mais rotas/permissões específicas); imports de ícones reduzidos para `ChevronDownIcon` e `HorizontalDotsIcon`; adicionados estado de colapso e o render do cabeçalho como `<button>` acessível.

- [ ] **Step 2: Verificar tipos**

Run: `npm run typecheck`
Expected: PASS. Atenção: se acusar import não usado, confirme que `AlertIcon`, `ListIcon` e `LockIcon` foram removidos do import de `@/components/icons` (só restam `ChevronDownIcon` e `HorizontalDotsIcon`).

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/app/app-sidebar.tsx
git commit -m "feat: torna categorias da sidebar colapsáveis via prop collapsibleGroups"
```

---

## Task 4: Reorganizar o menu do console e religar o compliance

**Files:**
- Modify: `resources/js/layouts/gestao-layout.tsx`

- [ ] **Step 1: Aplicar a nova ordem de categorias e passar `collapsibleGroups`**

Em `resources/js/layouts/gestao-layout.tsx`: adicionar `AlertIcon` ao import de `@/components/icons`, substituir o array `groups` pelas 6 categorias abaixo (incluindo "Auditoria e compliance" como categoria 5, antes injetada pelo componente) e passar `collapsibleGroups` ao `<AppShell>`.

Import de ícones (linha 6) passa a ser:

```tsx
import { AlertIcon, FileIcon, GearIcon, GridIcon, GroupIcon, ListIcon, LockIcon, MailIcon, MapPinIcon, PlugInIcon, ShieldIcon, TableIcon, TagIcon, UserCircleIcon } from '@/components/icons';
```

Array `groups` (substitui o atual por completo):

```tsx
    const groups: SidebarGroup[] = [
        {
            label: 'Início',
            items: [{ name: 'Painel', href: '/gestao', icon: <GridIcon /> }],
        },
        {
            label: 'Atendimento e operação',
            items: [
                {
                    name: 'Consulta territorial',
                    href: '/gestao/territorio',
                    icon: <MapPinIcon />,
                    visible: auth.permissions.includes('consultar-territorio'),
                },
                {
                    name: 'Atendimento presencial',
                    href: '/gestao/atendimento',
                    icon: <UserCircleIcon />,
                    visible: auth.permissions.includes('atendimento-presencial'),
                },
                {
                    name: 'Nova solicitação (contingência)',
                    href: '/gestao/contingencia',
                    icon: <FileIcon />,
                    visible: auth.permissions.includes('registrar-contingencia'),
                },
                {
                    name: 'Resultados do fluxo expresso',
                    href: '/gestao/resultados-expresso',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('consultar-solicitacoes'),
                },
            ],
        },
        {
            label: 'Análise técnica',
            items: [
                {
                    name: 'Fila de trabalho',
                    href: '/gestao/processos/fila',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('analisar-processos'),
                },
                {
                    name: 'Processos',
                    href: '/gestao/processos',
                    icon: <FileIcon />,
                    visible: auth.permissions.includes('consultar-solicitacoes'),
                },
                {
                    name: 'Setores',
                    href: '/gestao/setores',
                    icon: <GroupIcon />,
                    visible: auth.permissions.includes('manter-setores'),
                },
                {
                    name: 'Textos-padrão',
                    href: '/gestao/textos-padrao',
                    icon: <TableIcon />,
                    visible: auth.permissions.includes('manter-parametros'),
                },
            ],
        },
        {
            label: 'Regras do licenciamento',
            items: [
                {
                    name: 'CNAEs',
                    href: '/gestao/cnaes',
                    icon: <TableIcon />,
                    visible: auth.permissions.includes('consultar-cnaes'),
                },
                {
                    name: 'Tipos de serviço',
                    href: '/gestao/tipos-servico',
                    icon: <TagIcon />,
                    visible: auth.permissions.includes('manter-tipos-servico'),
                },
                {
                    name: 'Classificação de risco',
                    href: '/gestao/risco',
                    icon: <ShieldIcon />,
                    visible: auth.permissions.includes('consultar-risco'),
                },
                {
                    name: 'Condicionantes',
                    href: '/gestao/risco/condicionantes',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('manter-risco'),
                },
                {
                    name: 'Quadros LOUOS',
                    href: '/gestao/louos',
                    icon: <FileIcon />,
                    visible: auth.permissions.includes('consultar-louos'),
                },
                {
                    name: 'Simulação de regras',
                    href: '/gestao/louos/simulacao',
                    icon: <GearIcon />,
                    visible: auth.permissions.includes('manter-louos'),
                },
                {
                    name: 'Requisitos documentais',
                    href: '/gestao/requisitos-documentais',
                    icon: <FileIcon />,
                    visible: auth.permissions.includes('manter-requisitos-documentais'),
                },
            ],
        },
        {
            label: 'Auditoria e compliance',
            items: [
                {
                    name: 'Trilha de auditoria',
                    href: '/gestao/auditoria',
                    icon: <ListIcon />,
                    visible: auth.permissions.includes('consultar-auditoria'),
                },
                {
                    name: 'Conformidade LGPD',
                    href: '/gestao/lgpd',
                    icon: <LockIcon />,
                    visible: auth.permissions.includes('monitorar-lgpd'),
                },
                {
                    name: 'Alertas de abuso',
                    href: '/gestao/abuso',
                    icon: <AlertIcon />,
                    visible: auth.permissions.includes('gerenciar-alertas-abuso'),
                },
            ],
        },
        {
            label: 'Administração',
            items: [
                {
                    name: 'Usuários',
                    href: '/gestao/usuarios',
                    icon: <GroupIcon />,
                    visible: auth.permissions.includes('manter-usuarios'),
                },
                {
                    name: 'Perfis',
                    href: '/gestao/perfis',
                    icon: <LockIcon />,
                    visible: auth.permissions.includes('manter-perfis'),
                },
                {
                    name: 'Parâmetros',
                    href: '/gestao/parametros',
                    icon: <PlugInIcon />,
                    visible: auth.permissions.includes('manter-parametros'),
                },
                {
                    name: 'E-mails',
                    href: '/gestao/emails',
                    icon: <MailIcon />,
                    visible: auth.permissions.includes('monitorar-emails'),
                },
            ],
        },
    ];
```

Linha do `<AppShell>` (acrescentar `collapsibleGroups`):

```tsx
            <AppShell groups={groups} homeHref="/gestao" logoutHref="/gestao/logout" subtitle="Gestão SEDUR" variant="console" collapsibleGroups>
```

- [ ] **Step 2: Verificar tipos**

Run: `npm run typecheck`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/layouts/gestao-layout.tsx
git commit -m "feat: reorganiza menu do console de Gestão em 6 categorias colapsáveis"
```

---

## Task 5: Verificação integrada

**Files:** nenhum (apenas execução de comandos e checagem visual).

- [ ] **Step 1: Suíte de testes JS**

Run: `npm run test`
Expected: PASS — inclui `sidebar-active.test.ts` (existente) e `sidebar-collapse.test.ts` (novo).

- [ ] **Step 2: Typecheck e build**

Run: `npm run typecheck && npm run build`
Expected: ambos sem erro (build gera o manifest do Vite).

- [ ] **Step 3: Checagem visual manual (console de Gestão)**

Com `composer run dev` (ou `npm run dev`) rodando, autenticado na gestão, confirmar:
- As 6 categorias aparecem na ordem: Início, Atendimento e operação, Análise técnica, Regras do licenciamento, Auditoria e compliance, Administração (respeitando permissões do usuário).
- Clicar no cabeçalho de uma categoria colapsa/expande; o chevron rotaciona.
- Recarregar a página mantém o estado de colapso (persistência em localStorage).
- Navegar para uma página cuja categoria estava fechada abre essa categoria automaticamente.
- Recolher a barra (botão de toggle geral) para o modo só-ícone (90px) mostra todos os ícones, sem acordeão; ao passar o mouse, a barra expande e o acordeão reaparece.
- Abrir o Portal do Cidadão e confirmar que o menu dele permanece idêntico (cabeçalhos estáticos, sem chevrons).

---

## Self-Review (preenchido na escrita do plano)

**1. Cobertura da spec:**
- §3.1 reorganização em 6 categorias → Task 4.
- §3.2 prop `collapsibleGroups`, propagação e migração do compliance → Tasks 2, 3 e 4.
- §3.3 lógica/persistência (`sidebar-collapse.ts`) → Task 1; uso no componente → Task 3.
- §3.4 render colapsável e modo só-ícone → Task 3.
- §4 acessibilidade (`<button>`, `aria-expanded`, `aria-controls`, chevron `aria-hidden`) → Task 3.
- §5 testes (vitest, lógica pura) → Task 1; verificação → Task 5.
- §6 fora de escopo (portal intacto, sem novas deps) → respeitado (portal usa default `collapsibleGroups=false`).

**2. Placeholders:** nenhum — todo passo de código traz o código completo e todo passo de verificação traz comando + resultado esperado.

**3. Consistência de tipos:** assinaturas usadas no componente (`resolveActiveGroup`, `readClosedGroups`, `writeClosedGroups`, `toggleClosed`, `withGroupOpen`) batem com as definidas na Task 1; `collapsibleGroups?: boolean` é idêntico em `AppSidebar`, `AppShell` e na chamada do `gestao-layout`. A `<ul>` recebe `id={listId}` referenciado por `aria-controls` com o mesmo valor.

**Detalhe de a11y aprimorado em relação à spec:** a `<ul>` permanece sempre no DOM e é ocultada com o atributo `hidden` (em vez de não ser renderizada). Isso mantém o alvo de `aria-controls` sempre válido e remove o conteúdo da árvore de acessibilidade quando fechada.
