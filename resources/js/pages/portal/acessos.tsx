import { Head, Link } from '@inertiajs/react';
import Badge from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import PortalLayout from '@/layouts/portal-layout';

interface AccessLogItem {
    id: number;
    event: string;
    ip_address: string | null;
    channel: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface AcessosProps {
    logs: {
        data: AccessLogItem[];
        links: PaginationLink[];
    };
}

const headerCellStyles = 'px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light' | 'dark';

const eventConfig: Record<string, { label: string; color: BadgeColor }> = {
    login: { label: 'Login', color: 'success' },
    logout: { label: 'Saída', color: 'light' },
    falha: { label: 'Tentativa falha', color: 'error' },
    bloqueio: { label: 'Bloqueio temporário', color: 'warning' },
};

function EventBadge({ event }: { event: string }) {
    const config = eventConfig[event] ?? { label: event, color: 'light' as BadgeColor };

    return (
        <Badge size="sm" color={config.color}>
            {config.label}
        </Badge>
    );
}

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
                    <li className="text-sm text-gray-800 dark:text-white/90">{pageTitle}</li>
                </ol>
            </nav>
        </div>
    );
}

function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center gap-1">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        className={`inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-theme-sm font-medium transition ${
                            link.active
                                ? 'bg-brand-500 text-white'
                                : 'text-gray-700 hover:bg-brand-50 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-brand-500/[0.12] dark:hover:text-brand-400'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={index}
                        className="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-theme-sm text-gray-400 dark:text-gray-600"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}

function AccessLogTable({ logs }: { logs: AcessosProps['logs'] }) {
    if (logs.data.length === 0) {
        return (
            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                Nenhum acesso registrado ainda.
            </p>
        );
    }

    return (
        <>
            <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
                <div className="max-w-full overflow-x-auto">
                    <Table>
                        <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
                            <TableRow>
                                <TableCell isHeader className={headerCellStyles}>
                                    Data/hora
                                </TableCell>
                                <TableCell isHeader className={headerCellStyles}>
                                    Evento
                                </TableCell>
                                <TableCell isHeader className={headerCellStyles}>
                                    IP
                                </TableCell>
                                <TableCell isHeader className={headerCellStyles}>
                                    Canal
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                            {logs.data.map((log) => (
                                <TableRow
                                    key={log.id}
                                    className="transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                                >
                                    <TableCell className="px-5 py-4 text-start text-theme-sm font-medium whitespace-nowrap text-gray-800 dark:text-white/90">
                                        {new Date(log.created_at).toLocaleString('pt-BR')}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                        <EventBadge event={log.event} />
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-start text-theme-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {log.ip_address ?? '—'}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400">
                                        {log.channel ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
            <Pagination links={logs.links} />
        </>
    );
}

export default function Acessos({ logs }: AcessosProps) {
    return (
        <PortalLayout>
            <Head title="Meus acessos" />
            <PageBreadcrumb pageTitle="Meus acessos" />

            <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div className="px-6 py-5">
                    <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                        Histórico de acessos
                    </h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Logins, saídas, tentativas falhas e bloqueios registrados na sua conta.
                    </p>
                </div>
                <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                    <div className="space-y-6">
                        <AccessLogTable logs={logs} />
                    </div>
                </div>
            </div>
        </PortalLayout>
    );
}
