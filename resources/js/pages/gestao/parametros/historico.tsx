import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Badge from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';

interface HistoryEntry {
    id: number;
    valor_anterior: string | null;
    valor_novo: string | null;
    responsavel: string | null;
    data: string;
}

interface ParameterHistoryProps {
    parameter: {
        key: string;
        description: string;
        sensitive: boolean;
    };
    entries: {
        data: HistoryEntry[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
}

function HistoryValue({ value }: { value: string | null }) {
    if (value === '[criptografado]') {
        return (
            <Badge size="sm" color="light">
                [criptografado]
            </Badge>
        );
    }

    return <>{value ?? '—'}</>;
}

const columns: ColumnDef<HistoryEntry>[] = [
    {
        id: 'data',
        header: 'Data/hora',
        cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (entry) => new Date(entry.data).toLocaleString('pt-BR'),
    },
    {
        id: 'responsavel',
        header: 'Responsável',
        cell: (entry) => entry.responsavel ?? '—',
    },
    {
        id: 'valor_anterior',
        header: 'Valor anterior',
        cell: (entry) => <HistoryValue value={entry.valor_anterior} />,
    },
    {
        id: 'valor_novo',
        header: 'Valor novo',
        cell: (entry) => <HistoryValue value={entry.valor_novo} />,
    },
];

export default function ParameterHistory({ parameter, entries }: ParameterHistoryProps) {
    return (
        <>
            <Head title={`Histórico — ${parameter.description}`} />
            <PageHeader
                title={`Histórico — ${parameter.description}`}
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Parâmetros do sistema', href: '/gestao/parametros' },
                ]}
            />

            <div className="flex flex-col gap-4 md:gap-6">
                <Card>
                    <div className="px-6 py-5">
                        <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                            Alterações registradas
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Valor anterior, valor novo, responsável e data/hora de cada alteração.
                        </p>
                        <p className="mt-1 font-mono text-theme-xs text-gray-400 dark:text-gray-500">
                            {parameter.key}
                        </p>
                    </div>
                    <CardContent>
                        <div className="space-y-5">
                            <DataTable
                                columns={columns}
                                rows={entries.data}
                                rowKey={(entry) => entry.id}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title="Nenhuma alteração registrada"
                                        description="As alterações deste parâmetro aparecem aqui com valor anterior, valor novo e responsável."
                                    />
                                }
                            />
                            <Pagination
                                links={entries.links}
                                meta={{ from: entries.from, to: entries.to, total: entries.total }}
                            />
                        </div>
                    </CardContent>
                </Card>

                <div>
                    <Link
                        href="/gestao/parametros"
                        className="text-sm font-medium text-brand-500 underline-offset-2 transition hover:underline dark:text-brand-400"
                    >
                        Voltar para parâmetros
                    </Link>
                </div>
            </div>
        </>
    );
}

ParameterHistory.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
