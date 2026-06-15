import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ChevronDownIcon,
    CloseIcon,
    GearIcon,
    HorizontalDotsIcon,
    LogoutIcon,
    MenuIcon,
    MoonIcon,
    SunIcon,
} from '@/components/icons';
import NotificationBell from '@/components/notificacoes/NotificationBell';
import { Dropdown } from '@/components/ui/dropdown';
import { DropdownItem } from '@/components/ui/dropdown-item';
import { useSidebar } from '@/contexts/sidebar-context';
import { useTheme } from '@/contexts/theme-context';
import type { SharedProps } from '@/types';

function ThemeToggleButton() {
    const { toggleTheme } = useTheme();

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label="Alternar tema"
            className="relative flex items-center justify-center text-gray-500 transition-colors bg-white border border-gray-200 rounded-full h-11 w-11 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <SunIcon className="hidden dark:block" />
            <MoonIcon className="dark:hidden" />
        </button>
    );
}

function UserDropdown({ logoutHref }: { logoutHref: string }) {
    const { auth } = usePage<SharedProps>().props;
    const [isOpen, setIsOpen] = useState(false);

    const userName = auth.user?.name ?? '';
    const userEmail = auth.user?.email ?? '';
    const initials = userName
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    function toggleDropdown() {
        setIsOpen(!isOpen);
    }

    function closeDropdown() {
        setIsOpen(false);
    }

    return (
        <div className="relative">
            <button
                type="button"
                onClick={toggleDropdown}
                className="flex items-center text-gray-700 dropdown-toggle dark:text-gray-400"
            >
                <span className="mr-3 flex h-11 w-11 items-center justify-center overflow-hidden rounded-full bg-brand-500 text-sm font-semibold text-white">
                    {initials}
                </span>

                <span className="block mr-1 font-medium text-theme-sm">{userName}</span>
                <ChevronDownIcon
                    className={`stroke-gray-500 dark:stroke-gray-400 transition-transform duration-200 ${
                        isOpen ? 'rotate-180' : ''
                    }`}
                />
            </button>

            <Dropdown
                isOpen={isOpen}
                onClose={closeDropdown}
                className="absolute right-0 mt-[17px] flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
            >
                <div>
                    <span className="block font-medium text-gray-700 text-theme-sm dark:text-gray-400">{userName}</span>
                    <span className="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">{userEmail}</span>
                </div>

                <ul className="flex flex-col gap-1 pt-4 pb-3 border-b border-gray-200 dark:border-gray-800">
                    <li>
                        <DropdownItem
                            onItemClick={closeDropdown}
                            tag="a"
                            href="/settings/profile"
                            className="flex items-center gap-3 px-3 py-2 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                        >
                            <GearIcon className="text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-300" />
                            Configurações da conta
                        </DropdownItem>
                    </li>
                </ul>
                <Link
                    href={logoutHref}
                    method="post"
                    as="button"
                    className="flex items-center gap-3 px-3 py-2 mt-3 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                >
                    <LogoutIcon className="text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-300" />
                    Sair
                </Link>
            </Dropdown>
        </div>
    );
}

interface AppHeaderProps {
    homeHref: string;
    logoutHref: string;
}

/**
 * Header do TailAdmin adaptado: mantém o toggle da sidebar, o toggle
 * de tema e o menu do usuário; remove busca e notificações fictícias.
 */
export default function AppHeader({ homeHref, logoutHref }: AppHeaderProps) {
    const [isApplicationMenuOpen, setApplicationMenuOpen] = useState(false);

    const { isMobileOpen, toggleSidebar, toggleMobileSidebar } = useSidebar();

    const handleToggle = () => {
        if (window.innerWidth >= 1024) {
            toggleSidebar();
        } else {
            toggleMobileSidebar();
        }
    };

    const toggleApplicationMenu = () => {
        setApplicationMenuOpen(!isApplicationMenuOpen);
    };

    return (
        <header className="sticky top-0 flex w-full bg-white border-gray-200 z-99999 dark:border-gray-800 dark:bg-gray-900 lg:border-b">
            <div className="flex flex-col items-center justify-between grow lg:flex-row lg:px-6">
                <div className="flex items-center justify-between w-full gap-2 px-3 py-3 border-b border-gray-200 dark:border-gray-800 sm:gap-4 lg:justify-normal lg:border-b-0 lg:px-0 lg:py-4">
                    <button
                        type="button"
                        className="flex items-center justify-center w-10 h-10 text-gray-500 border-gray-200 rounded-lg z-99999 dark:border-gray-800 dark:text-gray-400 lg:h-11 lg:w-11 lg:border"
                        onClick={handleToggle}
                        aria-label="Alternar menu lateral"
                    >
                        {isMobileOpen ? <CloseIcon /> : <MenuIcon />}
                    </button>

                    <Link href={homeHref} className="lg:hidden">
                        <span className="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">SIMPLIFICA</span>
                    </Link>

                    <button
                        type="button"
                        onClick={toggleApplicationMenu}
                        aria-label="Abrir menu do cabeçalho"
                        className="flex items-center justify-center w-10 h-10 text-gray-700 rounded-lg z-99999 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:hidden"
                    >
                        <HorizontalDotsIcon />
                    </button>
                </div>
                <div
                    className={`${
                        isApplicationMenuOpen ? 'flex' : 'hidden'
                    } items-center justify-between w-full gap-4 px-5 py-4 lg:flex shadow-theme-md lg:justify-end lg:px-0 lg:shadow-none`}
                >
                    <div className="flex items-center gap-2 2xsm:gap-3">
                        <ThemeToggleButton />
                        <NotificationBell homeHref={homeHref} />
                    </div>
                    <UserDropdown logoutHref={logoutHref} />
                </div>
            </div>
        </header>
    );
}
