import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Logo, { LogoMark } from '@/components/app/logo';
import { AlertIcon, HorizontalDotsIcon, ListIcon, LockIcon } from '@/components/icons';
import { useSidebar } from '@/contexts/sidebar-context';
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
}

/**
 * Sidebar do TailAdmin adaptada: recebe grupos de navegação com
 * headings por props — já filtrados por permissão pelo layout — e
 * marca o item ativo comparando com a URL atual do Inertia.
 *
 * Variantes: `light` (portal do cidadão) e `console` (gestão SEDUR) —
 * o console estende a identidade escura do login interno (Fase 2.3)
 * para dentro do app, independente do tema claro/escuro do conteúdo.
 */
export default function AppSidebar({ groups, homeHref, subtitle, variant = 'light' }: AppSidebarProps) {
    const { isExpanded, isMobileOpen, isHovered, setIsHovered } = useSidebar();
    const { url, props } = usePage<SharedProps>();
    const permissions = Array.isArray(props.auth?.permissions) ? props.auth.permissions : [];

    const currentPath = url.split('?')[0] ?? '';

    const isActive = (href: string) => {
        if (href === homeHref) {
            return currentPath === href;
        }

        return currentPath === href || currentPath.startsWith(`${href}/`);
    };

    const isConsole = variant === 'console';

    // Grupo de auditoria/compliance (HU-098/100/102/149): só na retaguarda
    // (console) e gated por permissão — quem não tem a permissão não vê o item.
    // Aponta para as superfícies reais entregues em 12-09/12-10.
    const complianceGroup: SidebarGroup = {
        label: 'Auditoria e compliance',
        items: [
            {
                name: 'Trilha de auditoria',
                href: '/gestao/auditoria',
                icon: <ListIcon />,
                visible: permissions.includes('consultar-auditoria'),
            },
            {
                name: 'Conformidade LGPD',
                href: '/gestao/lgpd',
                icon: <LockIcon />,
                visible: permissions.includes('monitorar-lgpd'),
            },
            {
                name: 'Alertas de abuso',
                href: '/gestao/abuso',
                icon: <AlertIcon />,
                visible: permissions.includes('gerenciar-alertas-abuso'),
            },
        ],
    };

    const sourceGroups = isConsole ? [...groups, complianceGroup] : groups;

    const visibleGroups = sourceGroups
        .map((group) => ({ ...group, items: group.items.filter((item) => item.visible !== false) }))
        .filter((group) => group.items.length > 0);
    const showText = isExpanded || isHovered || isMobileOpen;

    const surfaceStyles = isConsole
        ? 'bg-gray-950 border-white/[0.06]'
        : 'bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 border-gray-200';

    const headingStyles = isConsole ? 'text-white/30' : 'text-gray-400';

    const itemStyles = (active: boolean) => {
        if (isConsole) {
            return active
                ? 'bg-brand-500/15 text-white'
                : 'text-gray-400 hover:bg-white/5 hover:text-white';
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
                        {visibleGroups.map((group) => (
                            <div key={group.label}>
                                <h2
                                    className={`mb-4 text-xs uppercase flex leading-[20px] ${headingStyles} ${
                                        !isExpanded && !isHovered ? 'lg:justify-center' : 'justify-start'
                                    }`}
                                >
                                    {showText ? group.label : <HorizontalDotsIcon className="size-6" />}
                                </h2>
                                <ul className="flex flex-col gap-4">
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
                        ))}
                    </div>
                </nav>
            </div>
        </aside>
    );
}
