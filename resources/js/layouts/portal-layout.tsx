import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppShell from '@/components/app/app-shell';
import type { SidebarGroup } from '@/components/app/app-sidebar';
import { AlertIcon, FileIcon, GridIcon, GroupIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import { ThemeProvider } from '@/contexts/theme-context';
import type { SharedProps } from '@/types';

interface PortalLayoutProps {
    children: ReactNode;
}

const groups: SidebarGroup[] = [
    {
        label: 'Início',
        items: [{ name: 'Meu painel', href: '/portal', icon: <GridIcon /> }],
    },
    {
        label: 'Serviços',
        items: [{ name: 'Procurações', href: '/portal/procuracoes', icon: <FileIcon /> }],
    },
    {
        label: 'Minha conta',
        items: [{ name: 'Meus acessos', href: '/portal/acessos', icon: <GroupIcon /> }],
    },
];

export default function PortalLayout({ children }: PortalLayoutProps) {
    const { actingFor, flash } = usePage<SharedProps>().props;

    return (
        <ThemeProvider>
            <AppShell groups={groups} homeHref="/portal" subtitle="Portal do Cidadão">
                {actingFor && (
                    <div className="mb-6 rounded-xl border border-warning-500 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-3">
                                <span className="text-warning-500">
                                    <AlertIcon className="size-6 fill-current" />
                                </span>
                                <p className="text-sm font-semibold text-gray-800 dark:text-white/90">
                                    Atuando em nome de {actingFor.name}
                                </p>
                            </div>
                            <Link
                                href="/portal/representacao"
                                method="delete"
                                as="button"
                                className="self-start rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 sm:self-auto dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300"
                            >
                                Encerrar representação
                            </Link>
                        </div>
                    </div>
                )}
                {flash.status && (
                    <div className="mb-6">
                        <Alert variant="success" title="Sucesso" message={flash.status} />
                    </div>
                )}
                {children}
            </AppShell>
        </ThemeProvider>
    );
}
