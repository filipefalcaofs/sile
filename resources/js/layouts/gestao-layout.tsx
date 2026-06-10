import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { SharedProps } from '@/types';

interface GestaoLayoutProps {
    children: ReactNode;
}

const navItems = [
    { label: 'Painel', href: '/gestao' },
    { label: 'CNAEs', href: '/gestao/cnaes', permission: 'consultar-cnaes' },
    { label: 'Usuários', href: '/gestao/usuarios', permission: 'manter-usuarios' },
    { label: 'Perfis', href: '/gestao/perfis', permission: 'manter-perfis' },
    { label: 'Parâmetros', href: '/gestao/parametros', permission: 'manter-parametros' },
];

export default function GestaoLayout({ children }: GestaoLayoutProps) {
    const { auth, flash } = usePage<SharedProps>().props;

    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    const visibleNavItems = navItems.filter(
        (item) => !item.permission || auth.permissions.includes(item.permission),
    );

    return (
        <div className="min-h-screen bg-neutral-100 dark:bg-neutral-950">
            <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <div className="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        SILE — Gestão SEDUR
                    </h1>
                    <div className="flex items-center gap-4">
                        <span className="text-sm text-neutral-600 dark:text-neutral-400">
                            {auth.user?.name}
                        </span>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm font-medium text-neutral-900 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-100 dark:hover:bg-neutral-800"
                        >
                            Sair
                        </Link>
                    </div>
                </div>
                <nav className="mx-auto flex max-w-5xl flex-wrap gap-1 px-4 pb-3">
                    {visibleNavItems.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`rounded-lg px-3 py-1.5 text-sm font-medium transition ${
                                currentPath === item.href
                                    ? 'bg-blue-700 text-white dark:bg-blue-600'
                                    : 'text-neutral-700 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800'
                            }`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            </header>
            {flash.status && (
                <div className="mx-auto mt-4 max-w-5xl px-4">
                    <p className="rounded-lg bg-green-100 p-4 text-sm text-green-800 dark:bg-green-900 dark:text-green-100">
                        {flash.status}
                    </p>
                </div>
            )}
            <main className="mx-auto max-w-5xl p-4 sm:p-6">{children}</main>
        </div>
    );
}
