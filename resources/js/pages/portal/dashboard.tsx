import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowRightIcon, FileIcon, GroupIcon } from '@/components/icons';
import PortalLayout from '@/layouts/portal-layout';
import type { SharedProps } from '@/types';

interface QuickAccessCard {
    name: string;
    label: string;
    href: string;
    icon: ReactNode;
}

const cards: QuickAccessCard[] = [
    {
        name: 'Procurações',
        label: 'Vínculos de representação',
        href: '/portal/procuracoes',
        icon: <FileIcon className="size-6 text-gray-800 dark:text-white/90" />,
    },
    {
        name: 'Meus acessos',
        label: 'Histórico da sua conta',
        href: '/portal/acessos',
        icon: <GroupIcon className="size-6 text-gray-800 dark:text-white/90" />,
    },
];

function PageBreadcrumb({ pageTitle }: { pageTitle: string }) {
    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{pageTitle}</h2>
            <nav aria-label="Trilha de navegação">
                <ol className="flex flex-wrap items-center gap-1.5">
                    <li>
                        <Link
                            className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                            href="/portal"
                        >
                            Portal
                            <svg
                                className="stroke-current"
                                width="17"
                                height="16"
                                viewBox="0 0 17 16"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                                    strokeWidth="1.2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        </Link>
                    </li>
                    <li className="text-sm text-gray-800 dark:text-white/90">Painel</li>
                </ol>
            </nav>
        </div>
    );
}

export default function Dashboard() {
    const { auth } = usePage<SharedProps>().props;

    return (
        <PortalLayout>
            <Head title="Meu painel" />
            <PageBreadcrumb pageTitle="Meu painel" />

            <div className="grid grid-cols-12 gap-4 md:gap-6">
                <div className="col-span-12">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
                        {cards.map((card) => (
                            <Link
                                key={card.href}
                                href={card.href}
                                className="rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40 md:p-6"
                            >
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                                    {card.icon}
                                </div>
                                <div className="mt-5 flex items-end justify-between">
                                    <div>
                                        <span className="text-sm text-gray-500 dark:text-gray-400">
                                            {card.label}
                                        </span>
                                        <h4 className="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                                            {card.name}
                                        </h4>
                                    </div>
                                    <ArrowRightIcon className="mb-1.5 size-5 text-gray-400 dark:text-gray-500" />
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                <div className="col-span-12">
                    <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div className="px-6 py-5">
                            <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                                Minha conta
                            </h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Bem-vindo(a) ao SILE.
                            </p>
                        </div>
                        <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Nome</dt>
                                    <dd className="mt-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        {auth.user?.name ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">E-mail</dt>
                                    <dd className="mt-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        {auth.user?.email ?? '—'}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </PortalLayout>
    );
}
