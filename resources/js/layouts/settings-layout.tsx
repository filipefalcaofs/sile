import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { LockIcon, UserCircleIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import { ThemeProvider } from '@/contexts/theme-context';
import type { SharedProps } from '@/types';

interface SettingsLayoutProps {
    children: ReactNode;
}

const navItems = [
    { label: 'Perfil', href: '/settings/profile', icon: <UserCircleIcon /> },
    { label: 'Senha', href: '/settings/password', icon: <LockIcon /> },
];

export default function SettingsLayout({ children }: SettingsLayoutProps) {
    const { auth, flash } = usePage<SharedProps>().props;
    const { url } = usePage();

    const dashboardHref = auth.permissions.includes('acessar-gestao') ? '/gestao' : '/portal';
    const currentPath = url.split('?')[0] ?? '';

    return (
        <ThemeProvider>
            <div className="min-h-screen">
                <header className="sticky top-0 z-99 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div className="mx-auto flex max-w-(--breakpoint-2xl) flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6">
                        <h1 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                            Configurações da conta
                        </h1>
                        <div className="flex items-center gap-4">
                            <span className="text-theme-sm text-gray-500 dark:text-gray-400">{auth.user?.name}</span>
                            <Link
                                href={dashboardHref}
                                className="rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300"
                            >
                                Voltar ao painel
                            </Link>
                        </div>
                    </div>
                </header>
                <div className="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                    {flash.status && (
                        <div className="mb-6">
                            <Alert variant="success" title="Sucesso" message={flash.status} />
                        </div>
                    )}
                    <div className="flex flex-col gap-6 sm:flex-row">
                        <nav className="shrink-0 sm:w-56">
                            <div className="rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                <ul className="flex flex-row gap-1 sm:flex-col">
                                    {navItems.map((item) => (
                                        <li key={item.href} className="flex-1 sm:flex-none">
                                            <Link
                                                href={item.href}
                                                className={`menu-item group ${
                                                    currentPath === item.href
                                                        ? 'menu-item-active'
                                                        : 'menu-item-inactive'
                                                }`}
                                            >
                                                <span
                                                    className={`menu-item-icon-size ${
                                                        currentPath === item.href
                                                            ? 'menu-item-icon-active'
                                                            : 'menu-item-icon-inactive'
                                                    }`}
                                                >
                                                    {item.icon}
                                                </span>
                                                <span className="menu-item-text">{item.label}</span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </nav>
                        <main className="flex-1">{children}</main>
                    </div>
                </div>
            </div>
        </ThemeProvider>
    );
}
