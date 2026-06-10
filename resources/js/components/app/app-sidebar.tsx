import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { HorizontalDotsIcon } from '@/components/icons';
import { useSidebar } from '@/contexts/sidebar-context';

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

interface AppSidebarProps {
    groups: SidebarGroup[];
    homeHref: string;
    subtitle?: string;
}

/**
 * Sidebar do TailAdmin adaptada: recebe grupos de navegação com
 * headings (subdivisões, como MENU/OTHERS no template) por props — já
 * filtrados por permissão pelo layout — e marca o item ativo
 * comparando com a URL atual do Inertia. O item do painel (homeHref)
 * só fica ativo em correspondência exata, para não acender junto com
 * as rotas filhas. Grupos sem itens visíveis não renderizam.
 */
export default function AppSidebar({ groups, homeHref, subtitle }: AppSidebarProps) {
    const { isExpanded, isMobileOpen, isHovered, setIsHovered } = useSidebar();
    const { url } = usePage();

    const currentPath = url.split('?')[0] ?? '';

    const isActive = (href: string) => {
        if (href === homeHref) {
            return currentPath === href;
        }

        return currentPath === href || currentPath.startsWith(`${href}/`);
    };

    const visibleGroups = groups
        .map((group) => ({ ...group, items: group.items.filter((item) => item.visible !== false) }))
        .filter((group) => group.items.length > 0);
    const showText = isExpanded || isHovered || isMobileOpen;

    return (
        <aside
            className={`fixed mt-16 flex flex-col lg:mt-0 top-0 px-5 left-0 bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 h-screen transition-all duration-300 ease-in-out z-50 border-r border-gray-200
        ${isExpanded || isMobileOpen ? 'w-[290px]' : isHovered ? 'w-[290px]' : 'w-[90px]'}
        ${isMobileOpen ? 'translate-x-0' : '-translate-x-full'}
        lg:translate-x-0`}
            onMouseEnter={() => !isExpanded && setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <div className={`py-8 flex ${!isExpanded && !isHovered ? 'lg:justify-center' : 'justify-start'}`}>
                <Link href={homeHref}>
                    {showText ? (
                        <span className="flex flex-col">
                            <span className="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                                SILE
                            </span>
                            {subtitle && (
                                <span className="text-theme-xs text-gray-500 dark:text-gray-400">{subtitle}</span>
                            )}
                        </span>
                    ) : (
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-base font-semibold text-white">
                            S
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
                                    className={`mb-4 text-xs uppercase flex leading-[20px] text-gray-400 ${
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
                                                className={`menu-item group ${
                                                    isActive(item.href) ? 'menu-item-active' : 'menu-item-inactive'
                                                }`}
                                            >
                                                <span
                                                    className={`menu-item-icon-size ${
                                                        isActive(item.href)
                                                            ? 'menu-item-icon-active'
                                                            : 'menu-item-icon-inactive'
                                                    }`}
                                                >
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
