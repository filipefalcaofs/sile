import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { SharedProps } from '@/types';

interface SettingsLayoutProps {
    children: ReactNode;
}

const navItems = [
    { label: 'Perfil', href: '/settings/profile' },
    { label: 'Senha', href: '/settings/password' },
];

export default function SettingsLayout({ children }: SettingsLayoutProps) {
    const { auth, flash } = usePage<SharedProps>().props;

    const dashboardHref = auth.permissions.includes('acessar-gestao') ? '/gestao' : '/portal';
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <div className="min-h-screen bg-neutral-100 dark:bg-neutral-950">
            <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <div className="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        Configurações da conta
                    </h1>
                    <div className="flex items-center gap-4">
                        <span className="text-sm text-neutral-600 dark:text-neutral-400">
                            {auth.user?.name}
                        </span>
                        <Link
                            href={dashboardHref}
                            className="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm font-medium text-neutral-900 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-100 dark:hover:bg-neutral-800"
                        >
                            Voltar ao painel
                        </Link>
                    </div>
                </div>
            </header>
            {flash.status && (
                <div className="mx-auto mt-4 max-w-5xl px-4">
                    <p className="rounded-lg bg-green-100 p-4 text-sm text-green-800 dark:bg-green-900 dark:text-green-100">
                        {flash.status}
                    </p>
                </div>
            )}
            <div className="mx-auto flex max-w-5xl flex-col gap-6 p-4 sm:flex-row sm:p-6">
                <nav className="flex shrink-0 flex-row gap-2 sm:w-48 sm:flex-col">
                    {navItems.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`rounded-lg px-3 py-2 text-sm font-medium transition ${
                                currentPath === item.href
                                    ? 'bg-blue-700 text-white dark:bg-blue-600'
                                    : 'text-neutral-700 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800'
                            }`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
                <main className="flex-1">{children}</main>
            </div>
        </div>
    );
}
