import { Head } from '@inertiajs/react';
import PageHeader from '@/components/app/page-header';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';

interface AccessLogItem {
    id: number;
    event: string;
    ip_address: string | null;
    channel: string | null;
    created_at: string;
}

interface AcessosProps {
    targetUser: {
        id: number;
        name: string;
        email: string;
    };
    logs: {
        data: AccessLogItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
}

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

const columns: ColumnDef<AccessLogItem>[] = [
    {
        id: 'created_at',
        header: 'Data/hora',
        cellClassName: 'font-medium text-gray-800 dark:text-white/90 whitespace-nowrap',
        cell: (log) => new Date(log.created_at).toLocaleString('pt-BR'),
    },
    {
        id: 'event',
        header: 'Evento',
        cell: (log) => <EventBadge event={log.event} />,
    },
    {
        id: 'ip',
        header: 'IP',
        cellClassName: 'whitespace-nowrap',
        cell: (log) => log.ip_address ?? '—',
    },
    {
        id: 'channel',
        header: 'Canal',
        cell: (log) => log.channel ?? '—',
    },
];

export default function Acessos({ targetUser, logs }: AcessosProps) {
    return (
        <GestaoLayout>
            <Head title={`Acessos de ${targetUser.name}`} />
            <PageHeader
                title={`Acessos de ${targetUser.name}`}
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Usuários', href: '/gestao/usuarios' },
                ]}
            />

            <Card>
                <CardHeader
                    title="Histórico de acessos"
                    description={`Logins, saídas, tentativas falhas e bloqueios registrados na conta de ${targetUser.email}.`}
                />
                <CardContent>
                    <div className="space-y-5">
                        <DataTable
                            columns={columns}
                            rows={logs.data}
                            rowKey={(log) => log.id}
                            emptyState={
                                <EmptyState
                                    title="Nenhum acesso registrado"
                                    description="Logins, saídas, tentativas falhas e bloqueios desta conta aparecem aqui."
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
