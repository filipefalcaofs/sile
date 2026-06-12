import { Head, router } from '@inertiajs/react';
import PageHeader from '@/components/app/page-header';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';

type EmailStatus = 'na_fila' | 'enviado' | 'falhou';
type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light' | 'dark';

interface EmailLogItem {
    id: number;
    recipient_email: string;
    recipient_name: string | null;
    type: string;
    status: EmailStatus;
    error_message: string | null;
    queued_at: string | null;
    sent_at: string | null;
    failed_at: string | null;
    created_at: string;
}

interface EmailsProps {
    logs: {
        data: EmailLogItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { status: string | null };
    counts: { na_fila: number; enviado: number; falhou: number };
}

const statusConfig: Record<EmailStatus, { label: string; color: BadgeColor }> = {
    na_fila: { label: 'Na fila', color: 'warning' },
    enviado: { label: 'Enviado', color: 'success' },
    falhou: { label: 'Falhou', color: 'error' },
};

function StatusBadge({ status }: { status: EmailStatus }) {
    const config = statusConfig[status] ?? { label: status, color: 'light' as BadgeColor };
    return <Badge size="sm" color={config.color}>{config.label}</Badge>;
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR');
}

const columns: ColumnDef<EmailLogItem>[] = [
    {
        id: 'created_at',
        header: 'Data',
        cellClassName: 'whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (log) => formatDate(log.created_at),
    },
    {
        id: 'recipient',
        header: 'Destinatário',
        cell: (log) => (
            <div>
                <p className="text-sm font-medium text-gray-800 dark:text-white/90">{log.recipient_email}</p>
                {log.recipient_name && (
                    <p className="text-xs text-gray-500 dark:text-gray-400">{log.recipient_name}</p>
                )}
            </div>
        ),
    },
    {
        id: 'type',
        header: 'Tipo',
        cell: (log) => log.type,
    },
    {
        id: 'status',
        header: 'Status',
        cell: (log) => <StatusBadge status={log.status} />,
    },
    {
        id: 'sent_at',
        header: 'Enviado em',
        cellClassName: 'whitespace-nowrap text-gray-600 dark:text-gray-400',
        cell: (log) => formatDate(log.sent_at),
    },
    {
        id: 'error',
        header: 'Erro',
        cell: (log) =>
            log.error_message ? (
                <span className="max-w-xs truncate text-xs text-error-600 dark:text-error-400" title={log.error_message}>
                    {log.error_message}
                </span>
            ) : (
                '—'
            ),
    },
];

const FILTER_TABS: { label: string; value: string | null }[] = [
    { label: 'Todos', value: null },
    { label: 'Na fila', value: 'na_fila' },
    { label: 'Enviados', value: 'enviado' },
    { label: 'Falharam', value: 'falhou' },
];

export default function EmailsIndex({ logs, filters, counts }: EmailsProps) {
    function applyFilter(status: string | null) {
        router.get('/gestao/emails', status ? { status } : {}, {
            preserveScroll: true,
            replace: true,
        });
    }

    const activeFilter = filters.status ?? null;

    return (
        <GestaoLayout>
            <Head title="Log de e-mails" />
            <PageHeader
                title="Log de e-mails"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }]}
            />

            <div className="mb-5 grid grid-cols-3 gap-4">
                <SummaryCard label="Na fila" count={counts.na_fila} color="warning" />
                <SummaryCard label="Enviados" count={counts.enviado} color="success" />
                <SummaryCard label="Falharam" count={counts.falhou} color="error" />
            </div>

            <Card>
                <CardHeader
                    title="Histórico de envios"
                    description="Todos os e-mails disparados pelo sistema — verificação, redefinição de senha e notificações."
                />
                <CardContent>
                    <div className="space-y-5">
                        <div className="flex gap-2">
                            {FILTER_TABS.map((tab) => (
                                <button
                                    key={String(tab.value)}
                                    onClick={() => applyFilter(tab.value)}
                                    className={`rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                                        activeFilter === tab.value
                                            ? 'bg-brand-500 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        <DataTable
                            columns={columns}
                            rows={logs.data}
                            rowKey={(log) => log.id}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title="Nenhum e-mail encontrado"
                                    description="Os e-mails enviados pelo sistema aparecerão aqui."
                                />
                            }
                        />

                        <Pagination links={logs.links} meta={{ from: logs.from, to: logs.to, total: logs.total }} />
                    </div>
                </CardContent>
            </Card>
        </GestaoLayout>
    );
}

function SummaryCard({
    label,
    count,
    color,
}: {
    label: string;
    count: number;
    color: 'warning' | 'success' | 'error';
}) {
    const colorMap: Record<string, string> = {
        warning: 'border-warning-200 bg-warning-50 dark:border-warning-800 dark:bg-warning-900/20',
        success: 'border-success-200 bg-success-50 dark:border-success-800 dark:bg-success-900/20',
        error: 'border-error-200 bg-error-50 dark:border-error-800 dark:bg-error-900/20',
    };
    const textMap: Record<string, string> = {
        warning: 'text-warning-700 dark:text-warning-400',
        success: 'text-success-700 dark:text-success-400',
        error: 'text-error-700 dark:text-error-400',
    };

    return (
        <div className={`rounded-xl border p-4 ${colorMap[color]}`}>
            <p className={`text-2xl font-bold ${textMap[color]}`}>{count}</p>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{label}</p>
        </div>
    );
}
