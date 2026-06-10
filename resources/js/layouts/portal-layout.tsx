import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { SharedProps } from '@/types';

interface PortalLayoutProps {
    children: ReactNode;
}

const navItems = [
    { label: 'Meu painel', href: '/portal' },
    { label: 'Procurações', href: '/portal/procuracoes' },
    { label: 'Meus acessos', href: '/portal/acessos' },
];

export default function PortalLayout({ children }: PortalLayoutProps) {
    const { auth, actingFor, flash } = usePage<SharedProps>().props;

    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <div className="min-h-screen bg-neutral-100 dark:bg-neutral-950">
            {actingFor && (
                <div className="bg-amber-100 dark:bg-amber-900">
                    <div className="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                            Atuando em nome de {actingFor.name}
                        </p>
                        <Link
                            href="/portal/representacao"
                            method="delete"
                            as="button"
                            className="self-start rounded-lg border border-amber-700 px-3 py-1 text-sm font-medium text-amber-900 transition hover:bg-amber-200 sm:self-auto dark:border-amber-300 dark:text-amber-100 dark:hover:bg-amber-800"
                        >
                            Encerrar representação
                        </Link>
                    </div>
                </div>
            )}
            <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <div className="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                        SILE — Portal do Cidadão
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
                <nav className="mx-auto flex max-w-5xl gap-1 px-4 pb-3">
                    {navItems.map((item) => (
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
