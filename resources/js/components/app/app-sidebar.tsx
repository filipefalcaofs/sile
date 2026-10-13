import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { filtrarGruposMenu } from '@/components/app/filtrar-menu';
import Logo, { LogoMark } from '@/components/app/logo';
import { ChevronDownIcon, CloseIcon, HorizontalDotsIcon, SearchIcon } from '@/components/icons';
import { Collapse } from '@/components/motion';
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
    /** Destino do logo; padrão `homeHref`. */
    logoHref?: string;
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
    logoHref,
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
    const buscaId = useId();
    const [termoBusca, setTermoBusca] = useState('');
    const buscando = termoBusca.trim() !== '';
    const gruposExibidos = showText ? filtrarGruposMenu(visibleGroups, termoBusca) : visibleGroups;

    function aoTeclarBusca(evento: KeyboardEvent<HTMLInputElement>) {
        if (evento.key === 'Escape') {
            setTermoBusca('');

            return;
        }

        if (evento.key !== 'Enter') {
            return;
        }

        const itens = gruposExibidos.flatMap((group) => group.items);

        if (itens.length !== 1) {
            return;
        }

        evento.preventDefault();
        router.visit(itens[0].href);
    }

    const surfaceStyles = isConsole
        ? 'bg-gray-950 border-white/[0.06]'
        : 'bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 border-gray-200';

    const headingStyles = isConsole ? 'text-gray-300' : 'text-gray-400';

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
            className={`fixed top-28 flex h-[calc(100dvh-7rem)] flex-col px-5 left-0 transition-all duration-300 ease-in-out z-50 border-r lg:top-0 lg:h-screen ${surfaceStyles}
        ${isExpanded || isMobileOpen ? 'w-[290px]' : isHovered ? 'w-[290px]' : 'w-[90px]'}
        ${isMobileOpen ? 'translate-x-0' : '-translate-x-full'}
        lg:translate-x-0`}
            onMouseEnter={() => !isExpanded && setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <div className={`flex ${showText ? 'pt-8 pb-4' : 'py-8'} ${!isExpanded && !isHovered ? 'lg:justify-center' : 'justify-start'}`}>
                <Link href={logoHref ?? homeHref}>
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
            {showText && (
                <div className="mb-5">
                    <label htmlFor={buscaId} className="sr-only">
                        Buscar no menu
                    </label>
                    <div className="relative">
                        <SearchIcon
                            className={`pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 ${
                                isConsole ? 'text-white/40' : 'text-gray-400'
                            }`}
                        />
                        <input
                            id={buscaId}
                            type="search"
                            value={termoBusca}
                            onChange={(evento) => setTermoBusca(evento.target.value)}
                            onKeyDown={aoTeclarBusca}
                            placeholder="Buscar no menu"
                            autoComplete="off"
                            spellCheck={false}
                            className={`h-10 w-full rounded-lg border pr-9 pl-9 text-sm outline-none transition focus-visible:ring-2 focus-visible:ring-brand-400/40 [&::-webkit-search-cancel-button]:hidden ${
                                isConsole
                                    ? 'border-white/10 bg-white/5 text-white placeholder:text-white/35 focus:border-brand-400'
                                    : 'border-gray-200 bg-gray-50 text-gray-800 placeholder:text-gray-400 focus:border-brand-300 dark:border-gray-700 dark:bg-white/5 dark:text-white dark:placeholder:text-white/30'
                            }`}
                        />
                        {termoBusca !== '' && (
                            <button
                                type="button"
                                onClick={() => setTermoBusca('')}
                                aria-label="Limpar busca"
                                className={`absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 ${
                                    isConsole ? 'text-white/40 hover:text-white' : 'text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                                }`}
                            >
                                <CloseIcon className="size-4" />
                            </button>
                        )}
                    </div>
                </div>
            )}
            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
                <nav id="menu" aria-label="Menu principal" className="mb-6">
                    <div className="flex flex-col gap-6">
                        {gruposExibidos.length === 0 && (
                            <p role="status" className={`px-1 text-sm ${isConsole ? 'text-white/45' : 'text-gray-500'}`}>
                                Nenhum item encontrado.
                            </p>
                        )}
                        {gruposExibidos.map((group, index) => {
                            const collapsible = collapsibleGroups && showText;
                            const open = collapsible ? buscando || !closedGroups.includes(group.label) : true;
                            const listId = `sidebar-group-${index}`;

                            return (
                                <div key={group.label}>
                                    {collapsible ? (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (!buscando) {
                                                    handleToggleGroup(group.label);
                                                }
                                            }}
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
                                    <Collapse open={open}>
                                        <ul id={listId} className="flex flex-col gap-4">
                                            {group.items.map((item) => (
                                                <li key={item.href}>
                                                    <Link
                                                        href={item.href}
                                                        aria-label={item.name}
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
                                    </Collapse>
                                </div>
                            );
                        })}
                    </div>
                </nav>
            </div>
        </aside>
    );
}
